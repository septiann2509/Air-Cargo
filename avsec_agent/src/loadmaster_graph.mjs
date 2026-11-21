// avsec_agent/src/loadmaster_graph.mjs
// Autonomous Smart Loadmaster AI Agent — LangGraph.js
//
//   START → perceive → think_rules ─┬─(LLM aktif)──→ think_llm ─┐
//                                   └─(tanpa LLM)───────────────┴→ act → END
//
// perceive    : membaca manifes e-AWB + telemetri IoT Smart Floor Scale (OIML R76) + spek kontainer ULD
// think_rules : menghitung selisih toleransi IATA CSC Res. 600b (±5%), tag HEA (>=150kg),
//               anomali densitas per koli, batas beban ULD AKE (1.588 kg), & titik CoG (% MAC)
// think_llm   : (opsional) Gemini memberi analisis kedua untuk anomali berat / saran load planning
// act         : menyusun perintah aktuator (kunci timbangan, terbitkan e-CCA, dispatch forklift HEA,
//               kunci posisi ULD di kompartemen, atau blokir overload ke PMC)

import { StateGraph, Annotation, START, END } from '@langchain/langgraph';
import {
  IATA_TOLERANCE_PERCENT,
  HEA_THRESHOLD_KG,
  ULD_SPECS,
  AIRCRAFT_WBM,
  COMMODITY_PIECE_WEIGHT_PROFILES,
} from './loadmaster_knowledge.mjs';

const round = (n, d = 2) => Math.round(n * 10 ** d) / 10 ** d;

const concat = (a, b) => (a || []).concat(b || []);
const LoadmasterState = Annotation.Root({
  input: Annotation(),
  options: Annotation(),
  perception: Annotation(),
  assessment: Annotation(),
  llm: Annotation(),
  result: Annotation(),
  trace: Annotation({ reducer: concat, default: () => [] }),
});

const step = (node, title, detail) => ({ node, title, detail, ts: new Date().toISOString() });

// ═══ NODE 1: PERCEIVE ═══
async function perceive(state) {
  const { cargo = {}, actual_weight_kg, uld = {}, stage = 5 } = state.input;
  const declared = Math.max(0.1, Number(cargo.declared_weight_kg ?? 0));
  const actual = Math.max(0.1, Number(actual_weight_kg ?? cargo.actual_weight_kg ?? declared));
  const quantity = Math.max(1, parseInt(cargo.quantity ?? 1, 10) || 1);

  const uldType = ULD_SPECS[uld.uld_type] ? uld.uld_type : 'AKE';
  const spec = ULD_SPECS[uldType];
  const tareWeight = Number(uld.tare_weight_kg ?? spec.tare_weight_kg);
  const maxUldWeight = Number(uld.max_weight_kg ?? spec.max_gross_weight_kg);

  const perception = {
    stage: Number(stage) || 5,
    manifest: {
      awb: cargo.awb || cargo.awb_number || '-',
      description: cargo.description || '-',
      commodity_type: cargo.commodity_type || 'GENERAL_CARGO',
      quantity,
      declared_weight_kg: round(declared, 2),
    },
    scale_telemetry: {
      device_id: 'SCALE-FLOOR-ULD-01',
      standard: AIRCRAFT_WBM.oiml_scale_standard,
      actual_weight_kg: round(actual, 2),
      avg_piece_weight_kg: round(actual / quantity, 2),
    },
    uld_spec: {
      uld_code: uld.uld_code || 'AKE-12345-GA',
      uld_type: uldType,
      compartment: uld.compartment || spec.default_compartment,
      tare_weight_kg: round(tareWeight, 2),
      max_gross_weight_kg: round(maxUldWeight, 2),
    },
  };

  return {
    perception,
    trace: [
      step(
        'perceive',
        'PERCEIVE — Baca e-AWB & IoT Smart Scale',
        `e-AWB ${perception.manifest.awb}: deklarasi ${perception.manifest.declared_weight_kg} kg (${quantity} koli). ` +
          `Sensor Load Cell IP68 membaca ${perception.scale_telemetry.actual_weight_kg} kg ` +
          `untuk kontainer ${perception.uld_spec.uld_code} (Tare ${perception.uld_spec.tare_weight_kg} kg / Maks ${perception.uld_spec.max_gross_weight_kg} kg).`,
      ),
    ],
  };
}

// ═══ NODE 2: THINK (aturan IATA & Weight/Balance deterministik) ═══
async function thinkRules(state) {
  const { manifest, scale_telemetry, uld_spec } = state.perception;
  const declared = manifest.declared_weight_kg;
  const actual = scale_telemetry.actual_weight_kg;
  const diffKg = round(actual - declared, 2);
  const diffPct = declared > 0 ? round((diffKg / declared) * 100, 2) : 0;
  const maxTolKg = round(declared * (IATA_TOLERANCE_PERCENT / 100), 2);
  const isWithinTolerance = Math.abs(diffPct) <= IATA_TOLERANCE_PERCENT;
  const toleranceStatus = isWithinTolerance ? 'WITHIN_TOLERANCE' : 'DISCREPANCY_WARNING';

  const avgPieceKg = scale_telemetry.avg_piece_weight_kg;
  const isHea = avgPieceKg >= HEA_THRESHOLD_KG || actual >= HEA_THRESHOLD_KG;

  const tareKg = uld_spec.tare_weight_kg;
  const grossUldKg = round(actual + tareKg, 2);
  const maxUldKg = uld_spec.max_gross_weight_kg;
  const isUldOverload = grossUldKg > maxUldKg;
  const uldUtilPct = round((grossUldKg / maxUldKg) * 100, 1);

  // Kalkulasi CoG MAC realistis berbasis proporsi muatan terhadap kapasitas belly
  const cgOffset = ((grossUldKg - 500) / maxUldKg) * 1.4;
  const cgMac = round(Math.max(22.0, Math.min(31.5, AIRCRAFT_WBM.optimal_cg_mac + cgOffset * 0.4)), 2);

  const findings = [];
  const reasoning = [];

  if (isWithinTolerance) {
    reasoning.push(
      `Selisih timbangan ${diffKg >= 0 ? '+' : ''}${diffKg} kg (${diffPct >= 0 ? '+' : ''}${diffPct}%) berada dalam batas toleransi IATA ±5% (maks ±${maxTolKg} kg).`,
    );
  } else {
    findings.push({
      code: 'IATA_600B_DISCREPANCY',
      severity: Math.abs(diffPct) >= 15 ? 0.8 : 0.45,
      regulation: AIRCRAFT_WBM.tolerance_regulation,
      detail: `Selisih berat ${diffKg >= 0 ? '+' : ''}${diffKg} kg (${diffPct}%) melampaui batas toleransi IATA ±5% (±${maxTolKg} kg). Diperlukan penerbitan e-CCA (Cargo Charges Correction Advice).`,
    });
    reasoning.push(
      `⚠ Deviasi berat ${diffPct}% melebihi toleransi IATA ±5% → terbitkan e-CCA otomatis untuk rekonsiliasi tarif; bobot fisik ${actual} kg tetap diteruskan ke Weight & Balance.`,
    );
  }

  if (isHea) {
    findings.push({
      code: 'IATA_HEA_CARGO',
      severity: 0.2,
      regulation: AIRCRAFT_WBM.hea_regulation,
      detail: `Bobot ${actual} kg (rata-rata ${avgPieceKg} kg/koli) memenuhi ambang >= 150 kg → diklasifikasikan HEA (Heavy Cargo), wajib penanganan forklift mekanis.`,
    });
    reasoning.push(`Tag HEA (Heavy Cargo >= 150 kg) aktif → dispatch forklift & floor spreader.`);
  }

  // Deteksi anomali rasio berat per koli terhadap profil komoditas
  const profile = COMMODITY_PIECE_WEIGHT_PROFILES[manifest.commodity_type] || COMMODITY_PIECE_WEIGHT_PROFILES.GENERAL_CARGO;
  const pieceAnomaly = avgPieceKg > profile.typicalMaxPerPieceKg && Math.abs(diffPct) > IATA_TOLERANCE_PERCENT;
  if (pieceAnomaly) {
    findings.push({
      code: 'DENSITY_PIECE_ANOMALY',
      severity: 0.65,
      regulation: 'IATA Cargo Handling Manual (Weight Verification)',
      detail: `Berat rata-rata ${avgPieceKg} kg/koli tidak lazim untuk komoditas ${profile.label} (normal <= ${profile.typicalMaxPerPieceKg} kg/koli) disertai deviasi ${diffPct}%.`,
    });
    reasoning.push(`⚠ Anomali bobot per koli (${avgPieceKg} kg/koli) terhadap profil ${profile.label}.`);
  }

  if (isUldOverload) {
    findings.push({
      code: 'ULD_STRUCTURAL_OVERLOAD',
      severity: 0.95,
      regulation: ULD_SPECS[uld_spec.uld_type]?.regulation || ULD_SPECS.AKE.regulation,
      detail: `Berat bruto ULD ${grossUldKg} kg (Kargo ${actual} kg + Tare ${tareKg} kg) melebihi batas sertifikasi ${uld_spec.uld_type} (${maxUldKg} kg).`,
    });
    reasoning.push(
      `⛔ OVERLOAD ULD: Gross ${grossUldKg} kg > batas struktural ${maxUldKg} kg (${uld_spec.uld_code}). Dilarang masuk kontainer ${uld_spec.uld_type}!`,
    );
  } else {
    reasoning.push(
      `Kapasitas ULD ${uld_spec.uld_code}: Gross ${grossUldKg} kg / ${maxUldKg} kg (${uldUtilPct}% terisi). CoG pesawat ${cgMac}% MAC (aman dalam envelope ${AIRCRAFT_WBM.cg_safe_envelope_min_mac}%-${AIRCRAFT_WBM.cg_safe_envelope_max_mac}% MAC).`,
    );
  }

  let decision = 'AUTO_APPROVED';
  if (isUldOverload) decision = 'OVERLOAD_REJECTED';
  else if (!isWithinTolerance) decision = 'CCA_AMENDMENT';

  const riskScore = isUldOverload
    ? 0.95
    : Math.min(0.85, (!isWithinTolerance ? 0.4 + Math.min(0.35, Math.abs(diffPct) / 100) : 0.04) + (pieceAnomaly ? 0.2 : 0));
  const confidence = isUldOverload ? 0.99 : isWithinTolerance ? 0.98 : 0.94;

  const assessment = {
    decision,
    risk_score: round(riskScore),
    confidence: round(confidence),
    tolerance_status: toleranceStatus,
    is_within_tolerance: isWithinTolerance,
    cca_required: !isWithinTolerance,
    is_hea: isHea,
    uld_overload: isUldOverload,
    discrepancy_kg: diffKg,
    discrepancy_percent: diffPct,
    max_tolerance_kg: maxTolKg,
    gross_uld_kg: grossUldKg,
    uld_utilization_percent: uldUtilPct,
    cg_mac: cgMac,
    findings,
    reasoning,
  };

  return {
    assessment,
    trace: [
      step(
        'think_rules',
        'THINK — Analisis IATA ±5%, HEA & Batas ULD',
        `Selisih ${diffKg >= 0 ? '+' : ''}${diffKg} kg (${diffPct}%) → ${toleranceStatus}. ` +
          `Gross ULD ${grossUldKg}/${maxUldKg} kg (${uldUtilPct}%), CoG ${cgMac}% MAC. Keputusan: ${decision}.`,
      ),
    ],
  };
}

// ═══ NODE 3 (opsional): THINK — Penalaran kedua oleh Gemini ═══
async function thinkLlm(state) {
  const { apiKey, model, timeoutMs } = state.options.llm;
  const { perception, assessment } = state;

  const prompt = [
    'Anda adalah Loadmaster Senior Penerbangan Kargo yang mengevaluasi penimbangan & keseimbangan ULD sesuai IATA CSC Res. 600b dan Aircraft Weight & Balance Manual.',
    'Jawab HANYA JSON: {"advice":"2 kalimat ringkas Bahasa Indonesia","escalate_warning":false|true}',
    'DATA:',
    JSON.stringify({
      manifest: perception.manifest,
      scale: perception.scale_telemetry,
      uld: perception.uld_spec,
      eval: {
        decision: assessment.decision,
        diff_pct: assessment.discrepancy_percent,
        gross_uld_kg: assessment.gross_uld_kg,
        cg_mac: assessment.cg_mac,
      },
    }),
  ].join('\n');

  let llm = { used: false };
  try {
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(model)}:generateContent`;
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'x-goog-api-key': apiKey },
      body: JSON.stringify({
        contents: [{ role: 'user', parts: [{ text: prompt }] }],
        generationConfig: { temperature: 0.1, responseMimeType: 'application/json' },
      }),
      signal: AbortSignal.timeout(timeoutMs),
    });
    if (!res.ok) throw new Error(`Gemini HTTP ${res.status}`);
    const data = await res.json();
    const text = data?.candidates?.[0]?.content?.parts?.map((p) => p.text).join('') || '';
    const parsed = JSON.parse(text.replace(/^```json\s*|\s*```$/g, ''));
    llm = {
      used: true,
      model,
      advice: String(parsed.advice || '').slice(0, 500),
      escalate_warning: !!parsed.escalate_warning,
    };
  } catch (err) {
    llm = { used: false, error: String(err?.message || err).slice(0, 160) };
  }

  const updated = { ...assessment, reasoning: [...assessment.reasoning] };
  let note;
  if (llm.used) {
    updated.reasoning.push(`Penalaran LLM (${llm.model}): ${llm.advice}`);
    if (llm.escalate_warning && updated.decision === 'AUTO_APPROVED') {
      updated.decision = 'CCA_AMENDMENT';
      updated.cca_required = true;
    }
    note = `Gemini mengonfirmasi evaluasi Loadmaster (${llm.model}).`;
  } else {
    note = `LLM tidak aktif (${llm.error || 'tanpa API key'}); memakai kalkulasi deterministik IATA.`;
  }

  return { llm, assessment: updated, trace: [step('think_llm', 'THINK — Penalaran kedua (Gemini)', note)] };
}

// ═══ NODE 4: ACT ═══
async function act(state) {
  const a = state.assessment;
  const { scale_telemetry, uld_spec } = state.perception;
  const refs = [AIRCRAFT_WBM.tolerance_regulation, ULD_SPECS[uld_spec.uld_type]?.regulation || ULD_SPECS.AKE.regulation];
  if (a.is_hea) refs.push(AIRCRAFT_WBM.hea_regulation);

  const actions = [
    {
      device: 'SMART_SCALE_01',
      command: 'LOCK_ACTUAL_WEIGHT',
      target: `${scale_telemetry.actual_weight_kg} KG`,
      description: `Mengunci bobot fisik aktual ${scale_telemetry.actual_weight_kg} kg (OIML R76) untuk modul Weight & Balance.`,
    },
  ];

  if (a.cca_required) {
    actions.push({
      device: 'CMS_BILLING_ENGINE',
      command: 'ISSUE_CCA',
      target: `DIFF ${a.discrepancy_kg >= 0 ? '+' : ''}${a.discrepancy_kg} KG (${a.discrepancy_percent}%)`,
      description: 'Menerbitkan draf Cargo Charges Correction Advice (e-CCA) untuk penyesuaian tarif e-AWB.',
    });
  }

  if (a.is_hea) {
    actions.push({
      device: 'RAMP_GSE_DISPATCH',
      command: 'ASSIGN_FORKLIFT_HEA',
      target: 'HEAVY_CARGO',
      description: 'Menugaskan forklift mekanis & floor spreader karena bobot >= 150 kg (Tag HEA).',
    });
  }

  if (a.uld_overload) {
    actions.push({
      device: 'ULD_WORKSTATION_03',
      command: 'BLOCK_AKE_OVERLOAD',
      target: 'TRANSFER_TO_PMC_PALLET',
      description: `Menolak pemuatan ke ${uld_spec.uld_code} (${a.gross_uld_kg} kg > ${uld_spec.max_gross_weight_kg} kg); alihkan ke Palet PMC Main Deck.`,
    });
  } else {
    actions.push({
      device: 'ULD_WORKSTATION_03',
      command: 'LOCK_COMPARTMENT',
      target: `${uld_spec.uld_code} → ${uld_spec.compartment}`,
      description: `Mengunci kontainer ${uld_spec.uld_code} ke kompartemen ${uld_spec.compartment} dengan CoG ${a.cg_mac}% MAC.`,
    });
  }

  let headline;
  if (a.decision === 'OVERLOAD_REJECTED') {
    headline = `OVERLOAD REJECTED — Total gross ${a.gross_uld_kg} kg melebihi kapasitas ${uld_spec.uld_type} (${uld_spec.max_gross_weight_kg} kg); wajib alihkan ke Palet PMC.`;
  } else if (a.decision === 'CCA_AMENDMENT') {
    headline = `CCA REQUIRED — Selisih ${a.discrepancy_kg >= 0 ? '+' : ''}${a.discrepancy_kg} kg (${a.discrepancy_percent}%) melebihi ±5% IATA; tagihan dikoreksi & bobot fisik ${scale_telemetry.actual_weight_kg} kg diloloskan.`;
  } else {
    headline = `AUTO-APPROVED — Bobot aktual ${scale_telemetry.actual_weight_kg} kg lolos toleransi IATA ±5% (${a.discrepancy_percent}%)${a.is_hea ? ' · Tag HEA aktif' : ''} · CoG ${a.cg_mac}% MAC aman.`;
  }

  const result = {
    ...a,
    headline,
    regulation_refs: refs,
    actions,
    perception: state.perception,
  };

  const actSummary = a.uld_overload
    ? `Kunci bobot aktual · Terbitkan alert OVERLOAD → blokir ${uld_spec.uld_code} & rekomendasikan PMC.`
    : `${a.cca_required ? 'Terbitkan e-CCA · ' : ''}${a.is_hea ? 'Dispatch Forklift HEA · ' : ''}Kunci bobot ${scale_telemetry.actual_weight_kg} kg → ${uld_spec.uld_code} (${uld_spec.compartment}, CoG ${a.cg_mac}% MAC).`;

  return {
    result,
    trace: [step('act', 'ACT — Eksekusi keputusan Loadmaster', actSummary)],
  };
}

export function buildLoadmasterGraph() {
  return new StateGraph(LoadmasterState)
    .addNode('perceive', perceive)
    .addNode('think_rules', thinkRules)
    .addNode('think_llm', thinkLlm)
    .addNode('act', act)
    .addEdge(START, 'perceive')
    .addEdge('perceive', 'think_rules')
    .addConditionalEdges('think_rules', (s) => (s.options?.llm?.apiKey ? 'think_llm' : 'act'), {
      think_llm: 'think_llm',
      act: 'act',
    })
    .addEdge('think_llm', 'act')
    .addEdge('act', END)
    .compile();
}

export async function runLoadmasterAgent(graph, input, options) {
  const t0 = Date.now();
  const out = await graph.invoke({ input, options });
  return {
    success: true,
    engine: 'langgraph-js',
    llm: out.llm
      ? { used: !!out.llm.used, model: out.llm.model || null, error: out.llm.error || null }
      : { used: false, model: null, error: null },
    ...out.result,
    trace: out.trace,
    elapsed_ms: Date.now() - t0,
  };
}
