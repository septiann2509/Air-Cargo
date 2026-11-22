<?php
// api/cargo_input.php
// Single Entry Point API untuk menerima input data kargo & armada truk SATU KALI SAJA (Zero Re-entry).
// Data armada yang dibutuhkan TMS otomatis masuk ke truck_arrivals (status SCHEDULED),
// sedangkan master kargo terdaftar di cargo_shipments pada Tahap 1 (REGISTERED)
// untuk mengalir secara berurutan ke TMS -> CMS -> AVSEC -> Weight & Balance -> 3D Simulation.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cargo_payload.php';
require_once __DIR__ . '/../config/avsec_agent.php';

ob_start();
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON input'], 400);
}

$db = getDb();
ensureAvsecDecisionTable($db); // DDL harus sebelum transaksi dimulai
ensureCargoPayloadColumn($db);

try {
    $db->beginTransaction();

    // ─── 1. Generate ID & Kode Unik (Bebas Bentrok) ───
    $suffix3 = str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
    $timeTag = date('His');
    $cargoId = 'CGO-' . date('Ymd') . '-' . $timeTag . '-' . $suffix3;
    $awbNumber = '126-' . random_int(10000000, 99999999);

    // GS1 SSCC-18 Generator dengan Modulo-10 Check Digit
    $serial = str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    $ssccRaw = '389912345000' . $serial;
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        $val = (int)$ssccRaw[$i];
        $sum += ($i % 2 === 0) ? ($val * 3) : $val;
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    $sscc = $ssccRaw . $checkDigit;

    // ─── 2. Parse Form Data (Input Tunggal) ───
    $shipper = trim((string)($input['shipper'] ?? '')) ?: 'PT Logistik Nusantara';
    $consignee = trim((string)($input['consignee'] ?? '')) ?: 'Bali Distribution Center';
    $description = trim((string)($input['description'] ?? '')) ?: 'Pakaian & Tekstil Premium Ekspor';
    $validCommodities = ['GENERAL_CARGO', 'PERISHABLE', 'DANGEROUS_GOODS', 'VALUABLE', 'PHARMA'];
    $commodityType = in_array($input['commodity_type'] ?? '', $validCommodities) ? $input['commodity_type'] : 'GENERAL_CARGO';
    $quantity = max(1, (int)($input['quantity'] ?? 50));
    $declaredWeight = max(1, (float)($input['declared_weight_kg'] ?? 350.0));

    // Data transportasi darat (hanya field ini yang diteruskan ke TMS)
    $driverName = trim((string)($input['driver_name'] ?? '')) ?: 'Suryadi Pratama';
    $truckPlate = strtoupper(trim((string)($input['truck_plate'] ?? ''))) ?: ('B ' . random_int(1000, 9999) . ' ' . chr(random_int(65, 90)) . chr(random_int(65, 90)));
    $transporterCompany = trim((string)($input['transporter_company'] ?? '')) ?: 'PT Intermodal Logistik Sejahtera';
    $validDocks = ['DOCK-01', 'DOCK-02', 'DOCK-03'];
    $dock = in_array($input['dock_slot'] ?? '', $validDocks) ? $input['dock_slot'] : 'DOCK-02';
    $bookingCode = 'TAS-SLOT-' . random_int(1000, 9999);

    // Opsi skenario X-Ray untuk preview 3D (disimpan di payload pre-alert tanpa melompati Tahap 4 AVSEC)
    $xrayScenario = ($input['xray_result'] ?? 'CLEARED') === 'SUSPECT' ? 'SUSPECT' : 'CLEARED';
    $xrayResult = $xrayScenario;

    $gtin = $input['gtin'] ?? ('0899' . random_int(1000000000, 9999999999));
    $batchNo = $input['batch_no'] ?? ('LOT-' . strtoupper(substr($commodityType, 0, 3)) . '-' . random_int(1000, 9999));
    $expiryDate = $input['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));

    // ─── 3. Otomatis Kirim Data yang Dibutuhkan ke TMS (truck_arrivals) ───
    $truckId = 'TRK-' . date('Ymd') . '-' . $timeTag . '-' . random_int(10, 99);
    $stmtTruck = $db->prepare("
        INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'SCHEDULED', ?)
    ");
    $stmtTruck->execute([
        $truckId,
        $truckPlate,
        $driverName,
        $transporterCompany,
        $dock,
        $bookingCode,
        'Pre-alert booking otomatis dari Input Kargo (Zero Re-entry)'
    ]);

    // ─── 4. Simpan Master Kargo (cargo_shipments) di Tahap 1 (REGISTERED) ───
    $stmtCargo = $db->prepare("
        INSERT INTO cargo_shipments (
            id, awb_number, sscc, gtin, batch_no, expiry_date,
            shipper, consignee, description, commodity_type, quantity,
            declared_weight_kg, truck_arrival_id, current_stage, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'REGISTERED')
    ");
    $stmtCargo->execute([
        $cargoId, $awbNumber, $sscc, $gtin, $batchNo, $expiryDate,
        $shipper, $consignee, $description, $commodityType, $quantity,
        $declaredWeight, $truckId
    ]);

    // AI Agent AVSEC Pre-Check jika modul terpasang
    $agent = function_exists('avsecAgentInspect') ? avsecAgentInspect([
        'cargo_id' => $cargoId,
        'awb' => $awbNumber,
        'description' => $description,
        'commodity_type' => $commodityType,
        'quantity' => $quantity,
        'declared_weight_kg' => $declaredWeight,
        'shipper' => $shipper,
        'consignee' => $consignee,
    ], $xrayScenario) : null;
    if ($agent && function_exists('saveAvsecDecision')) {
        saveAvsecDecision($db, $cargoId, $agent);
    }

    $db->commit();

    // ─── 5. Sinkronkan Master JSON Payload Kargo (CMP-...) ───
    $masterPayload = syncCargoPayload($db, $cargoId, $xrayResult);

    // ─── 6. Kembalikan Data Terpadu & Slice Data Khusus TMS ───
    jsonResponse([
        'success' => true,
        'message' => 'Data kargo berhasil diinput satu kali. Data armada & booking otomatis tersinkronisasi ke Dashboard TMS tanpa re-entry.',
        'cargo_id' => $cargoId,
        'cargo_payload_id' => 'CMP-' . $cargoId,
        'payload' => $masterPayload,
        'awb_number' => $awbNumber,
        'sscc' => $sscc,
        'truck_id' => $truckId,
        'truck_plate' => $truckPlate,
        'driver_name' => $driverName,
        'transporter_company' => $transporterCompany,
        'dock_slot' => $dock,
        'booking_code' => $bookingCode,
        'tms_data' => [
            'truck_id' => $truckId,
            'truck_plate' => $truckPlate,
            'driver_name' => $driverName,
            'transporter_company' => $transporterCompany,
            'dock_slot' => $dock,
            'booking_code' => $bookingCode,
            'status' => 'SCHEDULED'
        ],
        'xray_result' => $xrayResult,
        'current_stage' => 1,
        'final_status' => 'REGISTERED',
        'scenario' => ($xrayResult === 'SUSPECT') ? 'suspect' : 'normal',
        'xray_scenario' => $xrayScenario,
        'agent' => $agent
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
