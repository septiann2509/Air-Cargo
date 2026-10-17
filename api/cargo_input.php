<?php
// api/cargo_input.php
// API endpoint untuk menerima input kargo baru dari form cargo_input.php
// dan menyiapkan data untuk simulasi 3D di layout_3d.php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON input'], 400);
}

$db = getDb();

try {
    $db->beginTransaction();

    // ─── 1. Generate ID & Kode Unik ───
    $uniqueNum = rand(100, 999);
    $cargoId = 'CGO-' . date('Ymd') . '-' . $uniqueNum;
    $awbNumber = '126-' . rand(10000000, 99999999);
    
    // GS1 SSCC-18 Generator
    $serial = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $ssccRaw = '389912345000' . $serial;
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        $val = (int)$ssccRaw[$i];
        $sum += ($i % 2 === 0) ? ($val * 3) : $val;
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    $sscc = $ssccRaw . $checkDigit;

    // ─── 2. Parse Form Data ───
    $shipper = $input['shipper'] ?? 'PT Logistik Ekspor Nusantara';
    $consignee = $input['consignee'] ?? 'Bali Global Distribution Center';
    $description = $input['description'] ?? 'Paket Kargo Umum Terkonsolidasi';
    $commodityType = $input['commodity_type'] ?? 'GENERAL_CARGO';
    $quantity = max(1, (int)($input['quantity'] ?? 50));
    $declaredWeight = max(1, (float)($input['declared_weight_kg'] ?? 350.0));
    $driverName = $input['driver_name'] ?: 'Suryadi Pratama';
    $truckPlate = $input['truck_plate'] ?: ('B ' . rand(1000, 9999) . ' ' . chr(rand(65, 90)) . chr(rand(65, 90)));
    $xrayResult = ($input['xray_result'] ?? 'CLEARED') === 'SUSPECT' ? 'SUSPECT' : 'CLEARED';
    
    $gtin = $input['gtin'] ?? ('0899' . rand(1000000000, 9999999999));
    $batchNo = $input['batch_no'] ?? ('LOT-' . strtoupper(substr($commodityType, 0, 3)) . '-' . rand(1000, 9999));
    $expiryDate = $input['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'));

    // ─── 3. Buat Truk Arrival ───
    $truckId = 'TRK-' . date('Ymd') . '-' . rand(10, 99);
    $validDocks = ['DOCK-01', 'DOCK-02', 'DOCK-03'];
    $dock = in_array($input['dock_slot'] ?? '', $validDocks) ? $input['dock_slot'] : $validDocks[array_rand($validDocks)];
    $bookingCode = 'TAS-SLOT-' . rand(1000, 9999);

    $stmtTruck = $db->prepare("
        INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'ARRIVED')
    ");
    $stmtTruck->execute([
        $truckId,
        $truckPlate,
        $driverName,
        'PT Intermodal Logistik Sejahtera',
        $dock,
        $bookingCode
    ]);

    // ─── 4. Buat Cargo Shipment ───
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

    // ─── 5. Log Tahap 1 (TMS/TAS Arrival) ───
    $logPayload = [
        'event' => 'TRUCK_ARRIVAL_BOOKING',
        'truck_id' => $truckId,
        'plate_number' => $truckPlate,
        'driver' => $driverName,
        'assigned_dock' => $dock,
        'awb_reference' => $awbNumber,
        'eta_status' => 'ON_TIME',
        'arrival_timestamp' => date('c')
    ];
    $logResponse = [
        'status' => 'SUCCESS',
        'message' => 'Truk berhasil dialokasikan ke ' . $dock,
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

    // ─── 6. Auto-simulasi RFID (Tahap 2) ───
    $scanTime = date('Y-m-d H:i:s');
    $db->prepare("UPDATE cargo_shipments SET current_stage = 2, status = 'SCANNED', scan_time = ? WHERE id = ?")
       ->execute([$scanTime, $cargoId]);
    
    $rfidPayload = [
        'event_type' => 'RFID_AIDC_GATE_SCAN',
        'device_id' => 'SCANNER-GATE-CONVEYOR-01',
        'gs1_data' => [
            'ai_00_sscc' => $sscc,
            'ai_01_gtin' => $gtin,
            'ai_10_batch_no' => $batchNo,
            'ai_17_expiry_date' => $expiryDate,
            'quantity_koli' => $quantity
        ],
        'scanned_at' => date('c')
    ];
    $db->prepare("
        INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
        VALUES (?, ?, 2, 'Scan RFID/Barcode Gerbang Masuk', 'High-Speed RFID/Barcode Scanner', 'Cargo Management Software (CMS)', 'Pembacaan Massal Tag GS1 AIDC', ?, ?, 200, NOW())
    ")->execute(['LOG-' . uniqid(), $cargoId, json_encode($rfidPayload, JSON_PRETTY_PRINT), json_encode(['status' => '200_OK', 'message' => 'Tag SSCC berhasil diidentifikasi'], JSON_PRETTY_PRINT)]);

    // ─── 7. Auto-simulasi CMS Registration (Tahap 3) ───
    $db->prepare("UPDATE cargo_shipments SET current_stage = 3, status = 'SECURITY_CHECK' WHERE id = ?")
       ->execute([$cargoId]);
    
    $db->prepare("
        INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
        VALUES (?, ?, 3, 'Registrasi Data di CMS', 'CMS Core Server', 'Cargo Management Software (CMS)', 'Pencocokan e-AWB & Alokasi Alur Pemeriksaan', ?, ?, 200, NOW())
    ")->execute(['LOG-' . uniqid(), $cargoId, json_encode(['event_type' => 'CMS_AWB_MATCHING', 'e_awb_number' => $awbNumber, 'matched_sscc' => $sscc], JSON_PRETTY_PRINT), json_encode(['status' => '200_OK', 'cms_status' => 'AWB_VERIFIED'], JSON_PRETTY_PRINT)]);

    // ─── 8. Auto-simulasi X-Ray (Tahap 4) ───
    $isSuspect = ($xrayResult === 'SUSPECT');
    $csdNum = $isSuspect ? null : ('CSD-ID-' . date('Ymd') . '-' . rand(1000, 9999));
    $inspectorId = 'AVSEC-OPR-07';
    $xrayNotes = $isSuspect 
        ? 'Peringatan: Terdeteksi kepadatan tinggi mencurigakan (Potensi Dangerous Goods)' 
        : 'Pemeriksaan normal. Tidak ditemukan bahan berbahaya.';

    $db->prepare("DELETE FROM security_checks WHERE cargo_id = ?")->execute([$cargoId]);
    
    $db->prepare("
        INSERT INTO security_checks (id, cargo_id, scanner_type, xray_result, inspector_id, csd_number, check_time, density_reading, notes)
        VALUES (?, ?, 'Dual-View High-Energy X-Ray (180x180cm, 320kV)', ?, ?, ?, NOW(), ?, ?)
    ")->execute([
        'SEC-' . uniqid(), $cargoId, $xrayResult, $inspectorId, $csdNum,
        $isSuspect ? 'High Density Z-effective > 20' : 'Standard Organic Z=7-8',
        $xrayNotes
    ]);

    $finalStatus = $isSuspect ? 'SUSPECT' : 'CLEARED';
    $db->prepare("UPDATE cargo_shipments SET current_stage = 4, status = ? WHERE id = ?")->execute([$finalStatus, $cargoId]);

    $xrayLogPayload = [
        'event_type' => 'SECURITY_SCREENING_EVALUATION',
        'cargo_id' => $cargoId,
        'inspection_result' => $xrayResult,
        'inspector_badge' => $inspectorId,
        'csd_certificate' => $csdNum,
        'timestamp' => date('c')
    ];
    $httpCode = $isSuspect ? 403 : 200;
    $db->prepare("
        INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
        VALUES (?, ?, 4, 'Pemeriksaan Keamanan X-Ray', 'Dual-View X-Ray Scanner (AVSEC)', 'Aviation Security Management System', 'Screening Sinar-X & CSD', ?, ?, ?, NOW())
    ")->execute([
        'LOG-' . uniqid(), $cargoId,
        json_encode($xrayLogPayload, JSON_PRETTY_PRINT),
        json_encode(['status' => $isSuspect ? '403_FORBIDDEN' : '200_OK', 'security_clearance' => $xrayResult], JSON_PRETTY_PRINT),
        $httpCode
    ]);

    // ─── 9. Jika CLEARED: lanjut Tahap 5-7 ───
    if (!$isSuspect) {
        // Tahap 5: Penimbangan
        $jitter = rand(-3, 5);
        $actualWeight = max(10, $declaredWeight + $jitter);
        $discrepancy = $actualWeight - $declaredWeight;
        $tolerance = abs($discrepancy) <= 10 ? 'WITHIN_TOLERANCE' : 'DISCREPANCY_WARNING';
        
        $db->prepare("DELETE FROM weight_records WHERE cargo_id = ?")->execute([$cargoId]);
        $db->prepare("
            INSERT INTO weight_records (id, cargo_id, declared_weight_kg, actual_weight_kg, weight_discrepancy_kg, scale_device_id, weigh_time, tolerance_status)
            VALUES (?, ?, ?, ?, ?, 'SCALE-FLOOR-ULD-01', NOW(), ?)
        ")->execute(['WGT-' . uniqid(), $cargoId, $declaredWeight, $actualWeight, $discrepancy, $tolerance]);

        $db->prepare("UPDATE cargo_shipments SET current_stage = 5, status = 'WEIGHED', actual_weight_kg = ? WHERE id = ?")->execute([$actualWeight, $cargoId]);
        
        $db->prepare("
            INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
            VALUES (?, ?, 5, 'Penimbangan Kargo', 'Smart ULD Floor Weighing Station', 'CMS & Weight/Balance Engine', 'Pembacaan Sensor Load Cell', ?, ?, 200, NOW())
        ")->execute(['LOG-' . uniqid(), $cargoId, json_encode(['event_type' => 'CARGO_WEIGHT_ACQUISITION', 'actual_weight' => $actualWeight], JSON_PRETTY_PRINT), json_encode(['status' => '200_OK', 'allow_uld_buildup' => true], JSON_PRETTY_PRINT)]);

        // Tahap 6: Build-Up ULD
        $stmtUld = $db->query("
            SELECT u.*, f.flight_number, f.aircraft_type, f.destination 
            FROM uld_containers u
            JOIN flight_manifests f ON u.flight_id = f.id
            WHERE u.status IN ('EMPTY', 'LOADING')
            ORDER BY u.current_weight_kg ASC
            LIMIT 1
        ");
        $targetUld = $stmtUld->fetch();

        if ($targetUld) {
            $newUldWeight = (float)$targetUld['current_weight_kg'] + $actualWeight;
            $uldStatus = ($newUldWeight >= ((float)$targetUld['max_weight_kg'] * 0.85)) ? 'FULL' : 'LOADING';

            $db->prepare("UPDATE uld_containers SET current_weight_kg = ?, status = ? WHERE id = ?")->execute([$newUldWeight, $uldStatus, $targetUld['id']]);
            $db->prepare("UPDATE cargo_shipments SET current_stage = 6, status = 'ALLOCATED', uld_id = ? WHERE id = ?")->execute([$targetUld['id'], $cargoId]);
            
            $db->prepare("
                INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
                VALUES (?, ?, 6, 'Build-Up ULD & Weight Balance', 'Automated ULD Workstation', 'Weight & Balance System', 'Konsolidasi Kargo ke ULD', ?, ?, 200, NOW())
            ")->execute(['LOG-' . uniqid(), $cargoId, json_encode(['event_type' => 'ULD_CONSOLIDATION', 'uld_code' => $targetUld['uld_code'], 'new_weight' => $newUldWeight], JSON_PRETTY_PRINT), json_encode(['status' => '200_OK', 'load_position_locked' => true], JSON_PRETTY_PRINT)]);

            // Tahap 7: Loading
            $newFlightWeight = (float)($db->query("SELECT current_total_weight_kg FROM flight_manifests WHERE id = '" . $targetUld['flight_id'] . "'")->fetchColumn()) + $actualWeight;
            $db->prepare("UPDATE flight_manifests SET current_total_weight_kg = ?, status = 'LOADING' WHERE id = ?")->execute([$newFlightWeight, $targetUld['flight_id']]);
            $db->prepare("UPDATE cargo_shipments SET current_stage = 7, status = 'LOADED' WHERE id = ?")->execute([$cargoId]);

            $db->prepare("
                INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
                VALUES (?, ?, 7, 'Update Manifes & Loading ke Pesawat', 'Cargo High-Loader', 'CMS & Flight Ops', 'Pemuatan ULD ke Pesawat', ?, ?, 200, NOW())
            ")->execute(['LOG-' . uniqid(), $cargoId, json_encode(['event_type' => 'FLIGHT_MANIFEST_FINALIZATION', 'flight_number' => $targetUld['flight_number']], JSON_PRETTY_PRINT), json_encode(['status' => '200_OK', 'aircraft_ramp_status' => 'READY_FOR_PUSHBACK'], JSON_PRETTY_PRINT)]);
        }
    }

    $db->commit();

    // ─── 10. Return Response ───
    jsonResponse([
        'success' => true,
        'message' => 'Kargo berhasil didaftarkan dan simulasi ' . ($isSuspect ? 'karantina' : 'loading lengkap') . ' telah dijalankan',
        'cargo_id' => $cargoId,
        'awb_number' => $awbNumber,
        'sscc' => $sscc,
        'truck_id' => $truckId,
        'truck_plate' => $truckPlate,
        'dock_slot' => $dock,
        'xray_result' => $xrayResult,
        'final_status' => $finalStatus,
        'scenario' => $isSuspect ? 'suspect' : 'normal'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
