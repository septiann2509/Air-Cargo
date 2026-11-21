// avsec_agent/src/loadmaster_selftest.mjs — uji cepat Loadmaster Agent tanpa HTTP & tanpa LLM
import { buildLoadmasterGraph, runLoadmasterAgent } from './loadmaster_graph.mjs';

const graph = buildLoadmasterGraph();
const opts = { llm: { apiKey: '', model: '', timeoutMs: 1000 } };

const cases = [
  {
    name: '150 kg deklarasi vs 156 kg aktual (+4.0%, lolos ±5% + tag HEA)',
    input: {
      cargo: { awb: '126-1001', description: 'Kompresor Industri', commodity_type: 'GENERAL_CARGO', quantity: 1, declared_weight_kg: 150 },
      actual_weight_kg: 156,
      uld: { uld_code: 'AKE-12345-GA', uld_type: 'AKE', tare_weight_kg: 75, max_weight_kg: 1588 },
      stage: 5,
    },
    expect: 'AUTO_APPROVED',
    expectHea: true,
    expectCca: false,
  },
  {
    name: '150 kg deklarasi vs 162 kg aktual (+8.0%, CCA Required)',
    input: {
      cargo: { awb: '126-1002', description: 'Tekstil Ekspor', commodity_type: 'GENERAL_CARGO', quantity: 5, declared_weight_kg: 150 },
      actual_weight_kg: 162,
      uld: { uld_code: 'AKE-12345-GA', uld_type: 'AKE', tare_weight_kg: 75, max_weight_kg: 1588 },
      stage: 5,
    },
    expect: 'CCA_AMENDMENT',
    expectCca: true,
  },
  {
    name: 'Kargo 1550 kg + Tare 75 kg = 1625 kg (> 1588 kg batas AKE → Overload Rejected)',
    input: {
      cargo: { awb: '126-1003', description: 'Mesin Berat', commodity_type: 'GENERAL_CARGO', quantity: 2, declared_weight_kg: 1550 },
      actual_weight_kg: 1550,
      uld: { uld_code: 'AKE-12345-GA', uld_type: 'AKE', tare_weight_kg: 75, max_weight_kg: 1588 },
      stage: 6,
    },
    expect: 'OVERLOAD_REJECTED',
    expectOverload: true,
  },
];

let failed = 0;
for (const c of cases) {
  const r = await runLoadmasterAgent(graph, c.input, opts);
  const ok =
    r.decision === c.expect &&
    (c.expectHea === undefined || r.is_hea === c.expectHea) &&
    (c.expectCca === undefined || r.cca_required === c.expectCca) &&
    (c.expectOverload === undefined || r.uld_overload === c.expectOverload);
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${c.name} → ${r.decision} | ${r.headline}`);
}
if (failed) {
  console.error(`\n${failed} uji Loadmaster gagal`);
  process.exit(1);
}
console.log('\nSemua uji Loadmaster Agent lulus ✔');
