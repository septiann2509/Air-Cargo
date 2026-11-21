<?php
// api/loadmaster_agent.php
//   GET  ?action=health                    → status layanan AI Agent Loadmaster
//   GET  ?action=decision&cargo_id=CGO-... → keputusan agen Loadmaster tersimpan (jejak audit)
//   POST (JSON {cargo:{...}, actual_weight_kg, uld:{...}, stage}) → evaluasi langsung AI Agent
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/avsec_agent.php';
require_once __DIR__ . '/../config/loadmaster_agent.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'health') {
    jsonResponse(['success' => true] + avsecAgentHealth());
}

if ($method === 'GET' && $action === 'decision') {
    $cargoId = $_GET['cargo_id'] ?? '';
    if ($cargoId === '') jsonResponse(['success' => false, 'message' => 'cargo_id wajib'], 400);
    $db = getDb();
    $decision = getLatestLoadmasterDecision($db, $cargoId);
    if (!$decision) jsonResponse(['success' => false, 'message' => 'Belum ada keputusan Loadmaster Agent untuk kargo ini'], 404);
    jsonResponse(['success' => true, 'agent' => $decision]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['cargo']) || !is_array($input['cargo'])) {
        jsonResponse(['success' => false, 'message' => 'Body JSON harus berisi "cargo"'], 400);
    }
    $cargo = $input['cargo'];
    $actualWeight = isset($input['actual_weight_kg']) && is_numeric($input['actual_weight_kg'])
        ? (float)$input['actual_weight_kg']
        : (float)($cargo['actual_weight_kg'] ?? $cargo['declared_weight_kg'] ?? 0);
    $uld = is_array($input['uld'] ?? null) ? $input['uld'] : [];
    $stage = (int)($input['stage'] ?? 5);

    $agent = loadmasterAgentEvaluate($cargo, $actualWeight, $uld, $stage);
    $cargoId = trim((string)($input['cargo_id'] ?? ($cargo['cargo_id'] ?? '')));
    if ($cargoId !== '' && $cargoId !== 'CRG-DEMO') {
        try {
            $db = getDb();
            saveLoadmasterDecision($db, $cargoId, $stage, $agent);
        } catch (Throwable $e) { /* audit opsional saat preview */ }
    }
    jsonResponse(['success' => true, 'agent' => $agent]);
}

jsonResponse(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);
