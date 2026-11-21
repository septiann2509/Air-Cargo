<?php
// api/avsec_agent.php
//   GET  ?action=health          → status layanan AI Agent (untuk badge UI)
//   GET  ?action=decision&cargo_id=CGO-...  → hasil keputusan agen yang tersimpan (jejak audit)
//   POST (JSON {cargo:{...}, scenario})     → inspeksi langsung tanpa menyimpan (preview/demo)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/avsec_agent.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'health') {
    jsonResponse(['success' => true] + avsecAgentHealth());
}

if ($method === 'GET' && $action === 'decision') {
    $cargoId = $_GET['cargo_id'] ?? '';
    if ($cargoId === '') jsonResponse(['success' => false, 'message' => 'cargo_id wajib'], 400);
    $db = getDb();
    ensureAvsecDecisionTable($db);
    $st = $db->prepare("SELECT result_json FROM avsec_agent_decisions WHERE cargo_id = ? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$cargoId]);
    $json = $st->fetchColumn();
    if (!$json) jsonResponse(['success' => false, 'message' => 'Belum ada keputusan agen untuk kargo ini'], 404);
    jsonResponse(['success' => true, 'agent' => json_decode($json, true)]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['cargo']) || !is_array($input['cargo'])) {
        jsonResponse(['success' => false, 'message' => 'Body JSON harus berisi "cargo"'], 400);
    }
    $scenario = ($input['scenario'] ?? 'CLEARED') === 'SUSPECT' ? 'SUSPECT' : 'CLEARED';
    $agent = avsecAgentInspect($input['cargo'], $scenario);
    $cargoId = trim((string)($input['cargo_id'] ?? ($input['cargo']['cargo_id'] ?? '')));
    if ($cargoId !== '' && $cargoId !== 'CRG-DEMO') {
        try {
            $db = getDb();
            ensureAvsecDecisionTable($db);
            saveAvsecDecision($db, $cargoId, $agent);
        } catch (Throwable $e) { /* audit opsional saat preview */ }
    }
    jsonResponse(['success' => true, 'agent' => $agent]);
}

jsonResponse(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);
