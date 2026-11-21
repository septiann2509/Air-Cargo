<?php
// api/simulate.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cargo_payload.php';
require_once __DIR__ . '/../config/avsec_agent.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$cargoId = $input['cargo_id'] ?? '';
$targetStage = (int)($input['stage'] ?? 0);
$actionType = $input['action'] ?? 'advance'; // 'advance' or 'auto_run' or 'force_suspect'

if (!$cargoId) {
    jsonResponse(['success' => false, 'message' => 'Cargo ID is required'], 400);
}

$db = getDb();

try {
    // Ambil data kargo saat ini
    $stmt = $db->prepare("
        SELECT c.*, t.truck_plate, t.driver_name, t.dock_slot
        FROM cargo_shipments c
        LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();

    if (!$cargo) {
        jsonResponse(['success' => false, 'message' => 'Cargo not found'], 404);
    }

    if ($actionType === 'auto_run') {
        // Jalankan dari stage aktif sampai stage 7 secara berurutan
        $results = [];
        $startStage = ($cargo['status'] === 'LOADED') ? 1 : max(1, (int)$cargo['current_stage']);
        $failedMessage = null;
        for ($s = $startStage; $s <= 7; $s++) {
            $res = executeStage($db, $cargoId, $s, 'CLEARED');
            $results[] = $res;
            if (!$res['success']) {
                $failedMessage = $res['message'] ?? 'Simulasi terhenti pada tahap ' . $s;
                break;
            }
        }
        $stmtFetch = $db->prepare("
            SELECT c.*, t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code, t.arrival_time, t.status as truck_status
            FROM cargo_shipments c
            LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
            WHERE c.id = ?
        ");
        $stmtFetch->execute([$cargoId]);
        $finalCargo = $stmtFetch->fetch();
        $finalPayload = syncCargoPayload($db, $cargoId);

        if ($failedMessage !== null) {
            jsonResponse([
                'success' => false,
                'message' => $failedMessage,
                'cargo' => $finalCargo,
                'payload' => $finalPayload,
                'history' => $results
            ]);
        }

        jsonResponse([
            'success' => true,
            'message' => ($finalCargo['status'] ?? '') === 'SUSPECT'
                ? 'Auto-run dihentikan di Tahap 4 (AVSEC): AI Agent mendeteksi potensi Dangerous Goods dan mengunci kargo di Ruang Karantina.'
                : 'Auto-run simulasi selesai hingga tahap akhir (LOADED)',
            'cargo' => $finalCargo,
            'payload' => $finalPayload,
            'history' => $results
        ]);
    } else {
        // Jalankan single stage tertentu (tanpa re-entry input)
        $suspectParam = ($actionType === 'force_suspect') ? 'SUSPECT' : ($input['avsec_decision'] ?? 'CLEARED');
        $stageToRun = ($targetStage > 0) ? $targetStage : ($cargo['current_stage'] + 1);

        $res = executeStage($db, $cargoId, $stageToRun, $suspectParam);
        jsonResponse($res);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Eksekutor Tiap Tahapan Simulasi (Zero Re-entry Architecture)
 */
function executeStage($db, $cargoId, $stage, $avsecDecision = 'CLEARED') {
    $stmt = $db->prepare("
        SELECT c.*,
               t.truck_plate, t.driver_name, t.transporter_company, t.dock_slot, t.booking_code, t.arrival_time, t.status AS truck_status,
               u.uld_code, u.uld_type, u.compartment,
               f.flight_number, f.aircraft_type, f.destination AS flight_destination
        FROM cargo_shipments c
        LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
        LEFT JOIN uld_containers u ON c.uld_id = u.id
        LEFT JOIN flight_manifests f ON u.flight_id = f.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();

    if (!$cargo) {
        return ['success' => false, 'message' => 'Cargo not found'];
    }

    // Cek jika diblokir oleh AVSEC
    if ($cargo['status'] === 'SUSPECT' && $stage > 4) {
        return [
            'success' => false,
            'message' => 'Kargo ini berstatus SUSPECT di AVSEC (tahap 4). Kargo dikunci di Ruang Khusus Karantina dan memerlukan pemeriksaan fisik sekunder sebelum dapat dilanjutkan.'
        ];
    }
    if ($cargo['status'] === 'RETURNED_TO_TRUCK' && $stage > 4) {
        return [
            'success' => false,
            'message' => 'Kargo ini telah ditolak di AVSEC dan dikembalikan ke armada truk semula. Tidak dapat dilanjutkan ke tahapan penimbangan atau pemuatan.'
        ];
    }

    if ((int)$stage === 4) {
        ensureAvsecDecisionTable($db); // DDL sebelum transaksi dimulai
    }

    // Validasi urutan tahapan agar tetap linear (tidak melompati tahap yang belum selesai)
    $currentStageInt = max(1, (int)$cargo['current_stage']);
    if ($stage > $currentStageInt && $cargo['status'] !== 'LOADED') {
        return [
            'success' => false,
            'message' => "Tahap {$stage} belum dapat diproses. Silakan selesaikan Tahap {$currentStageInt} terlebih dahulu."
        ];
    }

    $db->beginTransaction();

    try {
        switch ($stage) {
            case 1: // TAHAP 1: Verifikasi Kedatangan Truk & Gate Check-In (TMS / TAS Gate — Zero Re-entry)
                $truckId = $cargo['truck_arrival_id'];

                if ($truckId) {
                    $truckPlate = $cargo['truck_plate'] ?: 'B 9482 KXT';
                    $driverName = $cargo['driver_name'] ?: 'Bambang Supriyanto';
                    $transporter = $cargo['transporter_company'] ?: 'PT Intermodal Logistik Sejahtera';
                    $dockSlot = $cargo['dock_slot'] ?: 'DOCK-04';
                    $bookingCode = $cargo['booking_code'] ?: ('TAS-SLOT-' . rand(1000, 9999));

                    $stmtUpdateTruck = $db->prepare("
                        UPDATE truck_arrivals
                        SET status = CASE WHEN status = 'SCHEDULED' THEN 'ARRIVED' ELSE status END,
                            arrival_time = CASE WHEN status = 'SCHEDULED' THEN NOW() ELSE arrival_time END
                        WHERE id = ?
                    ");
                    $stmtUpdateTruck->execute([$truckId]);
                } else {
                    // Fallback aman jika ada kargo lama tanpa truck_arrival_id (tanpa menimpa data kargo)
                    $truckId = 'TRK-' . date('Ymd-His') . '-' . rand(10, 99);
                    $truckPlate = 'B 9482 KXT';
                    $driverName = 'Bambang Supriyanto';
                    $transporter = 'PT Indotruck Multimoda Logistik';
                    $dockSlot = 'DOCK-02';
                    $bookingCode = 'TAS-SLOT-' . rand(1000, 9999);

                    $db->prepare("
                        INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'ARRIVED')
                    ")->execute([$truckId, $truckPlate, $driverName, $transporter, $dockSlot, $bookingCode]);

                    $db->prepare("UPDATE cargo_shipments SET truck_arrival_id = ? WHERE id = ?")->execute([$truckId, $cargoId]);
                }

                // Majukan tahap ke 2 (status ARRIVED) TANPA mengubah/menimpa data master kargo
                $updateCargo = $db->prepare("
                    UPDATE cargo_shipments
                    SET current_stage = CASE WHEN current_stage < 2 THEN 2 ELSE current_stage END,
                        status = CASE WHEN current_stage <= 1 OR status = 'REGISTERED' THEN 'ARRIVED' ELSE status END
                    WHERE id = ?
                ");
                $updateCargo->execute([$cargoId]);

                $stmtTruckRow = $db->prepare("SELECT id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status FROM truck_arrivals WHERE id = ?");
                $stmtTruckRow->execute([$truckId]);
                $verifiedTruck = $stmtTruckRow->fetch();

                $newTruckRecord = $verifiedTruck ?: [
                    'id' => $truckId,
                    'truck_plate' => $truckPlate,
                    'driver_name' => $driverName,
                    'transporter_company' => $transporter,
                    'dock_slot' => $dockSlot,
                    'booking_code' => $bookingCode,
                    'arrival_time' => date('Y-m-d H:i:s'),
                    'status' => 'ARRIVED'
                ];

                // Payload Stage 1 HANYA berisi data yang dibutuhkan oleh TMS (Zero Re-entry & Data Minimization)
                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "event_type" => "TMS_TRUCK_GATE_CHECKIN",
                    "data_source" => "SINGLE_ENTRY_INPUT_KARGO (ZERO_REENTRY)",
                    "filtered_domain" => "TMS_TRANSPORT_AND_DOCK_ONLY",
                    "truck_id" => $truckId,
                    "truck_plate" => $newTruckRecord['truck_plate'],
                    "driver_name" => $newTruckRecord['driver_name'],
                    "transporter_company" => $newTruckRecord['transporter_company'],
                    "assigned_dock" => $newTruckRecord['dock_slot'],
                    "booking_code" => $newTruckRecord['booking_code'],
                    "arrival_time" => $newTruckRecord['arrival_time'],
                    "tas_booking_status" => "CONFIRMED_VALID_TIMESLOT",
                    "gate_barrier_status" => "BARRIER_OPENED",
                    "hardware_specs" => [
                        "rfid_gate_reader" => "TMS UHF Long-Range RFID Reader (902-928 MHz)",
                        "barrier_gate" => "High-Speed Boom Barrier (1.2s opening speed)",
                        "dock_leveler" => "Hydraulic Pit Leveler 10-Ton Capacity"
                    ],
                    "timestamp" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Truk " . $newTruckRecord['truck_plate'] . " (" . $newTruckRecord['driver_name'] . ") berhasil diverifikasi otomatis di gerbang TAS & diarahkan ke " . $newTruckRecord['dock_slot'],
                    "tms_pass_token" => "TAS-TOKEN-" . strtoupper(bin2hex(random_bytes(4))),
                    "next_stage" => "CMS_AIDC_JSON_INGEST"
                ];

                logTransaction($db, $cargoId, 1, "Kedatangan Truk & Booking Slot (TMS/TAS)", "TMS RFID Gate Reader & Barrier Gate", "Intermodal TMS / Truck Appointment System (TAS)", "Verifikasi Gate Otomatis dari Data Input Kargo (Zero Re-entry)", $jsonPayload, $responsePayload);
                break;

            case 2: // TAHAP 2: Pemindaian Data Inbound (JSON Ingestion)
                $scanTime = date('Y-m-d H:i:s');
                if (!empty($cargo['truck_arrival_id'])) {
                    $db->prepare("UPDATE truck_arrivals SET status = 'UNLOADING' WHERE id = ? AND status IN ('SCHEDULED', 'ARRIVED')")
                       ->execute([$cargo['truck_arrival_id']]);
                }

                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = CASE WHEN current_stage < 3 THEN 3 ELSE current_stage END,
                        status = CASE WHEN current_stage <= 2 OR status IN ('REGISTERED', 'ARRIVED') THEN 'SCANNED' ELSE status END,
                        scan_time = ? 
                    WHERE id = ?
                ");
                $update->execute([$scanTime, $cargoId]);

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "AIDC_INBOUND_JSON_INGEST",
                    "device_id" => "EDGE-AIDC-SCANNER-01",
                    "stream_protocol" => "GS1_DIGITAL_LINK_JSON / REST_MQTT",
                    "edge_telemetry" => [
                        "conveyor_speed" => "2.5 m/s",
                        "carrier_freq" => "865.7 MHz (EPC Gen2 TDS 2.0)",
                        "rssi_dbm" => -41.5,
                        "confidence" => "99.98%"
                    ],
                    "gs1_data" => [
                        "ai_00_sscc" => $cargo['sscc'],
                        "ai_01_gtin" => $cargo['gtin'],
                        "ai_10_batch_no" => $cargo['batch_no'],
                        "ai_17_expiry_date" => $cargo['expiry_date'],
                        "quantity_koli" => (int)$cargo['quantity'],
                        "declared_weight_kg" => (float)$cargo['declared_weight_kg']
                    ],
                    "ingested_at" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Payload JSON AIDC (SSCC-18) berhasil diidentifikasi dan dikirim ke CMS",
                    "cms_ingest_token" => "CMS-TOKEN-" . strtoupper(bin2hex(random_bytes(4))),
                    "next_action" => "CMS_REGISTRATION"
                ];

                logTransaction($db, $cargoId, 2, "Pemindaian Data Inbound (JSON Ingestion)", "AIDC Inbound Edge Gateway (JSON Ingest)", "Cargo Management Software (CMS)", "Ingest Data Pemindaian JSON (SSCC/GTIN) ke CMS", $jsonPayload, $responsePayload);
                break;

            case 3: // TAHAP 3: Registrasi Data & e-AWB Matching di CMS
                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = CASE WHEN current_stage < 4 THEN 4 ELSE current_stage END,
                        status = CASE WHEN current_stage <= 3 OR status IN ('REGISTERED', 'ARRIVED', 'SCANNED') THEN 'SECURITY_CHECK' ELSE status END
                    WHERE id = ?
                ");
                $update->execute([$cargoId]);

                if (!empty($cargo['truck_arrival_id'])) {
                    $pendingStmt = $db->prepare("SELECT COUNT(*) FROM cargo_shipments WHERE truck_arrival_id = ? AND current_stage < 3");
                    $pendingStmt->execute([$cargo['truck_arrival_id']]);
                    if ((int)$pendingStmt->fetchColumn() === 0) {
                        $db->prepare("UPDATE truck_arrivals SET status = 'COMPLETED' WHERE id = ? AND status IN ('SCHEDULED', 'ARRIVED', 'UNLOADING')")
                           ->execute([$cargo['truck_arrival_id']]);
                    }
                }

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "CMS_AWB_MATCHING",
                    "action" => "VALIDATE_ELECTRONIC_AIR_WAYBILL",
                    "e_awb_number" => $cargo['awb_number'],
                    "matched_sscc" => $cargo['sscc'],
                    "shipment_details" => [
                        "shipper" => $cargo['shipper'],
                        "consignee" => $cargo['consignee'],
                        "commodity" => $cargo['description'],
                        "commodity_class" => $cargo['commodity_type'],
                        "declared_pieces" => (int)$cargo['quantity'],
                        "declared_weight_kg" => (float)$cargo['declared_weight_kg'],
                        "origin" => "CGK",
                        "destination" => "DPS"
                    ],
                    "timestamp" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "cms_status" => "AWB_VERIFIED",
                    "routing_instruction" => "MOVE_TO_AVSEC_XRAY_LANE_02",
                    "priority_level" => ($cargo['commodity_type'] === 'PHARMA' ? "URGENT_COLD_CHAIN" : "STANDARD")
                ];

                logTransaction($db, $cargoId, 3, "Registrasi Data di CMS", "CMS Core Server", "Cargo Management Software (CMS)", "Pencocokan e-AWB & Alokasi Alur Pemeriksaan Keamanan", $jsonPayload, $responsePayload);
                break;

            case 4: // TAHAP 4: Pemeriksaan Keamanan X-Ray (AVSEC System + AI Agent LangGraph)
                $isRelease = ($avsecDecision === 'RELEASE');
                $isReturn = ($avsecDecision === 'RETURN_TO_TRUCK');
                $agentResult = null;
                $densityReading = null;

                if ($isReturn) {
                    $isSuspect = false;
                    $statusResult = 'RETURNED_TO_TRUCK';
                    $notes = 'Kargo Ditolak di AVSEC dan dikembalikan ke armada truk semula (' . ($cargo['truck_plate'] ?? 'Truk Pengangkut') . '). Dibatalkan dari manifes penerbangan.';
                    $densityReading = 'Threat Confirmed / Rejected';
                    $csdNum = null;
                } else if ($isRelease) {
                    $isSuspect = false;
                    $statusResult = 'CLEARED';
                    $notes = 'Pemeriksaan fisik sekunder (Hand Search & ETD) selesai di Ruang Khusus Karantina AVSEC (Human-in-the-Loop). Dinyatakan AMAN dan dirilis.';
                    $densityReading = 'Standard Organic (Z=7-8) / ETD Cleared';
                    $csdNum = 'CSD-ID-' . date('Ymd') . '-SEC-' . rand(1000, 9999);
                } else {
                    $scenarioParam = ($avsecDecision === 'SUSPECT') ? 'SUSPECT' : 'CLEARED';
                    $agentResult = avsecAgentInspect([
                        'cargo_id' => $cargoId,
                        'awb' => $cargo['awb_number'] ?? '',
                        'description' => $cargo['description'] ?? '',
                        'commodity_type' => $cargo['commodity_type'] ?? 'GENERAL_CARGO',
                        'quantity' => (int)($cargo['quantity'] ?? 1),
                        'declared_weight_kg' => (float)($cargo['declared_weight_kg'] ?? 0),
                        'shipper' => $cargo['shipper'] ?? '',
                        'consignee' => $cargo['consignee'] ?? '',
                    ], $scenarioParam);
                    $statusResult = ($agentResult['decision'] === 'SUSPECT') ? 'SUSPECT' : 'CLEARED';
                    $isSuspect = ($statusResult === 'SUSPECT');
                    $agentScan = $agentResult['perception']['xray']['summary'] ?? [];
                    $densityReading = isset($agentScan['mean_z_eff'])
                        ? ('Z-eff ' . $agentScan['mean_z_eff'] . ' / ' . ($agentScan['max_density_gcm3'] ?? '-') . ' g/cm3')
                        : ($isSuspect ? 'High Density Atomic Number Z-effective > 20' : 'Standard Organic (Z=7-8) / Light Metal');
                    $notes = '[AI AGENT ' . ($agentResult['engine'] ?? 'langgraph-js') . '] ' . ($agentResult['headline'] ?? $statusResult);
                    $csdNum = $isSuspect ? null : ('CSD-ID-' . date('Ymd') . '-' . rand(1000, 9999));
                    saveAvsecDecision($db, $cargoId, $agentResult);
                }

                $inspectorId = $agentResult ? 'AI-AGENT-AVSEC' : 'AVSEC-OPR-07';

                // Hapus check sebelumnya jika ada
                $del = $db->prepare("DELETE FROM security_checks WHERE cargo_id = ?");
                $del->execute([$cargoId]);

                $stmtSec = $db->prepare("
                    INSERT INTO security_checks (
                        id, cargo_id, scanner_type, xray_result, inspector_id,
                        csd_number, check_time, density_reading, notes
                    ) VALUES (?, ?, 'Dual-View High-Energy X-Ray (180x180cm, 320kV)', ?, ?, ?, NOW(), ?, ?)
                ");
                $stmtSec->execute([
                    'SEC-' . uniqid(),
                    $cargoId,
                    $statusResult,
                    $inspectorId,
                    $csdNum,
                    $densityReading,
                    $notes
                ]);

                $nextStageAvsec = 5;
                if ($isSuspect) {
                    $nextStageAvsec = 4;
                } else if ($isReturn) {
                    $nextStageAvsec = 1;
                }
                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = ?, status = ? 
                    WHERE id = ?
                ");
                $update->execute([$nextStageAvsec, $statusResult, $cargoId]);

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "SECURITY_SCREENING_EVALUATION",
                    "cargo_id" => $cargoId,
                    "awb_number" => $cargo['awb_number'],
                    "truck_source" => [
                        "truck_plate" => $cargo['truck_plate'] ?? 'B 8888 KGO',
                        "driver" => $cargo['driver_name'] ?? 'Bambang Pamungkas'
                    ],
                    "scanner_id" => "XRAY-DUALVIEW-01",
                    "tunnel_dimensions" => "180 cm x 180 cm (320kV)",
                    "penetration_steel" => "80 mm",
                    "inspection_result" => $statusResult,
                    "inspector_badge" => $inspectorId,
                    "csd_certificate" => $csdNum,
                    "quarantine_room_status" => $isSuspect ? "LOCKED_IN_VAULT" : ($isReturn ? "EXPELLED_TO_TRUCK" : "CLEAR_PASS"),
                    "ai_agent" => $agentResult ? [
                        "engine" => $agentResult['engine'] ?? null,
                        "confidence" => $agentResult['confidence'] ?? null,
                        "risk_score" => $agentResult['risk_score'] ?? null,
                        "mismatch" => $agentResult['mismatch'] ?? false,
                        "headline" => $agentResult['headline'] ?? null,
                        "actions" => $agentResult['actions'] ?? []
                    ] : null,
                    "timestamp" => date('c')
                ];

                $httpCode = $isSuspect ? 403 : 200;
                $responsePayload = [
                    "status" => $isSuspect ? "403_FORBIDDEN" : "200_OK",
                    "security_clearance" => $statusResult,
                    "action_required" => $isSuspect ? "HOLD_IN_QUARANTINE_VAULT" : ($isReturn ? "RETURN_CARGO_TO_TRUCK" : "PROCEED_TO_WEIGHING_STATION"),
                    "alert_triggered" => $isSuspect
                ];

                logTransaction($db, $cargoId, 4, "Pemeriksaan Keamanan X-Ray", "Dual-View X-Ray Scanner (AVSEC)", "Aviation Security Management System", "Screening Sinar-X & Status CSD", $jsonPayload, $responsePayload, $httpCode);
                break;

            case 5: // TAHAP 5: Penimbangan Kargo (Smart ULD Weighing Station)
                $declared = (float)$cargo['declared_weight_kg'];
                $jitter = ($declared >= 20) ? rand(-2, 4) : round(rand(-5, 5) / 10, 1); // selisih kg realistis
                $actual = max(0.5, round($declared + $jitter, 1));
                $discrepancy = round($actual - $declared, 2);
                $maxToleranceAllowed = $declared * 0.05; // 5% toleransi
                $tolerance = abs($discrepancy) <= $maxToleranceAllowed ? 'WITHIN_TOLERANCE' : 'DISCREPANCY_WARNING';

                // Parameter Batas Beban Kargo Pesawat
                $maxUldCapacity = 1588.0; // Maksimal kontainer AKE (atau 6804.0 PMC)
                $maxAircraftCapacity = 18500.0; // Kapasitas muatan Boeing 737-800F GA-707
                $isApprovedForFlight = ($actual <= $maxUldCapacity);

                // Hapus record lama jika ada
                $del = $db->prepare("DELETE FROM weight_records WHERE cargo_id = ?");
                $del->execute([$cargoId]);

                $stmtWeight = $db->prepare("
                    INSERT INTO weight_records (
                        id, cargo_id, declared_weight_kg, actual_weight_kg, weight_discrepancy_kg,
                        scale_device_id, weigh_time, tolerance_status
                    ) VALUES (?, ?, ?, ?, ?, 'SCALE-FLOOR-ULD-01', NOW(), ?)
                ");
                $stmtWeight->execute([
                    'WGT-' . uniqid(),
                    $cargoId,
                    $declared,
                    $actual,
                    $discrepancy,
                    $tolerance
                ]);

                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 6, status = 'WEIGHED', actual_weight_kg = ? 
                    WHERE id = ?
                ");
                $update->execute([$actual, $cargoId]);

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "CARGO_WEIGHT_ACQUISITION",
                    "device_id" => "SCALE-FLOOR-ULD-01",
                    "device_specs" => [
                        "platform" => "3.5m x 2.5m Steel Floor Scale",
                        "max_capacity_ton" => 15,
                        "accuracy_kg" => 1.0,
                        "sensor_protection" => "IP68 Hermetic"
                    ],
                    "weight_data" => [
                        "cargo_id" => $cargoId,
                        "awb_number" => $cargo['awb_number'],
                        "truck_plate" => $cargo['truck_plate'] ?? 'B 8888 KGO',
                        "declared_gross_weight_kg" => $declared,
                        "actual_scale_weight_kg" => $actual,
                        "discrepancy_kg" => $discrepancy,
                        "tolerance_status" => $tolerance . " (Max ±5%)"
                    ],
                    "flight_loading_criteria" => [
                        "max_uld_gross_weight_kg" => $maxUldCapacity,
                        "max_aircraft_payload_kg" => $maxAircraftCapacity,
                        "aircraft_type" => "Boeing 737-800F (GA-707)",
                        "criteria_status" => $isApprovedForFlight ? "APPROVED_FOR_FLIGHT_LOADING" : "OVERWEIGHT_REJECTED"
                    ],
                    "weighed_at" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Data berat aktual berhasil disinkronkan ke CMS & Modul Weight & Balance",
                    "allow_uld_buildup" => true,
                    "criteria_result" => "PASSED"
                ];

                logTransaction($db, $cargoId, 5, "Penimbangan Kargo", "Smart ULD Floor Weighing Station", "CMS & Weight/Balance Engine", "Pembacaan Sensor Load Cell & Sinkronisasi Berat Aktual", $jsonPayload, $responsePayload);
                break;

            case 6: // TAHAP 6: Build-Up ULD & Kalkulasi Weight/Balance
                // Cari kontainer ULD yang tersedia untuk penerbangan aktif
                $stmtUld = $db->query("
                    SELECT u.*, f.flight_number, f.aircraft_type, f.destination 
                    FROM uld_containers u
                    JOIN flight_manifests f ON u.flight_id = f.id
                    WHERE u.status IN ('EMPTY', 'LOADING')
                    ORDER BY u.current_weight_kg ASC
                    LIMIT 1
                ");
                $targetUld = $stmtUld->fetch();

                if (!$targetUld) {
                    $stmtUldAny = $db->query("
                        SELECT u.*, f.flight_number, f.aircraft_type, f.destination 
                        FROM uld_containers u
                        JOIN flight_manifests f ON u.flight_id = f.id
                        ORDER BY u.current_weight_kg ASC
                        LIMIT 1
                    ");
                    $targetUld = $stmtUldAny->fetch();
                }

                if (!$targetUld) {
                    $targetUld = [
                        'id' => 'ULD-AKE-12345',
                        'uld_code' => 'AKE-12345-GA',
                        'uld_type' => 'AKE',
                        'max_weight_kg' => 1588.00,
                        'current_weight_kg' => 75.00,
                        'flight_number' => 'GA-707',
                        'compartment' => 'AFT-LOWER-1'
                    ];
                }

                $cargoWeight = (float)($cargo['actual_weight_kg'] ?? $cargo['declared_weight_kg']);
                $newUldWeight = (float)$targetUld['current_weight_kg'] + $cargoWeight;
                $uldStatus = ($newUldWeight >= ((float)$targetUld['max_weight_kg'] * 0.85)) ? 'FULL' : 'LOADING';

                $updateUld = $db->prepare("
                    UPDATE uld_containers 
                    SET current_weight_kg = ?, status = ? 
                    WHERE id = ?
                ");
                $updateUld->execute([$newUldWeight, $uldStatus, $targetUld['id']]);

                $updateCargo = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 7, status = 'ALLOCATED', uld_id = ? 
                    WHERE id = ?
                ");
                $updateCargo->execute([$targetUld['id'], $cargoId]);

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "ULD_CONSOLIDATION_AND_LOAD_PLANNING",
                    "cargo_id" => $cargoId,
                    "target_uld" => [
                        "uld_code" => $targetUld['uld_code'],
                        "uld_type" => $targetUld['uld_type'],
                        "tare_weight_kg" => 75.0,
                        "cargo_weight_added_kg" => $cargoWeight,
                        "new_gross_uld_weight_kg" => $newUldWeight,
                        "max_capacity_kg" => (float)$targetUld['max_weight_kg'],
                        "utilization_percent" => round(($newUldWeight / (float)$targetUld['max_weight_kg']) * 100, 1)
                    ],
                    "weight_and_balance_engine" => [
                        "aircraft" => $targetUld['aircraft_type'] ?? 'Boeing 737-800F',
                        "assigned_compartment" => $targetUld['compartment'],
                        "center_of_gravity_index" => 28.4,
                        "within_safety_envelope" => true,
                        "floor_load_limit_status" => "WITHIN_STRUCTURAL_LIMIT"
                    ],
                    "timestamp" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Kargo berhasil dikonsolidasikan ke dalam kontainer ULD " . $targetUld['uld_code'],
                    "load_position_locked" => true
                ];

                logTransaction($db, $cargoId, 6, "Build-Up ULD & Kalkulasi Weight & Balance", "Automated Cargo Handling & ULD Dock", "Weight & Balance System / Load Planning", "Konsolidasi Kargo ke ULD & Optimasi Titik Keseimbangan Pesawat", $jsonPayload, $responsePayload);
                break;

            case 7: // TAHAP 7: Update Manifes Penerbangan & Pemuatan ke Pesawat
                // Ambil ULD dan Flight
                $stmtFlight = $db->prepare("
                    SELECT f.*, u.uld_code, u.current_weight_kg as uld_final_weight 
                    FROM cargo_shipments c
                    JOIN uld_containers u ON c.uld_id = u.id
                    JOIN flight_manifests f ON u.flight_id = f.id
                    WHERE c.id = ?
                ");
                $stmtFlight->execute([$cargoId]);
                $flightInfo = $stmtFlight->fetch();

                if ($flightInfo) {
                    $newFlightWeight = (float)$flightInfo['current_total_weight_kg'] + (float)($cargo['actual_weight_kg'] ?? $cargo['declared_weight_kg']);
                    
                    $db->prepare("
                        UPDATE flight_manifests 
                        SET current_total_weight_kg = ?, status = 'CLOSED' 
                        WHERE id = ?
                    ")->execute([$newFlightWeight, $flightInfo['id']]);

                    $db->prepare("
                        UPDATE uld_containers 
                        SET status = 'LOADED' 
                        WHERE id = ?
                    ")->execute([$cargo['uld_id']]);
                }

                $updateCargo = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 7, status = 'LOADED' 
                    WHERE id = ?
                ");
                $updateCargo->execute([$cargoId]);

                $loadsheetId = 'LS-' . ($flightInfo['flight_number'] ?? 'GA707') . '-' . date('YmdHi');

                $jsonPayload = [
                    "cargo_payload_id" => "CMP-" . $cargoId,
                    "master_sscc" => $cargo['sscc'],
                    "event_type" => "FLIGHT_MANIFEST_FINALIZATION",
                    "flight_manifest_id" => $flightInfo['id'] ?? 'FL-GA707-2026',
                    "flight_number" => $flightInfo['flight_number'] ?? 'GA-707',
                    "loadsheet_number" => $loadsheetId,
                    "aircraft_type" => $flightInfo['aircraft_type'] ?? 'Boeing 737-800F',
                    "route" => "CGK -> " . ($flightInfo['destination'] ?? 'DPS'),
                    "loaded_uld_list" => [
                        [
                            "uld_code" => $flightInfo['uld_code'] ?? 'AKE-12345-GA',
                            "final_weight_kg" => (float)($flightInfo['uld_final_weight'] ?? 850.0),
                            "contains_cargo_awb" => $cargo['awb_number'],
                            "ramp_loading_status" => "TRANSFERRED_TO_AIRCRAFT_BELLY"
                        ]
                    ],
                    "total_cargo_payload_kg" => (float)($newFlightWeight ?? 1250.0),
                    "departure_clearance" => "APPROVED",
                    "timestamp" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Manifes penerbangan final telah diterbitkan. Loadsheet resmi terkirim ke kokpit & flight ops.",
                    "aircraft_ramp_status" => "READY_FOR_PUSHBACK"
                ];

                logTransaction($db, $cargoId, 7, "Update Manifes & Loading ke Pesawat", "Airport Ramp Dolly & Cargo High-Loader", "Cargo Management Software (CMS) & Flight Ops", "Penerbitan Loadsheet Final & Pemuatan ULD ke Kompartemen Pesawat", $jsonPayload, $responsePayload);
                break;

            default:
                return ['success' => false, 'message' => 'Tahapan ' . $stage . ' tidak valid'];
        }

        // Selalu ambil data kargo paling mutakhir beserta info truk, ULD, flight, dan AVSEC
        $stmtCargoAfter = $db->prepare("
            SELECT c.*, 
                   t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code, t.arrival_time, t.status as truck_status,
                   u.uld_code, u.uld_type, u.compartment, u.current_weight_kg as uld_weight, u.max_weight_kg as uld_max_weight,
                   f.flight_number, f.destination as flight_destination, f.aircraft_type, f.departure_time, f.max_cargo_weight_kg as flight_max_weight,
                   s.xray_result, s.inspector_id, s.csd_number, s.check_time, s.notes as avsec_notes
            FROM cargo_shipments c
            LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
            LEFT JOIN uld_containers u ON c.uld_id = u.id
            LEFT JOIN flight_manifests f ON u.flight_id = f.id
            LEFT JOIN security_checks s ON s.cargo_id = c.id
            WHERE c.id = ?
        ");
        $stmtCargoAfter->execute([$cargoId]);
        $updatedCargo = $stmtCargoAfter->fetch();

        $tmsData = [
            'truck_id' => $updatedCargo['truck_arrival_id'],
            'truck_plate' => $updatedCargo['truck_plate'],
            'driver_name' => $updatedCargo['driver_name'],
            'transporter_company' => $updatedCargo['transporter_company'],
            'dock_slot' => $updatedCargo['dock_slot'],
            'booking_code' => $updatedCargo['booking_code'],
            'arrival_time' => $updatedCargo['arrival_time'],
            'truck_status' => $updatedCargo['truck_status'] ?? 'ARRIVED',
            'gate_verified' => ((int)$updatedCargo['current_stage'] >= 2 || $updatedCargo['status'] !== 'REGISTERED'),
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

        // Sinkronkan Master JSON Payload terbaru ke database
        $masterPayload = syncCargoPayload($db, $cargoId);

        $db->commit();
        $res = [
            'success' => true,
            'message' => isset($agentResult)
                ? ('Tahap 4 (AI Agent AVSEC): ' . ($agentResult['headline'] ?? 'Diproses'))
                : ('Tahap ' . $stage . ' berhasil diproses'),
            'stage' => $stage,
            'cargo_id' => $cargoId,
            'cargo' => $updatedCargo,
            'tms_data' => $tmsData,
            'tms_trucks' => $tmsTrucks,
            'tms_total_trucks' => $tmsTotalTrucks,
            'payload' => $masterPayload
        ];
        if (isset($agentResult)) {
            $res['agent'] = $agentResult;
        }
        if (isset($newTruckRecord)) {
            $res['truck'] = $newTruckRecord;
        }
        return $res;

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function logTransaction($db, $cargoId, $stage, $stageName, $hardware, $software, $action, $payload, $response, $httpStatus = 200) {
    $stmt = $db->prepare("
        INSERT INTO scan_logs (
            id, cargo_id, stage, stage_name, hardware_device,
            software_system, action, json_payload, response_payload,
            http_status, timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        'LOG-' . uniqid(),
        $cargoId,
        $stage,
        $stageName,
        $hardware,
        $software,
        $action,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        $httpStatus
    ]);
}
