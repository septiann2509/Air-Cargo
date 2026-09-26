<?php
// api/simulate.php
require_once __DIR__ . '/../config/database.php';

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
    $stmt = $db->prepare("SELECT * FROM cargo_shipments WHERE id = ?");
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();

    if (!$cargo) {
        jsonResponse(['success' => false, 'message' => 'Cargo not found'], 404);
    }

    if ($actionType === 'auto_run') {
        // Jalankan dari stage saat ini sampai stage 7 secara berurutan
        $results = [];
        for ($s = max(2, $cargo['current_stage'] + 1); $s <= 7; $s++) {
            $res = executeStage($db, $cargoId, $s, 'CLEARED');
            $results[] = $res;
            if (!$res['success']) {
                break;
            }
        }
        jsonResponse([
            'success' => true,
            'message' => 'Auto-run simulasi selesai hingga tahap akhir',
            'history' => $results
        ]);
    } else {
        // Jalankan single stage tertentu
        $suspectParam = ($actionType === 'force_suspect') ? 'SUSPECT' : ($input['avsec_decision'] ?? 'CLEARED');
        $stageToRun = ($targetStage > 0) ? $targetStage : ($cargo['current_stage'] + 1);
        
        $res = executeStage($db, $cargoId, $stageToRun, $suspectParam);
        jsonResponse($res);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Eksekutor Tiap Tahapan Simulasi
 */
function executeStage($db, $cargoId, $stage, $avsecDecision = 'CLEARED') {
    $stmt = $db->prepare("SELECT * FROM cargo_shipments WHERE id = ?");
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();

    if (!$cargo) {
        return ['success' => false, 'message' => 'Cargo not found'];
    }

    // Cek jika diblokir oleh AVSEC
    if ($cargo['status'] === 'SUSPECT' && $stage > 4) {
        return [
            'success' => false,
            'message' => 'Kargo ini berstatus SUSPECT di AVSEC. Dilarang melanjutkan ke penimbangan atau pemuatan!'
        ];
    }

    $db->beginTransaction();

    try {
        switch ($stage) {
            case 2: // TAHAP 2: Scan RFID / Barcode di Gerbang Masuk
                $scanTime = date('Y-m-d H:i:s');
                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 2, status = 'SCANNED', scan_time = ? 
                    WHERE id = ?
                ");
                $update->execute([$scanTime, $cargoId]);

                $jsonPayload = [
                    "event_type" => "RFID_AIDC_GATE_SCAN",
                    "device_id" => "SCANNER-GATE-CONVEYOR-01",
                    "hardware_specs" => [
                        "type" => "High-Speed Barcode/RFID Tunnel Scanner",
                        "antenna" => "UHF Impinj Indy R2000 865-868MHz",
                        "conveyor_speed" => "2.5 m/s",
                        "ip_rating" => "IP65"
                    ],
                    "gs1_data" => [
                        "ai_00_sscc" => $cargo['sscc'],
                        "ai_01_gtin" => $cargo['gtin'],
                        "ai_10_batch_no" => $cargo['batch_no'],
                        "ai_17_expiry_date" => $cargo['expiry_date'],
                        "quantity_koli" => (int)$cargo['quantity']
                    ],
                    "scanned_at" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Tag SSCC berhasil diidentifikasi dan dikirim ke CMS",
                    "cms_ingest_token" => "CMS-TOKEN-" . strtoupper(bin2hex(random_bytes(4))),
                    "next_action" => "CMS_REGISTRATION"
                ];

                logTransaction($db, $cargoId, 2, "Scan RFID/Barcode Gerbang Masuk", "High-Speed RFID/Barcode Scanner", "Cargo Management Software (CMS)", "Pembacaan Massal Tag GS1 AIDC (SSCC-18) di Gerbang Masuk", $jsonPayload, $responsePayload);
                break;

            case 3: // TAHAP 3: Registrasi Data & e-AWB Matching di CMS
                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 3, status = 'SECURITY_CHECK' 
                    WHERE id = ?
                ");
                $update->execute([$cargoId]);

                $jsonPayload = [
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

            case 4: // TAHAP 4: Pemeriksaan Keamanan X-Ray (AVSEC System)
                $isSuspect = ($avsecDecision === 'SUSPECT');
                $statusResult = $isSuspect ? 'SUSPECT' : 'CLEARED';
                $csdNum = $isSuspect ? null : ('CSD-ID-' . date('Ymd') . '-' . rand(1000, 9999));
                $inspectorId = 'AVSEC-OPR-07';
                $notes = $isSuspect ? 'Peringatan Sistem: Terdeteksi kepadatan tinggi mencurigakan (Potensi Dangerous Goods / Baterai Tanpa Izin IATA)' : 'Pemeriksaan tembus pandang normal. Tidak ditemukan bahan berbahaya.';

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
                    $isSuspect ? 'High Density Atomic Number Z-effective > 20' : 'Standard Organic (Z=7-8) / Light Metal',
                    $notes
                ]);

                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 4, status = ? 
                    WHERE id = ?
                ");
                $update->execute([$statusResult, $cargoId]);

                $jsonPayload = [
                    "event_type" => "SECURITY_SCREENING_EVALUATION",
                    "cargo_id" => $cargoId,
                    "awb_number" => $cargo['awb_number'],
                    "scanner_id" => "XRAY-DUALVIEW-01",
                    "tunnel_dimensions" => "180 cm x 180 cm",
                    "penetration_steel" => "80 mm",
                    "inspection_result" => $statusResult,
                    "inspector_badge" => $inspectorId,
                    "csd_certificate" => $csdNum,
                    "timestamp" => date('c')
                ];

                $httpCode = $isSuspect ? 403 : 200;
                $responsePayload = [
                    "status" => $isSuspect ? "403_FORBIDDEN" : "200_OK",
                    "security_clearance" => $statusResult,
                    "action_required" => $isSuspect ? "HOLD_AND_ISOLATE_CARGO" : "PROCEED_TO_WEIGHING_STATION",
                    "alert_triggered" => $isSuspect
                ];

                logTransaction($db, $cargoId, 4, "Pemeriksaan Keamanan X-Ray", "Dual-View X-Ray Scanner (AVSEC)", "Aviation Security Management System", "Screening Sinar-X & Penerbitan Sertifikat Keamanan CSD", $jsonPayload, $responsePayload, $httpCode);
                break;

            case 5: // TAHAP 5: Penimbangan Kargo (Smart ULD Weighing Station)
                // Hitung berat aktual dengan deviasi realistis (±0.5% - 2%)
                $declared = (float)$cargo['declared_weight_kg'];
                $jitter = rand(-3, 5); // selisih kg
                $actual = max(10, $declared + $jitter);
                $discrepancy = $actual - $declared;
                $tolerance = abs($discrepancy) <= 10 ? 'WITHIN_TOLERANCE' : 'DISCREPANCY_WARNING';

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
                    SET current_stage = 5, status = 'WEIGHED', actual_weight_kg = ? 
                    WHERE id = ?
                ");
                $update->execute([$actual, $cargoId]);

                $jsonPayload = [
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
                        "declared_gross_weight_kg" => $declared,
                        "actual_scale_weight_kg" => $actual,
                        "discrepancy_kg" => $discrepancy,
                        "status" => $tolerance
                    ],
                    "weighed_at" => date('c')
                ];

                $responsePayload = [
                    "status" => "200_OK",
                    "message" => "Data berat aktual berhasil disinkronkan ke CMS & Modul Weight & Balance",
                    "allow_uld_buildup" => true
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
                    // Fallback create or pick any ULD
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
                    SET current_stage = 6, status = 'ALLOCATED', uld_id = ? 
                    WHERE id = ?
                ");
                $updateCargo->execute([$targetUld['id'], $cargoId]);

                $jsonPayload = [
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

        $db->commit();
        return [
            'success' => true,
            'message' => 'Tahap ' . $stage . ' berhasil diproses',
            'stage' => $stage,
            'cargo_id' => $cargoId
        ];

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
