// avsec_agent/src/selftest.mjs — uji cepat agen tanpa HTTP & tanpa LLM
import { buildAvsecGraph, runAvsecAgent } from './graph.mjs';

const graph = buildAvsecGraph();
const opts = { llm: { apiKey: '', model: '', timeoutMs: 1000 } };

const cases = [
  { name: 'Garmen aman', cargo: { awb: '126-1', description: 'Garmen & Tekstil Ekspor Premium', commodity_type: 'GENERAL_CARGO' }, scenario: 'CLEARED', expect: 'CLEARED' },
  { name: 'Vaksin farmasi', cargo: { awb: '126-2', description: 'Vaksin & Peralatan Medis Biofarma', commodity_type: 'PHARMA' }, scenario: 'CLEARED', expect: 'CLEARED' },
  { name: 'Garmen + anomali tersembunyi (mismatch)', cargo: { awb: '126-3', description: 'Garmen & Tekstil Ekspor Premium', commodity_type: 'GENERAL_CARGO' }, scenario: 'SUSPECT', expect: 'SUSPECT', mismatch: true },
  { name: 'Manifes menyebut baterai lithium (skenario CLEARED)', cargo: { awb: '126-4', description: 'Baterai Lithium & Aerosol Kimia DG', commodity_type: 'DANGEROUS_GOODS' }, scenario: 'CLEARED', expect: 'SUSPECT' },
  { name: 'Suku cadang berat', cargo: { awb: '126-5', description: 'Suku Cadang Kompresor Turbin Berat', commodity_type: 'GENERAL_CARGO' }, scenario: 'CLEARED', expect: 'CLEARED' },
  { name: 'Kata "gasket" tidak boleh match "gas"', cargo: { awb: '126-6', description: 'Gasket karet mesin', commodity_type: 'GENERAL_CARGO' }, scenario: 'CLEARED', expect: 'CLEARED' },
];

let failed = 0;
for (const c of cases) {
  const r = await runAvsecAgent(graph, { cargo: c.cargo, scenario: c.scenario }, opts);
  const ok = r.decision === c.expect && (c.mismatch === undefined || r.mismatch === c.mismatch);
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${c.name} → ${r.decision} (risiko ${r.risk_score}, mismatch=${r.mismatch}) | ${r.headline}`);
}
if (failed) { console.error(`\n${failed} uji gagal`); process.exit(1); }
console.log('\nSemua uji lulus ✔');
