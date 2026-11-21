<?php
// config/avsec_agent.php
// Jembatan PHP → AVSEC Agent (LangGraph.js, layanan Node di 127.0.0.1:8787)
// + fallback aturan sederhana bila layanan agen belum dijalankan, + penyimpanan jejak audit keputusan.

if (!defined('AVSEC_AGENT_URL')) {
    define('AVSEC_AGENT_URL', 'http://127.0.0.1:8787');
}

/**
 * Panggil agen. Selalu mengembalikan array hasil (tidak pernah melempar exception).
 * @param array $cargo     data manifes (awb, description, commodity_type, quantity, declared_weight_kg, ...)
 * @param string $scenario 'CLEARED'|'SUSPECT' → isi fisik kargo yang "dilihat" pemindai X-Ray simulasi
 */
function avsecAgentInspect(array $cargo, string $scenario = 'CLEARED'): array {
    $scenario = ($scenario === 'SUSPECT') ? 'SUSPECT' : 'CLEARED';
    $body = json_encode(['cargo' => $cargo, 'scenario' => $scenario], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nConnection: close\r\n",
            'content'       => $body,
            'timeout'       => 14,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents(AVSEC_AGENT_URL . '/inspect', false, $ctx);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded['success']) && isset($decoded['decision'])) {
            return $decoded;
        }
    }
    return avsecFallbackInspect($cargo, $scenario);
}

/** Status layanan agen (untuk badge di UI). */
function avsecAgentHealth(): array {
    $ctx = stream_context_create(['http' => ['timeout' => 1.5, 'ignore_errors' => true]]);
    $raw = @file_get_contents(AVSEC_AGENT_URL . '/health', false, $ctx);
    $d = $raw ? json_decode($raw, true) : null;
    if (is_array($d) && !empty($d['ok'])) {
        return ['online' => true, 'engine' => $d['engine'] ?? 'langgraph-js', 'llm_enabled' => !empty($d['llm_enabled']), 'llm_model' => $d['llm_model'] ?? null];
    }
    return ['online' => false, 'engine' => 'php-fallback', 'llm_enabled' => false, 'llm_model' => null];
}

/** Fallback minimal (tanpa LangGraph) agar demo tidak pernah gagal. */
function avsecFallbackInspect(array $cargo, string $scenario): array {
    $text = strtolower(($cargo['description'] ?? '') . ' ' . ($cargo['commodity_type'] ?? ''));
    $keywords = ['lithium', 'li-ion', 'baterai', 'battery', 'power bank', 'aerosol', 'spray', 'semprotan', 'tabung', 'gas', 'lpg',
                 'bensin', 'thinner', 'alkohol', 'petasan', 'kembang api', 'amunisi', 'korosif', 'asam', 'radioaktif', 'peroksida', 'dangerous'];
    $hits = [];
    foreach ($keywords as $k) {
        if (preg_match('/(?<![a-z0-9])' . preg_quote($k, '/') . '(?![a-z0-9])/i', $text)) $hits[] = $k;
    }
    $suspect = $scenario === 'SUSPECT' || count($hits) > 0;
    $now = date('c');
    $reasoning = $suspect
        ? ['[Fallback] Layanan LangGraph tidak aktif; memakai aturan kata kunci.',
           $hits ? 'Kata kunci DG pada manifes: ' . implode(', ', $hits) . '.' : 'Skenario X-Ray menandai anomali densitas tinggi.',
           'Kargo ditahan untuk pemeriksaan sekunder (ICAO Annex 17).']
        : ['[Fallback] Layanan LangGraph tidak aktif; tidak ada indikator DG pada manifes.'];
    return [
        'success' => true,
        'engine' => 'php-fallback',
        'llm' => ['used' => false, 'model' => null, 'error' => 'Layanan avsec_agent tidak berjalan'],
        'decision' => $suspect ? 'SUSPECT' : 'CLEARED',
        'risk_score' => $suspect ? 0.85 : 0.05,
        'confidence' => $suspect ? 0.75 : 0.8,
        'mismatch' => false,
        'headline' => $suspect ? 'SUSPECT — indikator Dangerous Goods terdeteksi (mode fallback).' : 'CLEARED — tidak ada indikator DG (mode fallback).',
        'reasoning' => $reasoning,
        'findings' => [],
        'regulation_refs' => ['ICAO Annex 17 §4.5 · IATA DGR Sec. 8'],
        'actions' => $suspect
            ? [['device' => 'DIVERTER_ARM_01', 'command' => 'EXTEND', 'target' => 'QUARANTINE_ZONE_3B', 'description' => 'Diverter mendorong kargo ke Zone 3B.'],
               ['device' => 'ALARM_SIREN_RED', 'command' => 'ON', 'target' => 'ZONE_3B', 'description' => 'Sirine merah menyala.'],
               ['device' => 'CMS_STATUS', 'command' => 'LOCK', 'target' => 'SUSPECT', 'description' => 'Status dikunci SUSPECT.']]
            : [['device' => 'DIVERTER_ARM_01', 'command' => 'RETRACT', 'target' => 'CENTRAL_STORAGE', 'description' => 'Lurus ke Central Storage.'],
               ['device' => 'CMS_STATUS', 'command' => 'SET', 'target' => 'CLEARED', 'description' => 'Status CLEARED.']],
        'human_review_required' => $suspect,
        'perception' => ['manifest' => ['description' => $cargo['description'] ?? '-', 'commodity_type' => $cargo['commodity_type'] ?? '-'], 'xray' => ['summary' => ['object_count' => 0], 'detected_objects' => []]],
        'trace' => [
            ['node' => 'perceive', 'title' => 'PERCEIVE — Membaca manifes', 'detail' => 'Fallback PHP (tanpa citra X-Ray terstruktur).', 'ts' => $now],
            ['node' => 'think_rules', 'title' => 'THINK — Aturan kata kunci', 'detail' => $suspect ? 'Indikator DG ditemukan.' : 'Tidak ada indikator.', 'ts' => $now],
            ['node' => 'act', 'title' => 'ACT — Perintah aktuator', 'detail' => $suspect ? 'Diverter EXTEND → 3B, sirine ON, status SUSPECT.' : 'Diverter RETRACT, status CLEARED.', 'ts' => $now],
        ],
        'elapsed_ms' => 0,
    ];
}

/** Buat tabel jejak audit keputusan agen bila belum ada. */
function ensureAvsecDecisionTable(PDO $db): void {
    // DDL memicu implicit commit di MySQL: panggil SEBELUM beginTransaction(); hanya dijalankan sekali per request.
    static $done = false;
    if ($done) return;
    $done = true;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `avsec_agent_decisions` (
            `id` VARCHAR(50) PRIMARY KEY,
            `cargo_id` VARCHAR(50) NOT NULL,
            `decision` ENUM('CLEARED','SUSPECT') NOT NULL,
            `risk_score` DECIMAL(4,2) NOT NULL DEFAULT 0,
            `confidence` DECIMAL(4,2) NOT NULL DEFAULT 0,
            `engine` VARCHAR(40) NOT NULL DEFAULT 'langgraph-js',
            `llm_model` VARCHAR(60) NULL,
            `mismatch` TINYINT(1) NOT NULL DEFAULT 0,
            `headline` VARCHAR(500) NULL,
            `result_json` LONGTEXT NULL COMMENT 'Hasil lengkap: reasoning, findings, actions, trace',
            `created_at` DATETIME NOT NULL,
            INDEX (`cargo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function saveAvsecDecision(PDO $db, string $cargoId, array $r): void {
    ensureAvsecDecisionTable($db);
    $db->prepare("DELETE FROM avsec_agent_decisions WHERE cargo_id = ?")->execute([$cargoId]);
    $db->prepare("
        INSERT INTO avsec_agent_decisions (id, cargo_id, decision, risk_score, confidence, engine, llm_model, mismatch, headline, result_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        'AGT-' . uniqid(),
        $cargoId,
        $r['decision'],
        (float)($r['risk_score'] ?? 0),
        (float)($r['confidence'] ?? 0),
        substr((string)($r['engine'] ?? 'langgraph-js'), 0, 40),
        $r['llm']['model'] ?? null,
        !empty($r['mismatch']) ? 1 : 0,
        mb_substr((string)($r['headline'] ?? ''), 0, 500),
        json_encode($r, JSON_UNESCAPED_UNICODE),
    ]);
}
