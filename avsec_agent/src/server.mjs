// avsec_agent/src/server.mjs
// Layanan HTTP lokal Multi-Agent LangGraph.js (AVSEC + Loadmaster).
//   GET  /health         → status layanan agen
//   POST /inspect        → { cargo:{...}, scenario:'CLEARED'|'SUSPECT', scan?:{...} } → AVSEC Agent (Tahap 4)
//   POST /evaluate-load  → { cargo:{...}, actual_weight_kg, uld:{...}, stage } → Loadmaster Agent (Tahap 5-6)
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildAvsecGraph, runAvsecAgent } from './graph.mjs';
import { buildLoadmasterGraph, runLoadmasterAgent } from './loadmaster_graph.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Muat .env sederhana (tanpa dependensi tambahan)
function loadEnv(file) {
  try {
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
      const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/i);
      if (m && !line.trim().startsWith('#') && process.env[m[1]] === undefined) {
        process.env[m[1]] = m[2].replace(/^['"]|['"]$/g, '');
      }
    }
  } catch { /* .env opsional */ }
}
loadEnv(path.join(__dirname, '..', '.env'));

const PORT = Number(process.env.AVSEC_AGENT_PORT || 8787);
const HOST = process.env.AVSEC_AGENT_HOST || '127.0.0.1';
const apiKey = (process.env.GEMINI_API_KEY || '').trim();
const options = {
  llm: {
    apiKey: apiKey && apiKey !== 'ISI_API_KEY_ANDA' ? apiKey : '',
    model: process.env.GEMINI_MODEL || 'gemini-2.5-flash',
    timeoutMs: Number(process.env.GEMINI_TIMEOUT_MS || 9000),
  },
};

const graph = buildAvsecGraph();
const loadmasterGraph = buildLoadmasterGraph();

function send(res, code, body) {
  const data = JSON.stringify(body);
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(data) });
  res.end(data);
}

async function readJsonBody(req) {
  const chunks = [];
  let size = 0;
  for await (const c of req) {
    size += c.length;
    if (size > 1_000_000) throw Object.assign(new Error('Payload terlalu besar'), { statusCode: 413 });
    chunks.push(c);
  }
  try {
    return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}');
  } catch {
    throw Object.assign(new Error('JSON tidak valid'), { statusCode: 400 });
  }
}

const server = http.createServer(async (req, res) => {
  try {
    if (req.method === 'GET' && req.url === '/health') {
      return send(res, 200, {
        ok: true,
        service: 'air-cargo-multi-agent',
        services: ['avsec-agent', 'loadmaster-agent'],
        engine: 'langgraph-js',
        llm_enabled: !!options.llm.apiKey,
        llm_model: options.llm.apiKey ? options.llm.model : null,
      });
    }
    if (req.method === 'POST' && req.url === '/inspect') {
      const input = await readJsonBody(req);
      if (!input.cargo || typeof input.cargo !== 'object') return send(res, 400, { success: false, message: 'Field "cargo" wajib ada' });
      input.scenario = input.scenario === 'SUSPECT' ? 'SUSPECT' : 'CLEARED';
      const result = await runAvsecAgent(graph, input, options);
      console.log(`[AVSEC ${new Date().toLocaleTimeString()}] ${input.cargo.awb || '-'} "${String(input.cargo.description || '').slice(0, 40)}" → ${result.decision} (risiko ${result.risk_score}, ${result.elapsed_ms} ms${result.llm.used ? ', +LLM' : ''})`);
      return send(res, 200, result);
    }
    if (req.method === 'POST' && req.url === '/evaluate-load') {
      const input = await readJsonBody(req);
      if (!input.cargo || typeof input.cargo !== 'object') return send(res, 400, { success: false, message: 'Field "cargo" wajib ada' });
      const result = await runLoadmasterAgent(loadmasterGraph, input, options);
      console.log(`[LOADMASTER ${new Date().toLocaleTimeString()}] ${input.cargo.awb || '-'} aktual=${result.perception?.scale_telemetry?.actual_weight_kg}kg → ${result.decision} (CoG ${result.cg_mac}% MAC, ${result.elapsed_ms} ms${result.llm.used ? ', +LLM' : ''})`);
      return send(res, 200, result);
    }
    send(res, 404, { success: false, message: 'Not found' });
  } catch (err) {
    if (err?.statusCode) return send(res, err.statusCode, { success: false, message: err.message });
    console.error('Agent error:', err);
    send(res, 500, { success: false, message: String(err?.message || err) });
  }
});

server.listen(PORT, HOST, () => {
  console.log(`✈  Air Cargo Multi-Agent Hub (LangGraph.js) aktif di http://${HOST}:${PORT}`);
  console.log(`   Endpoints: POST /inspect (AVSEC) · POST /evaluate-load (Loadmaster)`);
  console.log(`   LLM Gemini: ${options.llm.apiKey ? `AKTIF (${options.llm.model})` : 'nonaktif → mode aturan (rule-based)'}`);
});
