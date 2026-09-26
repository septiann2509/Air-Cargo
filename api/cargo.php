<?php
// api/cargo.php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';
$db = getDb();

try {
    if ($action === 'list') {
        $stmt = $db->query("
            SELECT 
                c.*, 
                t.truck_plate, t.dock_slot, t.driver_name,
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

    if ($action === 'detail') {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Cargo ID required'], 400);
        }
        $stmt = $db->prepare("
            SELECT 
                c.*, 
                t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company,
                u.uld_code, u.uld_type, u.compartment, u.current_weight_kg as uld_weight, u.max_weight_kg as uld_max_weight,
                f.flight_number, f.destination as flight_destination, f.aircraft_type, f.departure_time,
                s.xray_result, s.inspector_id, s.csd_number, s.notes as avsec_notes,
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

        jsonResponse([
            'success' => true,
            'data' => [
                'cargo' => $cargo,
                'logs' => $logs
            ]
        ]);
    }

    if ($action === 'create' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Generate ID & Data
        $uniqueNum = rand(100, 999);
        $cargoId = 'CGO-' . date('Ymd') . '-' . $uniqueNum;
        $awbNumber = '126-' . rand(10000000, 99999999);
        // Format GS1 SSCC: 18 digits (00) 3 (extension 1) + 89912345 (GS1 Indonesia prefix) + serial + check digit
        $serial = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $ssccRaw = '389912345000' . $serial; // 17 digits
        // Calculate Luhn / Mod 10 check digit for GS1
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $val = (int)$ssccRaw[$i];
            $sum += ($i % 2 === 0) ? ($val * 3) : $val;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        $sscc = $ssccRaw . $checkDigit;

        $gtin = $input['gtin'] ?? ('0899' . rand(1000000000, 9999999999));
        $batchNo = $input['batch_no'] ?? ('LOT-GEN-' . rand(1000, 9999));
        $expiryDate = $input['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));
        $shipper = $input['shipper'] ?? 'PT Logistik Ekspor Nusantara';
        $consignee = $input['consignee'] ?? 'Bali Global Distribution Center';
        $description = $input['description'] ?? 'Paket Kargo Umum Terkonsolidasi';
        $commodityType = $input['commodity_type'] ?? 'GENERAL_CARGO';
        $quantity = (int)($input['quantity'] ?? 50);
        $declaredWeight = (float)($input['declared_weight_kg'] ?? 350.0);

        // Buat atau kaitkan Truk
        $truckId = 'TRK-' . date('Ymd') . '-' . rand(10, 99);
        $plate = 'B ' . rand(1000, 9999) . ' ' . chr(rand(65, 90)) . chr(rand(65, 90));
        $dock = 'DOCK-0' . rand(1, 6);

        $db->beginTransaction();

        $stmtTruck = $db->prepare("
            INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'ARRIVED')
        ");
        $stmtTruck->execute([
            $truckId,
            $plate,
            $input['driver_name'] ?? 'Suryadi Pratama',
            'PT Intermodal Logistik Sejahtera',
            $dock,
            'TAS-SLOT-' . rand(1000, 9999)
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

        // Simpan log tahap 1 (TMS / TAS Arrival)
        $logPayload = [
            'event' => 'TRUCK_ARRIVAL_BOOKING',
            'truck_id' => $truckId,
            'plate_number' => $plate,
            'driver' => $input['driver_name'] ?? 'Suryadi Pratama',
            'assigned_dock' => $dock,
            'awb_reference' => $awbNumber,
            'eta_status' => 'ON_TIME',
            'arrival_timestamp' => date('c')
        ];
        $logResponse = [
            'status' => 'SUCCESS',
            'message' => 'Truk berhasil dialokasikan ke dock ' . $dock,
            'gate_clearance_code' => 'GATE-OK-' . rand(1000, 9999)
        ];

        $stmtLog = $db->prepare("
            INSERT INTO scan_logs (
                id, cargo_id, stage, stage_name, hardware_device,
                software_system, action, json_payload, response_payload, http_status, timestamp
            ) VALUES (?, ?, 1, 'Kedatangan Truk & Booking Slot', 'TMS RFID Gate Reader', 'Intermodal TMS / TAS', 'Registrasi Kedatangan Truk & Alokasi Slot Dock', ?, ?, 200, NOW())
        ");
        $stmtLog->execute([
            'LOG-' . uniqid(),
            $cargoId,
            json_encode($logPayload, JSON_PRETTY_PRINT),
            json_encode($logResponse, JSON_PRETTY_PRINT)
        ]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Kargo berhasil dibuat',
            'cargo_id' => $cargoId,
            'sscc' => $sscc,
            'awb_number' => $awbNumber
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
