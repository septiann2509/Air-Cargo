# AVSEC Agent — LangGraph.js

Agen keamanan penerbangan otonom (Tahap 4 X-Ray AVSEC & Karantina Zone 3B).

```
START → perceive → think_rules ─┬─(ada GEMINI_API_KEY)→ think_llm ─┐
                                └─(tanpa key)──────────────────────┴→ act → END
```

| Node | Tugas |
|---|---|
| `perceive` | Membaca manifes + fitur citra X-Ray dual-energy (Z-eff, densitas, objek terdeteksi) |
| `think_rules` | Mencocokkan dengan basis aturan IATA DGR / ICAO Annex 17 (`src/knowledge.mjs`), menghitung skor risiko, mendeteksi **ketidaksesuaian deklarasi** |
| `think_llm` | (Opsional) Gemini memberi penalaran kedua. LLM hanya boleh **menaikkan** CLEARED → SUSPECT, tidak pernah menurunkan temuan aturan |
| `act` | Menyusun perintah aktuator: diverter arm, sirine merah, kunci status CMS, tahan CSD, notifikasi supervisor |

## Menjalankan
1. `cd avsec_agent` lalu `npm install` (sekali saja).
2. (Opsional) salin `.env.example` → `.env`, isi `GEMINI_API_KEY`.
3. Jalankan `start-agent.bat` (atau `node src/server.mjs`). Layanan aktif di `http://127.0.0.1:8787`.
4. Buka `simulation_live.php`, jalankan simulasi. Panel **AI AGENT AVSEC** muncul saat kargo masuk terowongan X-Ray.

Uji cepat tanpa HTTP: `npm test`.

Jika layanan tidak dijalankan, PHP otomatis memakai **fallback aturan kata kunci** (`config/avsec_agent.php`) sehingga demo tetap berjalan.

## Endpoint
- `GET  /health`
- `POST /inspect` — `{ "cargo": {awb, description, commodity_type, ...}, "scenario": "CLEARED|SUSPECT" }` (AVSEC Tahap 4)
- `POST /evaluate-load` — `{ "cargo": {awb, description, commodity_type, quantity, declared_weight_kg}, "actual_weight_kg": 156, "uld": {...}, "stage": 5 }` (Loadmaster Tahap 5–6)

## Alur integrasi
- AVSEC: `simulation_live.php` / `simulation.php` → `api/simulate.php` → `config/avsec_agent.php` → `POST :8787/inspect` → `security_checks` + `avsec_agent_decisions`.
- Loadmaster: `simulation_live.php` / `simulation.php` → `api/simulate.php` → `config/loadmaster_agent.php` → `POST :8787/evaluate-load` → `weight_records` + `loadmaster_agent_decisions`.
