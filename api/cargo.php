<?php
// api/cargo.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cargo_payload.php';
require_once __DIR__ . '/../config/avsec_agent.php';
require_once __DIR__ . '/../config/loadmaster_agent.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDb();

try {
    if ($action === 'list') {
        $stmt = $db->query("
            SELECT 
                c.*, 
                CONCAT('CMP-', c.id) as cargo_payload_id,
                t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code, t.status as truck_status,
                u.uld_code, u.compartment,
                f.flight_number, f.destination as flight_dest
            FROM cargo_shipments c
            LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
            LEFT JOIN uld_containers u ON c.uld_id = u.id
            LEFT JOIN flight_manifests f ON u.flight_id = f.id
            ORDER BY c.created_at DESC
        ");
        $cargos = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $cargos]);
    }

    if ($action === 'tms') {
        $id = $_GET['id'] ?? $_GET['cargo_id'] ?? '';
        if ($id) {
            $stmt = $db->prepare("
                SELECT t.id as truck_id, t.truck_plate, t.driver_name, t.transporter_company,
                       t.dock_slot, t.booking_code, t.arrival_time, t.status as truck_status,
                       c.id as cargo_id, c.current_stage, c.status as cargo_status
                FROM cargo_shipments c
                LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->query("
                SELECT t.id as truck_id, t.truck_plate, t.driver_name, t.transporter_company,
                       t.dock_slot, t.booking_code, t.arrival_time, t.status as truck_status,
                       c.id as cargo_id, c.current_stage, c.status as cargo_status
                FROM cargo_shipments c
                LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
                ORDER BY c.created_at DESC
                LIMIT 1
            ");
        }
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Data TMS tidak ditemukan'], 404);
        }

        $trucks = $db->query("
            SELECT id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status
            FROM truck_arrivals
            ORDER BY arrival_time DESC, created_at DESC
            LIMIT 20
        ")->fetchAll();
        $totalTrucks = (int)$db->query("SELECT COUNT(*) FROM truck_arrivals")->fetchColumn();

        jsonResponse([
            'success' => true,
            'tms_data' => [
                'truck_id' => $row['truck_id'],
                'truck_plate' => $row['truck_plate'],
                'driver_name' => $row['driver_name'],
                'transporter_company' => $row['transporter_company'],
                'dock_slot' => $row['dock_slot'],
                'booking_code' => $row['booking_code'],
                'arrival_time' => $row['arrival_time'],
                'truck_status' => $row['truck_status'],
                'current_stage' => (int)$row['current_stage'],
                'gate_verified' => ((int)$row['current_stage'] >= 2 || $row['cargo_status'] !== 'REGISTERED'),
                'source_system' => 'Single Entry (Input Kargo)',
                'zero_reentry' => true
            ],
            'tms_trucks' => $trucks,
            'tms_total_trucks' => $totalTrucks
        ]);
    }

    if ($action === 'master_payload') {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Cargo ID required'], 400);
        }
        $payload = syncCargoPayload($db, $id);
        if (!$payload) {
            jsonResponse(['success' => false, 'message' => 'Cargo not found'], 404);
        }
        jsonResponse([
            'success' => true,
            'cargo_id' => $id,
            'cargo_payload_id' => 'CMP-' . $id,
            'payload' => $payload
        ]);
    }

    if ($action === 'detail') {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Cargo ID required'], 400);
        }
        $stmt = $db->prepare("
            SELECT 
                c.*, 
                t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company,
                t.booking_code, t.arrival_time, t.status as truck_status,
                u.uld_code, u.uld_type, u.compartment, u.current_weight_kg as uld_weight, u.max_weight_kg as uld_max_weight,
                f.flight_number, f.destination as flight_destination, f.aircraft_type, f.departure_time, f.max_cargo_weight_kg as flight_max_weight,
                s.xray_result, s.inspector_id, s.csd_number, s.check_time, s.notes as avsec_notes,
                w.actual_weight_kg as weighed_kg, w.weight_discrepancy_kg, w.tolerance_status
            FROM cargo_shipments c
            LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
            LEFT JOIN uld_containers u ON c.uld_id = u.id
            LEFT JOIN flight_manifests f ON u.flight_id = f.id
            LEFT JOIN security_checks s ON s.cargo_id = c.id
            LEFT JOIN weight_records w ON w.cargo_id = c.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $cargo = $stmt->fetch();

        if (!$cargo) {
            jsonResponse(['success' => false, 'message' => 'Cargo not found'], 404);
        }

        // Ambil scan logs
        $logStmt = $db->prepare("SELECT * FROM scan_logs WHERE cargo_id = ? ORDER BY stage ASC, timestamp ASC");
        $logStmt->execute([$id]);
        $logs = $logStmt->fetchAll();

        // Data khusus TMS (hanya kolom yang dibutuhkan TMS, bebas data kargo internal)
        $tmsData = [
            'truck_id' => $cargo['truck_arrival_id'],
            'truck_plate' => $cargo['truck_plate'],
            'driver_name' => $cargo['driver_name'],
            'transporter_company' => $cargo['transporter_company'],
            'dock_slot' => $cargo['dock_slot'],
            'booking_code' => $cargo['booking_code'],
            'arrival_time' => $cargo['arrival_time'],
            'truck_status' => $cargo['truck_status'] ?? 'SCHEDULED',
            'gate_verified' => ((int)$cargo['current_stage'] >= 2 || $cargo['status'] !== 'REGISTERED'),
            'source_system' => 'Single Entry (Input Kargo)',
            'zero_reentry' => true
        ];

        $tmsTrucks = $db->query("
            SELECT id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status
            FROM truck_arrivals
            ORDER BY arrival_time DESC, created_at DESC
            LIMIT 20
        ")->fetchAll();
        $tmsTotalTrucks = (int)$db->query("SELECT COUNT(*) FROM truck_arrivals")->fetchColumn();

        // Sinkronkan dan ambil Master JSON Payload Terpadu
        $masterPayload = syncCargoPayload($db, $id);

        // Ambil jejak keputusan AI Agent AVSEC & Loadmaster (jika pernah diproses)
        ensureAvsecDecisionTable($db);
        $agentStmt = $db->prepare("SELECT result_json FROM avsec_agent_decisions WHERE cargo_id = ? ORDER BY created_at DESC LIMIT 1");
        $agentStmt->execute([$id]);
        $agentJson = $agentStmt->fetchColumn();
        $agentData = $agentJson ? json_decode($agentJson, true) : null;

        $loadmasterData = getLatestLoadmasterDecision($db, $id);
        $uldsList = $db->query("
            SELECT u.*, f.flight_number
            FROM uld_containers u
            LEFT JOIN flight_manifests f ON u.flight_id = f.id
            ORDER BY u.id ASC
        ")->fetchAll();

        jsonResponse([
            'success' => true,
            'data' => [
                'cargo' => $cargo,
                'tms_data' => $tmsData,
                'tms_trucks' => $tmsTrucks,
                'tms_total_trucks' => $tmsTotalTrucks,
                'ulds' => $uldsList,
                'logs' => $logs,
                'payload' => $masterPayload,
                'payload_id' => 'CMP-' . $cargo['id'],
                'agent' => $agentData,
                'loadmaster_agent' => $loadmasterData
            ]
        ]);
    }

    if ($action === 'create' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Generate ID & Data
        $suffix3 = str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $timeTag = date('His');
        $cargoId = 'CGO-' . date('Ymd') . '-' . $timeTag . '-' . $suffix3;
        $awbNumber = '126-' . random_int(10000000, 99999999);
        // Format GS1 SSCC: 18 digits
        $serial = str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $ssccRaw = '389912345000' . $serial;
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $val = (int)$ssccRaw[$i];
            $sum += ($i % 2 === 0) ? ($val * 3) : $val;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        $sscc = $ssccRaw . $checkDigit;

        $gtin = $input['gtin'] ?? ('0899' . random_int(1000000000, 9999999999));
        $batchNo = $input['batch_no'] ?? ('LOT-GEN-' . random_int(1000, 9999));
        $expiryDate = $input['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));
        $shipper = $input['shipper'] ?? 'PT Logistik Ekspor Nusantara';
        $consignee = $input['consignee'] ?? 'Bali Global Distribution Center';
        $description = $input['description'] ?? 'Paket Kargo Umum Terkonsolidasi';
        $commodityType = $input['commodity_type'] ?? 'GENERAL_CARGO';
        $quantity = (int)($input['quantity'] ?? 50);
        $declaredWeight = (float)($input['declared_weight_kg'] ?? 350.0);

        $truckId = 'TRK-' . date('Ymd') . '-' . $timeTag . '-' . random_int(10, 99);
        $plate = !empty($input['truck_plate']) ? strtoupper(trim($input['truck_plate'])) : ('B ' . random_int(1000, 9999) . ' ' . chr(random_int(65, 90)) . chr(random_int(65, 90)));
        $validDocks = ['DOCK-01', 'DOCK-02', 'DOCK-03', 'DOCK-04'];
        $dock = in_array($input['dock_slot'] ?? '', $validDocks) ? $input['dock_slot'] : 'DOCK-02';
        $transporter = !empty($input['transporter_company']) ? trim($input['transporter_company']) : 'PT Intermodal Logistik Sejahtera';
        $bookingCode = 'TAS-SLOT-' . random_int(1000, 9999);

        $db->beginTransaction();

        $stmtTruck = $db->prepare("
            INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'SCHEDULED', ?)
        ");
        $stmtTruck->execute([
            $truckId,
            $plate,
            $input['driver_name'] ?? 'Suryadi Pratama',
            $transporter,
            $dock,
            $bookingCode,
            'Pre-alert booking otomatis dari Input Kargo (Zero Re-entry)'
        ]);

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

        $db->commit();

        $masterPayload = syncCargoPayload($db, $cargoId);

        jsonResponse([
            'success' => true,
            'message' => 'Kargo berhasil dibuat (Zero Re-entry)',
            'cargo_id' => $cargoId,
            'cargo_payload_id' => 'CMP-' . $cargoId,
            'sscc' => $sscc,
            'awb_number' => $awbNumber,
            'payload' => $masterPayload
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
