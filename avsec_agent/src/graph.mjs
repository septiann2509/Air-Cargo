// avsec_agent/src/graph.mjs
// Autonomous AVSEC Security Agent — LangGraph.js
//
//   START → perceive → think_rules ─┬─(LLM aktif)──→ think_llm ─┐
//                                   └─(tanpa LLM)───────────────┴→ act → END
//
// perceive    : membaca dokumen manifes + citra/fitur X-Ray dual-energy
// think_rules : mencocokkan terhadap basis aturan DGR / ICAO Annex 17 (deterministik, dapat diaudit)
// think_llm   : (opsional) Gemini memberi penalaran kedua. Prinsip keselamatan: LLM hanya boleh
//               MENAIKKAN kewaspadaan (CLEARED → SUSPECT), tidak pernah menurunkan temuan aturan.
// act         : menyusun perintah aktuator (diverter arm, sirine, kunci status CMS, notifikasi supervisor)

import { StateGraph, Annotation, START, END } from '@langchain/langgraph';
import { DG_RULES, BENIGN_HINTS, XRAY_SIGNATURES } from './knowledge.mjs';

const SCANNER_NAME = 'Dual-View High-Energy X-Ray (180x180cm, 320kV)';

// ─── Utilitas ────────────────────────────────────────────────────────────────
function hashString(str) {
  let h = 1779033703 ^ str.length;
  for (let i = 0; i < str.length; i++) {
    h = Math.imul(h ^ str.charCodeAt(i), 3432918353);
    h = (h << 13) | (h >>> 19);
  }
  return h >>> 0;
}
function mulberry32(seed) {
  let a = seed >>> 0;
  return function () {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
const round = (n, d = 2) => Math.round(n * 10 ** d) / 10 ** d;
const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

function keywordMatch(text, keyword) {
  const kw = keyword.trim();
  if (!kw) return false;
  return new RegExp(`(?<![a-z0-9])${escapeRe(kw)}(?![a-z0-9])`, 'i').test(text);
}

export function matchManifestRules(text) {
  const t = String(text || '').toLowerCase();
  const hits = [];
  for (const rule of DG_RULES) {
    const matched = rule.keywords.filter((k) => keywordMatch(t, k));
    if (matched.length) hits.push({ rule, matched });
  }
  return hits;
}

function isBenignDescription(text) {
  const t = String(text || '').toLowerCase();
  return BENIGN_HINTS.some((k) => keywordMatch(t, k));
}

// ─── Pembangkit data X-Ray sintetis (menggantikan keluaran pemindai fisik) ────
function synthesizeXrayScan(cargo, scenario, manifestHits, rng) {
  const objects = [];
  const pickSignature = () => {
    if (manifestHits.length) return manifestHits[0].rule.xraySignature;
    const pool = ['ARRAY_OF_DENSE_CELLS', 'PRESSURIZED_CYLINDER', 'LIQUID_CONTAINER_DENSE'];
    return pool[Math.floor(rng() * pool.length)];
  };

  // Objek dasar: kemasan/isi umum
  const baseSig = rng() > 0.6 ? 'NORMAL_MIXED_GOODS' : 'NORMAL_ORGANIC_TEXTILE';
  const base = XRAY_SIGNATURES[baseSig];
  objects.push({
    id: 'OBJ-01',
    signature: baseSig,
    type: base.objectType,
    z_eff: round(base.zEff + (rng() - 0.5) * 0.8),
    density_gcm3: round(base.densityGcm3 + (rng() - 0.5) * 0.08),
    color_class: base.colorClass,
    bbox_pct: { x: 4, y: 8, w: 92, h: 84 },
    confidence: round(0.9 + rng() * 0.08),
  });

  // Anomali tersembunyi / sesuai deklarasi (jika skenario SUSPECT atau manifes mengandung DG)
  const hasDeclaredDG = manifestHits.length > 0 || /DANGER/i.test(cargo.commodity_type || '');
  if (scenario === 'SUSPECT' || hasDeclaredDG) {
    const sig = pickSignature();
    const s = XRAY_SIGNATURES[sig];
    objects.push({
      id: 'OBJ-02',
      signature: sig,
      type: s.objectType,
      z_eff: round(s.zEff + (rng() - 0.5) * 1.2),
      density_gcm3: round(s.densityGcm3 + (rng() - 0.5) * 0.3),
      color_class: s.colorClass,
      bbox_pct: {
        x: Math.round(18 + rng() * 40),
        y: Math.round(20 + rng() * 35),
        w: Math.round(18 + rng() * 14),
        h: Math.round(16 + rng() * 14),
      },
      confidence: round(0.82 + rng() * 0.16),
    });
  }
  return objects;
}

function summarizeScan(objects) {
  const meanZ = objects.reduce((a, o) => a + o.z_eff, 0) / objects.length;
  const maxDensity = Math.max(...objects.map((o) => o.density_gcm3));
  const hist = {};
  for (const o of objects) hist[o.color_class] = (hist[o.color_class] || 0) + 1;
  return {
    scanner: SCANNER_NAME,
    views: 2,
    object_count: objects.length,
    mean_z_eff: round(meanZ, 1),
    max_density_gcm3: round(maxDensity),
    color_classes: hist,
  };
}

// ─── State graph ─────────────────────────────────────────────────────────────
const concat = (a, b) => (a || []).concat(b || []);
const State = Annotation.Root({
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
  const { cargo = {}, scenario = 'CLEARED', scan } = state.input;
  const text = `${cargo.description || ''} ${cargo.commodity_type || ''}`;
  const manifestHits = matchManifestRules(text);
  const seed = hashString(`${cargo.awb || ''}|${cargo.description || ''}|${scenario}`);
  const rng = mulberry32(seed);

  const objects = Array.isArray(scan?.detected_objects) && scan.detected_objects.length
    ? scan.detected_objects
    : synthesizeXrayScan(cargo, scenario, manifestHits, rng);
  const scanSummary = summarizeScan(objects);

  const perception = {
    manifest: {
      awb: cargo.awb || '-',
      description: cargo.description || '-',
      commodity_type: cargo.commodity_type || 'GENERAL_CARGO',
      quantity: cargo.quantity ?? null,
      declared_weight_kg: cargo.declared_weight_kg ?? null,
      shipper: cargo.shipper || null,
      consignee: cargo.consignee || null,
      benign_description: isBenignDescription(cargo.description),
      keyword_hits: manifestHits.map((h) => ({ rule_id: h.rule.id, matched: h.matched })),
    },
    xray: { summary: scanSummary, detected_objects: objects },
  };

  return {
    perception,
    trace: [
      step(
        'perceive',
        'PERCEIVE — Membaca manifes & citra X-Ray',
        `Manifes: "${perception.manifest.description}" (${perception.manifest.commodity_type}). ` +
          `X-Ray dual-view: ${scanSummary.object_count} objek, Z-eff rata-rata ${scanSummary.mean_z_eff}, ` +
          `densitas maks ${scanSummary.max_density_gcm3} g/cm³. Kata kunci DG pada manifes: ${manifestHits.length}.`,
      ),
    ],
  };
}

// ═══ NODE 2: THINK (aturan deterministik) ═══
async function thinkRules(state) {
  const { manifest, xray } = state.perception;
  const findings = [];

  // a) Temuan dari manifes
  for (const h of manifest.keyword_hits) {
    const rule = DG_RULES.find((r) => r.id === h.rule_id);
    findings.push({
      source: 'MANIFEST',
      rule_id: rule.id,
      label: rule.label,
      un: rule.un,
      dg_class: rule.dgClass,
      severity: rule.severity,
      regulation: rule.regulation,
      detail: `Manifes menyebut "${h.matched.join('", "')}" → terindikasi ${rule.label} (${rule.un}, Kelas ${rule.dgClass}).`,
    });
  }
  if (/DANGER/i.test(manifest.commodity_type) && !findings.length) {
    findings.push({
      source: 'MANIFEST',
      rule_id: 'DECLARED_DG_GENERIC',
      label: 'Dangerous Goods (dideklarasikan)',
      un: '-',
      dg_class: '-',
      severity: 0.7,
      regulation: 'IATA DGR Sec. 8 (Shipper’s Declaration) · ICAO Annex 18',
      detail: 'Jenis komoditas dideklarasikan sebagai Dangerous Goods; wajib melalui DG acceptance check, tidak boleh lewat jalur kargo umum.',
    });
  }

  // b) Temuan dari citra X-Ray
  const anomalies = xray.detected_objects.filter((o) => !String(o.signature).startsWith('NORMAL_'));
  for (const o of anomalies) {
    const sig = XRAY_SIGNATURES[o.signature];
    const candidateIds = sig?.dgClassHint || [];
    const candidate = DG_RULES.find((r) => candidateIds.includes(r.id)) || null;
    findings.push({
      source: 'XRAY',
      rule_id: candidate ? candidate.id : 'UNKNOWN_ANOMALY',
      label: candidate ? candidate.label : 'Anomali tak teridentifikasi',
      un: candidate ? candidate.un : '-',
      dg_class: candidate ? candidate.dgClass : '-',
      severity: candidate ? candidate.severity * (o.confidence ?? 0.85) : 0.6,
      regulation: candidate ? candidate.regulation : 'ICAO Annex 17 §4.5 (screening anomaly)',
      object_id: o.id,
      bbox_pct: o.bbox_pct,
      detail:
        `${o.id}: ${o.type} — Z-eff ${o.z_eff}, densitas ${o.density_gcm3} g/cm³, warna ${o.color_class}, ` +
        `keyakinan ${(Number(o.confidence) * 100).toFixed(0)}%` +
        (candidate ? ` → pola konsisten dengan ${candidate.label} (${candidate.un}).` : '.'),
    });
  }

  const declaredDG = manifest.keyword_hits.length > 0 || /DANGER/i.test(manifest.commodity_type);
  const undeclaredAnomaly = anomalies.length > 0 && !declaredDG;
  const mismatch = undeclaredAnomaly && manifest.benign_description;

  // c) Skor risiko yang dapat dijelaskan
  const xrayScore = findings.filter((f) => f.source === 'XRAY').reduce((m, f) => Math.max(m, f.severity), 0);
  const manifestScore = findings.filter((f) => f.source === 'MANIFEST').reduce((m, f) => Math.max(m, f.severity * 0.85), 0);
  const mismatchBonus = undeclaredAnomaly ? (mismatch ? 0.25 : 0.15) : 0;
  const risk = Math.min(1, Math.max(xrayScore, manifestScore) + mismatchBonus);

  const suspect = findings.length > 0; // Prinsip AVSEC: setiap indikator DG ⇒ tahan & periksa sekunder
  const decision = suspect ? 'SUSPECT' : 'CLEARED';
  const confidence = suspect ? Math.min(0.99, 0.6 + 0.4 * risk) : Math.min(0.97, 0.88 + 0.09 * (1 - risk));

  const reasoning = [];
  if (!findings.length) {
    reasoning.push('Tidak ada kata kunci Dangerous Goods pada manifes.');
    reasoning.push(`Seluruh ${xray.detected_objects.length} objek pada citra X-Ray berpola organik/campuran normal (Z-eff rata-rata ${xray.summary.mean_z_eff}).`);
    reasoning.push('Deklarasi manifes konsisten dengan hasil pemindaian → layak dirilis dan diterbitkan CSD.');
  } else {
    if (mismatch) reasoning.push('⚠ KETIDAKSESUAIAN DEKLARASI: manifes menyebut barang umum/aman, tetapi X-Ray menemukan anomali bersignatur Dangerous Goods (indikasi barang tidak dideklarasikan / penyembunyian).');
    else if (undeclaredAnomaly) reasoning.push('⚠ Anomali pada citra X-Ray tidak tercantum pada manifes (undeclared DG).');
    findings.forEach((f) => reasoning.push(f.detail));
    reasoning.push(`Skor risiko ${(risk * 100).toFixed(0)}% ≥ ambang karantina → kargo ditahan untuk pemeriksaan sekunder (ETD & hand search) sesuai ICAO Annex 17.`);
  }

  const assessment = {
    decision,
    risk_score: round(risk),
    confidence: round(confidence),
    mismatch,
    declared_dg: declaredDG,
    findings,
    reasoning,
  };

  return {
    assessment,
    trace: [
      step(
        'think_rules',
        'THINK — Mencocokkan dengan aturan DGR / ICAO Annex 17',
        `${findings.length} temuan, risiko ${(risk * 100).toFixed(0)}%, ` +
          `${mismatch ? 'TERDETEKSI ketidaksesuaian deklarasi vs X-Ray. ' : ''}Keputusan sementara: ${decision}.`,
      ),
    ],
  };
}

// ═══ NODE 3 (opsional): THINK — Penalaran kedua oleh Gemini ═══
async function thinkLlm(state) {
  const { apiKey, model, timeoutMs } = state.options.llm;
  const { perception, assessment } = state;

  const prompt = [
    'Anda adalah Petugas Keamanan Penerbangan (AVSEC) senior yang menilai kargo udara sesuai IATA DGR dan ICAO Annex 17.',
    'Berikut data manifes dan hasil X-Ray dual-energy, beserta penilaian awal berbasis aturan.',
    'Beri penilaian independen. Jawab HANYA JSON dengan skema:',
    '{"verdict":"CLEARED"|"SUSPECT","confidence":0.0-1.0,"explanation":"2-3 kalimat Bahasa Indonesia","extra_concerns":["..."]}',
    '',
    'DATA:',
    JSON.stringify({ manifest: perception.manifest, xray: perception.xray, rule_assessment: { decision: assessment.decision, risk: assessment.risk_score, findings: assessment.findings.map((f) => f.detail) } }),
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
      verdict: parsed.verdict === 'SUSPECT' ? 'SUSPECT' : 'CLEARED',
      confidence: Math.max(0, Math.min(1, Number(parsed.confidence) || 0)),
      explanation: String(parsed.explanation || '').slice(0, 600),
      extra_concerns: Array.isArray(parsed.extra_concerns) ? parsed.extra_concerns.slice(0, 5).map(String) : [],
    };
  } catch (err) {
    llm = { used: false, error: String(err?.message || err).slice(0, 160) };
  }

  const updated = { ...assessment, reasoning: [...assessment.reasoning] };
  let note;
  if (llm.used) {
    if (llm.verdict === 'SUSPECT' && updated.decision === 'CLEARED' && llm.confidence >= 0.8) {
      updated.decision = 'SUSPECT';
      updated.confidence = round(Math.max(updated.confidence, llm.confidence));
      updated.reasoning.push(`LLM (${llm.model}) menaikkan status ke SUSPECT: ${llm.explanation}`);
      note = `Gemini mengeskalasi ke SUSPECT (keyakinan ${(llm.confidence * 100).toFixed(0)}%).`;
    } else {
      updated.reasoning.push(`Penalaran LLM (${llm.model}): ${llm.explanation}`);
      note = `Gemini sependapat/tidak menaikkan status (${llm.verdict}, ${(llm.confidence * 100).toFixed(0)}%).`;
    }
    llm.extra_concerns.forEach((c) => updated.reasoning.push(`Catatan tambahan LLM: ${c}`));
  } else {
    note = `LLM tidak tersedia (${llm.error || 'dinonaktifkan'}); memakai keputusan aturan saja.`;
  }

  return { llm, assessment: updated, trace: [step('think_llm', 'THINK — Penalaran kedua (Gemini)', note)] };
}

// ═══ NODE 4: ACT ═══
async function act(state) {
  const a = state.assessment;
  const suspect = a.decision === 'SUSPECT';
  const refs = [...new Set(a.findings.map((f) => f.regulation))];
  if (!refs.length) refs.push('ICAO Annex 17 §4.5 (cargo screening) · IATA DGR Sec. 8');

  const actions = suspect
    ? [
        { device: 'DIVERTER_ARM_01', command: 'EXTEND', target: 'QUARANTINE_ZONE_3B', description: 'Lengan pemilah pneumatik mendorong kargo ke konveyor isolasi Ruang Karantina (Zone 3B).' },
        { device: 'ALARM_SIREN_RED', command: 'ON', target: 'ZONE_3B', description: 'Menyalakan sirine merah & strobe di Ruang Karantina.' },
        { device: 'CMS_STATUS', command: 'LOCK', target: 'SUSPECT', description: 'Mengunci status kargo menjadi SUSPECT di CMS; dilarang masuk ULD / pesawat.' },
        { device: 'CSD_ISSUER', command: 'WITHHOLD', target: null, description: 'Sertifikat CSD (Consignment Security Declaration) tidak diterbitkan.' },
        { device: 'AVSEC_SUPERVISOR', command: 'NOTIFY', target: 'HUMAN_REVIEW', description: 'Notifikasi ke supervisor AVSEC untuk pemeriksaan sekunder (ETD & hand search) — human-in-the-loop.' },
      ]
    : [
        { device: 'DIVERTER_ARM_01', command: 'RETRACT', target: 'CENTRAL_STORAGE', description: 'Lengan pemilah tetap tertarik; kargo lurus ke Central Storage.' },
        { device: 'ALARM_SIREN_RED', command: 'OFF', target: 'ZONE_3B', description: 'Sirine tetap mati.' },
        { device: 'CMS_STATUS', command: 'SET', target: 'CLEARED', description: 'Status kargo ditetapkan CLEARED di CMS.' },
        { device: 'CSD_ISSUER', command: 'ISSUE', target: null, description: 'Menerbitkan sertifikat CSD.' },
      ];

  const top = a.findings.find((f) => f.source === 'XRAY') || a.findings[0];
  const headline = suspect
    ? a.mismatch
      ? `SUSPECT — deklarasi tidak sesuai: X-Ray menemukan ${top?.label || 'anomali'} pada kargo yang dideklarasikan "${state.perception.manifest.description}".`
      : `SUSPECT — ${top?.label || 'anomali'} terdeteksi${top?.un && top.un !== '-' ? ` (${top.un})` : ''}.`
    : 'CLEARED — tidak ditemukan anomali; manifes konsisten dengan citra X-Ray.';

  const result = {
    decision: a.decision,
    risk_score: a.risk_score,
    confidence: a.confidence,
    mismatch: a.mismatch,
    headline,
    reasoning: a.reasoning,
    findings: a.findings,
    regulation_refs: refs,
    actions,
    human_review_required: suspect,
    perception: state.perception,
  };

  return {
    result,
    trace: [
      step(
        'act',
        'ACT — Mengeksekusi perintah aktuator',
        suspect
          ? 'Diverter arm EXTEND → Zone 3B · Sirine merah ON · Status dikunci SUSPECT · CSD ditahan · Supervisor dinotifikasi.'
          : 'Diverter arm RETRACT → Central Storage · Status CLEARED · CSD diterbitkan.',
      ),
    ],
  };
}

// ─── Rakit graf ──────────────────────────────────────────────────────────────
export function buildAvsecGraph() {
  return new StateGraph(State)
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

export async function runAvsecAgent(graph, input, options) {
  const t0 = Date.now();
  const out = await graph.invoke({ input, options });
  return {
    success: true,
    engine: 'langgraph-js',
    llm: out.llm ? { used: !!out.llm.used, model: out.llm.model || null, error: out.llm.error || null } : { used: false, model: null, error: null },
    ...out.result,
    trace: out.trace,
    elapsed_ms: Date.now() - t0,
  };
}
