// avsec_agent/src/server.mjs
// Layanan HTTP lokal untuk AVSEC Agent. Dipanggil oleh PHP (api/avsec_agent.php).
//   GET  /health   → status layanan
//   POST /inspect  → { cargo:{...}, scenario:'CLEARED'|'SUSPECT', scan?:{...} } → keputusan agen
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildAvsecGraph, runAvsecAgent } from './graph.mjs';

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

function send(res, code, body) {
  const data = JSON.stringify(body);
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(data) });
  res.end(data);
}

const server = http.createServer(async (req, res) => {
  try {
    if (req.method === 'GET' && req.url === '/health') {
      return send(res, 200, { ok: true, service: 'avsec-agent', engine: 'langgraph-js', llm_enabled: !!options.llm.apiKey, llm_model: options.llm.apiKey ? options.llm.model : null });
    }
    if (req.method === 'POST' && req.url === '/inspect') {
      const chunks = [];
      let size = 0;
      for await (const c of req) {
        size += c.length;
        if (size > 1_000_000) return send(res, 413, { success: false, message: 'Payload terlalu besar' });
        chunks.push(c);
      }
      let input;
      try { input = JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}'); }
      catch { return send(res, 400, { success: false, message: 'JSON tidak valid' }); }
      if (!input.cargo || typeof input.cargo !== 'object') return send(res, 400, { success: false, message: 'Field "cargo" wajib ada' });
      input.scenario = input.scenario === 'SUSPECT' ? 'SUSPECT' : 'CLEARED';
      const result = await runAvsecAgent(graph, input, options);
      console.log(`[${new Date().toLocaleTimeString()}] ${input.cargo.awb || '-'} "${String(input.cargo.description || '').slice(0, 40)}" → ${result.decision} (risiko ${result.risk_score}, ${result.elapsed_ms} ms${result.llm.used ? ', +LLM' : ''})`);
      return send(res, 200, result);
    }
    send(res, 404, { success: false, message: 'Not found' });
  } catch (err) {
    console.error('Agent error:', err);
    send(res, 500, { success: false, message: String(err?.message || err) });
  }
});

server.listen(PORT, HOST, () => {
  console.log(`✈  AVSEC Agent (LangGraph.js) aktif di http://${HOST}:${PORT}`);
  console.log(`   LLM Gemini: ${options.llm.apiKey ? `AKTIF (${options.llm.model})` : 'nonaktif → mode aturan (rule-based)'}`);
});
