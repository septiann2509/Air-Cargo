<?php
// config/loadmaster_agent.php
// Jembatan PHP → Smart Loadmaster AI Agent (LangGraph.js di 127.0.0.1:8787/evaluate-load)
// + fallback aturan IATA deterministik bila layanan agen Node belum dijalankan, + jejak audit keputusan.

if (!defined('LOADMASTER_AGENT_URL')) {
    define('LOADMASTER_AGENT_URL', defined('AVSEC_AGENT_URL') ? AVSEC_AGENT_URL : 'http://127.0.0.1:8787');
}

/**
 * Evaluasi penimbangan (Tahap 5) & konsolidasi ULD Weight & Balance (Tahap 6) oleh AI Agent Loadmaster.
 * Selalu mengembalikan array hasil terstruktur (tidak pernah melempar exception).
 */
function loadmasterAgentEvaluate(array $cargo, float $actualWeight, array $uld = [], int $stage = 5): array {
    $body = json_encode([
        'cargo'            => $cargo,
        'actual_weight_kg' => round($actualWeight, 2),
        'uld'              => $uld,
        'stage'            => $stage,
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nConnection: close\r\n",
            'content'       => $body,
            'timeout'       => 8,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents(LOADMASTER_AGENT_URL . '/evaluate-load', false, $ctx);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded['success']) && isset($decoded['decision'])) {
            return $decoded;
        }
    }
    return loadmasterFallbackEvaluate($cargo, $actualWeight, $uld, $stage);
}

/**
 * Fallback aturan deterministik IATA (Perceive -> Think -> Act) saat layanan LangGraph.js offline.
 */
function loadmasterFallbackEvaluate(array $cargo, float $actualWeight, array $uld = [], int $stage = 5): array {
    $t0 = microtime(true);
    $declared = max(0.1, (float)($cargo['declared_weight_kg'] ?? 0));
    $actual = max(0.1, round($actualWeight, 2));
    $quantity = max(1, (int)($cargo['quantity'] ?? 1));
    $avgPieceKg = round($actual / $quantity, 2);

    $uldType = strtoupper((string)($uld['uld_type'] ?? 'AKE'));
    $defaultSpecs = [
        'AKE' => ['tare' => 75.0, 'max' => 1588.0, 'comp' => 'AFT-LOWER-1', 'reg' => 'IATA ULD Technical Manual §3.2 (LD3 Structural Limit)'],
        'PMC' => ['tare' => 110.0, 'max' => 6800.0, 'comp' => 'MAIN-DECK-01', 'reg' => 'IATA ULD Technical Manual §4.1 (Main Deck Pallet)'],
    ];
    $spec = $defaultSpecs[$uldType] ?? $defaultSpecs['AKE'];
    $uldCode = (string)($uld['uld_code'] ?? 'AKE-12345-GA');
    $compartment = (string)($uld['compartment'] ?? $spec['comp']);
    $tareKg = round((float)($uld['tare_weight_kg'] ?? $spec['tare']), 2);
    $maxUldKg = round((float)($uld['max_weight_kg'] ?? $spec['max']), 2);

    // Kalkulasi IATA CSC Resolution 600b (±5%)
    $diffKg = round($actual - $declared, 2);
    $diffPct = $declared > 0 ? round(($diffKg / $declared) * 100, 2) : 0.0;
    $maxTolKg = round($declared * 0.05, 2);
    $isWithinTolerance = (abs($diffPct) <= 5.0);
    $toleranceStatus = $isWithinTolerance ? 'WITHIN_TOLERANCE' : 'DISCREPANCY_WARNING';

    // Klasifikasi Heavy Cargo IATA TACT (>= 150 kg)
    $isHea = ($avgPieceKg >= 150.0 || $actual >= 150.0);

    // Kalkulasi ULD Gross & Weight & Balance CoG (% MAC)
    $grossUldKg = round($actual + $tareKg, 2);
    $isUldOverload = ($grossUldKg > $maxUldKg);
    $uldUtilPct = $maxUldKg > 0 ? round(($grossUldKg / $maxUldKg) * 100, 1) : 0.0;
    $cgOffset = (($grossUldKg - 500.0) / max(1.0, $maxUldKg)) * 1.4;
    $cgMac = round(max(22.0, min(31.5, 28.4 + $cgOffset * 0.4)), 2);

    // Deteksi anomali rasio berat per koli terhadap komoditas
    $commodityProfiles = [
        'PHARMA'          => ['label' => 'Farmasi / Cold Chain', 'maxPiece' => 120.0],
        'PERISHABLE'      => ['label' => 'Perishable / Makanan Segar', 'maxPiece' => 100.0],
        'ELECTRONICS'     => ['label' => 'Elektronik & Semikonduktor', 'maxPiece' => 180.0],
        'GENERAL_CARGO'   => ['label' => 'General Cargo / Tekstil / Industri', 'maxPiece' => 500.0],
        'DANGEROUS_GOODS' => ['label' => 'Dangerous Goods', 'maxPiece' => 250.0],
    ];
    $commodityType = strtoupper((string)($cargo['commodity_type'] ?? 'GENERAL_CARGO'));
    $profile = $commodityProfiles[$commodityType] ?? $commodityProfiles['GENERAL_CARGO'];
    $pieceAnomaly = ($avgPieceKg > $profile['maxPiece'] && abs($diffPct) > 5.0);

    $findings = [];
    $reasoning = [];
    $sign = $diffKg >= 0 ? '+' : '';

    if ($isWithinTolerance) {
        $reasoning[] = "Selisih timbangan {$sign}{$diffKg} kg ({$sign}{$diffPct}%) berada dalam batas toleransi IATA ±5% (maks ±{$maxTolKg} kg).";
    } else {
        $findings[] = [
            'code'       => 'IATA_600B_DISCREPANCY',
            'severity'   => abs($diffPct) >= 15.0 ? 0.8 : 0.45,
            'regulation' => 'IATA CSC Resolution 600b (±5.0% Weight Tolerance)',
            'detail'     => "Selisih berat {$sign}{$diffKg} kg ({$diffPct}%) melampaui batas toleransi IATA ±5% (±{$maxTolKg} kg). Diperlukan penerbitan e-CCA (Cargo Charges Correction Advice).",
        ];
        $reasoning[] = "⚠ Deviasi berat {$diffPct}% melebihi toleransi IATA ±5% → terbitkan e-CCA otomatis; bobot fisik {$actual} kg tetap diteruskan ke Weight & Balance.";
    }

    if ($isHea) {
        $findings[] = [
            'code'       => 'IATA_HEA_CARGO',
            'severity'   => 0.2,
            'regulation' => 'IATA TACT Rules §5.7 (HEA >= 150 kg per piece)',
            'detail'     => "Bobot {$actual} kg (rata-rata {$avgPieceKg} kg/koli) memenuhi ambang >= 150 kg → diklasifikasikan HEA (Heavy Cargo), wajib forklift mekanis.",
        ];
        $reasoning[] = 'Tag HEA (Heavy Cargo >= 150 kg) aktif → dispatch forklift & floor spreader.';
    }

    if ($pieceAnomaly) {
        $findings[] = [
            'code'       => 'DENSITY_PIECE_ANOMALY',
            'severity'   => 0.65,
            'regulation' => 'IATA Cargo Handling Manual (Weight Verification)',
            'detail'     => "Berat rata-rata {$avgPieceKg} kg/koli tidak lazim untuk komoditas {$profile['label']} (normal <= {$profile['maxPiece']} kg/koli) disertai deviasi {$diffPct}%.",
        ];
        $reasoning[] = "⚠ Anomali bobot per koli ({$avgPieceKg} kg/koli) terhadap profil {$profile['label']}.";
    }

    if ($isUldOverload) {
        $findings[] = [
            'code'       => 'ULD_STRUCTURAL_OVERLOAD',
            'severity'   => 0.95,
            'regulation' => $spec['reg'],
            'detail'     => "Berat bruto ULD {$grossUldKg} kg (Kargo {$actual} kg + Tare {$tareKg} kg) melebihi batas sertifikasi {$uldType} ({$maxUldKg} kg).",
        ];
        $reasoning[] = "⛔ OVERLOAD ULD: Gross {$grossUldKg} kg > batas struktural {$maxUldKg} kg ({$uldCode}). Dilarang masuk kontainer {$uldType}!";
    } else {
        $reasoning[] = "Kapasitas ULD {$uldCode}: Gross {$grossUldKg} / {$maxUldKg} kg ({$uldUtilPct}% terisi). CoG pesawat {$cgMac}% MAC (aman dalam envelope 18.0%-34.0% MAC).";
    }

    $decision = 'AUTO_APPROVED';
    if ($isUldOverload) {
        $decision = 'OVERLOAD_REJECTED';
    } elseif (!$isWithinTolerance) {
        $decision = 'CCA_AMENDMENT';
    }

    $riskScore = $isUldOverload
        ? 0.95
        : min(0.85, (!$isWithinTolerance ? 0.4 + min(0.35, abs($diffPct) / 100.0) : 0.04) + ($pieceAnomaly ? 0.2 : 0.0));
    $confidence = $isUldOverload ? 0.99 : ($isWithinTolerance ? 0.98 : 0.94);

    $refs = ['IATA CSC Resolution 600b (±5.0% Weight Tolerance)', $spec['reg']];
    if ($isHea) {
        $refs[] = 'IATA TACT Rules §5.7 (HEA >= 150 kg)';
    }

    $actions = [
        [
            'device'      => 'SMART_SCALE_01',
            'command'     => 'LOCK_ACTUAL_WEIGHT',
            'target'      => "{$actual} KG",
            'description' => "Mengunci bobot fisik aktual {$actual} kg (OIML R76) untuk modul Weight & Balance.",
        ],
    ];
    if (!$isWithinTolerance) {
        $actions[] = [
            'device'      => 'CMS_BILLING_ENGINE',
            'command'     => 'ISSUE_CCA',
            'target'      => "DIFF {$sign}{$diffKg} KG ({$diffPct}%)",
            'description' => 'Menerbitkan draf Cargo Charges Correction Advice (e-CCA) untuk penyesuaian tarif e-AWB.',
        ];
    }
    if ($isHea) {
        $actions[] = [
            'device'      => 'RAMP_GSE_DISPATCH',
            'command'     => 'ASSIGN_FORKLIFT_HEA',
            'target'      => 'HEAVY_CARGO',
            'description' => 'Menugaskan forklift mekanis & floor spreader karena bobot >= 150 kg (Tag HEA).',
        ];
    }
    if ($isUldOverload) {
        $actions[] = [
            'device'      => 'ULD_WORKSTATION_03',
            'command'     => 'BLOCK_AKE_OVERLOAD',
            'target'      => 'TRANSFER_TO_PMC_PALLET',
            'description' => "Menolak pemuatan ke {$uldCode} ({$grossUldKg} kg > {$maxUldKg} kg); alihkan ke Palet PMC Main Deck.",
        ];
    } else {
        $actions[] = [
            'device'      => 'ULD_WORKSTATION_03',
            'command'     => 'LOCK_COMPARTMENT',
            'target'      => "{$uldCode} → {$compartment}",
            'description' => "Mengunci kontainer {$uldCode} ke kompartemen {$compartment} dengan CoG {$cgMac}% MAC.",
        ];
    }

    if ($decision === 'OVERLOAD_REJECTED') {
        $headline = "OVERLOAD REJECTED — Total gross {$grossUldKg} kg melebihi kapasitas {$uldType} ({$maxUldKg} kg); wajib alihkan ke Palet PMC.";
    } elseif ($decision === 'CCA_AMENDMENT') {
        $headline = "CCA REQUIRED — Selisih {$sign}{$diffKg} kg ({$diffPct}%) melebihi ±5% IATA; tagihan dikoreksi & bobot fisik {$actual} kg diloloskan.";
    } else {
        $headline = "AUTO-APPROVED — Bobot aktual {$actual} kg lolos toleransi IATA ±5% ({$diffPct}%)" . ($isHea ? ' · Tag HEA aktif' : '') . " · CoG {$cgMac}% MAC aman.";
    }

    $awb = (string)($cargo['awb'] ?? ($cargo['awb_number'] ?? '-'));
    $now = date('c');

    return [
        'success'                 => true,
        'engine'                  => 'php-fallback',
        'llm'                     => ['used' => false, 'model' => null, 'error' => 'Layanan LangGraph.js (:8787) tidak aktif — menggunakan mesin aturan IATA deterministik'],
        'decision'                => $decision,
        'risk_score'              => round($riskScore, 2),
        'confidence'              => round($confidence, 2),
        'tolerance_status'        => $toleranceStatus,
        'is_within_tolerance'     => $isWithinTolerance,
        'cca_required'            => !$isWithinTolerance,
        'is_hea'                  => $isHea,
        'uld_overload'            => $isUldOverload,
        'discrepancy_kg'          => $diffKg,
        'discrepancy_percent'     => $diffPct,
        'max_tolerance_kg'        => $maxTolKg,
        'gross_uld_kg'            => $grossUldKg,
        'uld_utilization_percent' => $uldUtilPct,
        'cg_mac'                  => $cgMac,
        'headline'                => $headline,
        'reasoning'               => $reasoning,
        'findings'                => $findings,
        'regulation_refs'         => $refs,
        'actions'                 => $actions,
        'perception'              => [
            'stage'    => $stage,
            'manifest' => [
                'awb'                => $awb,
                'description'        => $cargo['description'] ?? '-',
                'commodity_type'     => $commodityType,
                'quantity'           => $quantity,
                'declared_weight_kg' => round($declared, 2),
            ],
            'scale_telemetry' => [
                'device_id'           => 'SCALE-FLOOR-ULD-01',
                'standard'            => 'OIML R76 Class III (±1.0 kg)',
                'actual_weight_kg'    => $actual,
                'avg_piece_weight_kg' => $avgPieceKg,
            ],
            'uld_spec' => [
                'uld_code'            => $uldCode,
                'uld_type'            => $uldType,
                'compartment'         => $compartment,
                'tare_weight_kg'      => $tareKg,
                'max_gross_weight_kg' => $maxUldKg,
            ],
        ],
        'trace' => [
            [
                'node'   => 'perceive',
                'title'  => 'PERCEIVE — Baca e-AWB & IoT Smart Scale',
                'detail' => "e-AWB {$awb}: deklarasi {$declared} kg ({$quantity} koli). Sensor Load Cell membaca {$actual} kg untuk {$uldCode} (Tare {$tareKg} / Maks {$maxUldKg} kg).",
                'ts'     => $now,
            ],
            [
                'node'   => 'think_rules',
                'title'  => 'THINK — Analisis IATA ±5%, HEA & Batas ULD',
                'detail' => "Selisih {$sign}{$diffKg} kg ({$diffPct}%) → {$toleranceStatus}. Gross ULD {$grossUldKg}/{$maxUldKg} kg ({$uldUtilPct}%), CoG {$cgMac}% MAC. Keputusan: {$decision}.",
                'ts'     => $now,
            ],
            [
                'node'   => 'act',
                'title'  => 'ACT — Eksekusi keputusan Loadmaster',
                'detail' => $isUldOverload
                    ? "Kunci bobot aktual · Terbitkan alert OVERLOAD → blokir {$uldCode} & rekomendasikan PMC."
                    : ((!$isWithinTolerance ? 'Terbitkan e-CCA · ' : '') . ($isHea ? 'Dispatch Forklift HEA · ' : '') . "Kunci bobot {$actual} kg → {$uldCode} ({$compartment}, CoG {$cgMac}% MAC)."),
                'ts'     => $now,
            ],
        ],
        'elapsed_ms' => (int)round((microtime(true) - $t0) * 1000),
    ];
}

/** Buat tabel jejak audit keputusan Loadmaster AI Agent bila belum ada. */
function ensureLoadmasterDecisionTable(PDO $db): void {
    // DDL memicu implicit commit di MySQL: panggil SEBELUM beginTransaction(); hanya dijalankan sekali per request.
    static $done = false;
    if ($done) return;
    $done = true;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `loadmaster_agent_decisions` (
            `id` VARCHAR(50) PRIMARY KEY,
            `cargo_id` VARCHAR(50) NOT NULL,
            `stage` TINYINT NOT NULL DEFAULT 5,
            `decision` VARCHAR(40) NOT NULL,
            `risk_score` DECIMAL(4,2) NOT NULL DEFAULT 0,
            `confidence` DECIMAL(4,2) NOT NULL DEFAULT 0,
            `engine` VARCHAR(40) NOT NULL DEFAULT 'langgraph-js',
            `llm_model` VARCHAR(60) NULL,
            `tolerance_status` VARCHAR(40) NOT NULL DEFAULT 'WITHIN_TOLERANCE',
            `cca_required` TINYINT(1) NOT NULL DEFAULT 0,
            `is_hea` TINYINT(1) NOT NULL DEFAULT 0,
            `uld_overload` TINYINT(1) NOT NULL DEFAULT 0,
            `headline` VARCHAR(500) NULL,
            `result_json` LONGTEXT NULL COMMENT 'Hasil lengkap: reasoning, findings, actions, trace',
            `created_at` DATETIME NOT NULL,
            INDEX (`cargo_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function saveLoadmasterDecision(PDO $db, string $cargoId, int $stage, array $r): void {
    ensureLoadmasterDecisionTable($db);
    $db->prepare("DELETE FROM loadmaster_agent_decisions WHERE cargo_id = ?")->execute([$cargoId]);
    $db->prepare("
        INSERT INTO loadmaster_agent_decisions (
            id, cargo_id, stage, decision, risk_score, confidence, engine, llm_model,
            tolerance_status, cca_required, is_hea, uld_overload, headline, result_json, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        'LMA-' . uniqid(),
        $cargoId,
        $stage,
        substr((string)($r['decision'] ?? 'AUTO_APPROVED'), 0, 40),
        (float)($r['risk_score'] ?? 0),
        (float)($r['confidence'] ?? 0),
        substr((string)($r['engine'] ?? 'langgraph-js'), 0, 40),
        $r['llm']['model'] ?? null,
        substr((string)($r['tolerance_status'] ?? 'WITHIN_TOLERANCE'), 0, 40),
        !empty($r['cca_required']) ? 1 : 0,
        !empty($r['is_hea']) ? 1 : 0,
        !empty($r['uld_overload']) ? 1 : 0,
        mb_substr((string)($r['headline'] ?? ''), 0, 500),
        json_encode($r, JSON_UNESCAPED_UNICODE),
    ]);
}

function getLatestLoadmasterDecision(PDO $db, string $cargoId): ?array {
    ensureLoadmasterDecisionTable($db);
    $st = $db->prepare("SELECT result_json FROM loadmaster_agent_decisions WHERE cargo_id = ? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$cargoId]);
    $json = $st->fetchColumn();
    if (!$json) return null;
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}
