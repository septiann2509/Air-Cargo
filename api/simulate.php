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
        $truckData = $input['truck_data'] ?? null;
        
        $res = executeStage($db, $cargoId, $stageToRun, $suspectParam, $truckData);
        jsonResponse($res);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Helper: Hitung GS1 SSCC 18-digit dengan valid Modulo-10 Check Digit
 */
function generateSsccFromSeed($seedStr) {
    $seed = abs(crc32($seedStr));
    $prefix = '389912' . str_pad(($seed % 900000) + 100000, 6, '0', STR_PAD_LEFT);
    $serial = str_pad(($seed % 90000) + 10000, 5, '0', STR_PAD_LEFT);
    $sscc17 = $prefix . $serial;
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        $digit = (int)$sscc17[$i];
        $sum += ($i % 2 === 0) ? ($digit * 3) : $digit;
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $sscc17 . $checkDigit;
}

/**
 * Helper: Menentukan manifes muatan kargo spesifik sesuai armada truk yang tiba
 */
function getCargoForTruck($truckPlate, $driverName, $transporter) {
    $plateClean = strtoupper(str_replace(' ', '', $truckPlate));
    if ($plateClean === 'B8888KGO') {
        return [
            'awb' => '126-85258013',
            'desc' => 'Pakaian & Tekstil Premium Ekspor',
            'comm_type' => 'GENERAL_CARGO',
            'qty' => 50,
            'declared_weight' => 350.00,
            'shipper' => 'PT Logistik Nusantara',
            'consignee' => 'Bali Distribution Center',
            'batch' => 'LOT-TEX-8821',
            'gtin' => '08997193267836'
        ];
    } else if ($plateClean === 'B9182KXA') {
        return [
            'awb' => '126-91824401',
            'desc' => 'Farmasi, Vaksin & Alat Medis',
            'comm_type' => 'PHARMA',
            'qty' => 30,
            'declared_weight' => 185.00,
            'shipper' => 'Bio Farma BioTech',
            'consignee' => 'Bali Medika Hospital',
            'batch' => 'LOT-VAC-9182',
            'gtin' => '08998304073856'
        ];
    } else if ($plateClean === 'B4412PQL') {
        return [
            'awb' => '126-44128833',
            'desc' => 'Komponen Elektronik & Semikonduktor',
            'comm_type' => 'ELECTRONICS',
            'qty' => 60,
            'declared_weight' => 420.00,
            'shipper' => 'PT Sat Nusapersada Batam',
            'consignee' => 'Denpasar Electronics Hub',
            'batch' => 'LOT-ELC-4412',
            'gtin' => '08991204412993'
        ];
    } else if ($plateClean === 'B7777WXY') {
        return [
            'awb' => '126-77771290',
            'desc' => 'Buah Segar & Komoditas Perishable',
            'comm_type' => 'PERISHABLE',
            'qty' => 45,
            'declared_weight' => 260.00,
            'shipper' => 'PT Nusantara Fresh Produce',
            'consignee' => 'Bali Agro Trade',
            'batch' => 'LOT-AGR-7777',
            'gtin' => '08996577771204'
        ];
    } else {
        $seed = abs(crc32($plateClean));
        $awbNum = '126-' . str_pad(($seed % 90000000) + 10000000, 8, '0', STR_PAD_LEFT);
        $commCatalog = [
            ['desc' => 'Pakaian & Garmen Ekspor', 'type' => 'GENERAL_CARGO', 'weight' => 320.0, 'qty' => 40, 'shipper' => 'PT Global Tekstil Mandiri'],
            ['desc' => 'Vaksin & Farmasi Cold Chain', 'type' => 'PHARMA', 'weight' => 195.0, 'qty' => 25, 'shipper' => 'PT Kimia Farma Trading'],
            ['desc' => 'Komponen Otomotif & Spareparts', 'type' => 'GENERAL_CARGO', 'weight' => 480.0, 'qty' => 55, 'shipper' => 'PT Astra Komponen Otomotif'],
            ['desc' => 'Perangkat Elektronik & Komputer', 'type' => 'ELECTRONICS', 'weight' => 380.0, 'qty' => 50, 'shipper' => 'PT Indonesia Polytron Global'],
            ['desc' => 'Buah & Sayuran Segar Ekspor', 'type' => 'PERISHABLE', 'weight' => 240.0, 'qty' => 35, 'shipper' => 'PT Segar Alam Semesta']
        ];
        $picked = $commCatalog[$seed % count($commCatalog)];
        return [
            'awb' => $awbNum,
            'desc' => $picked['desc'],
            'comm_type' => $picked['type'],
            'qty' => $picked['qty'],
            'declared_weight' => $picked['weight'],
            'shipper' => !empty($transporter) ? ('Shipper Rekanan ' . $transporter) : $picked['shipper'],
            'consignee' => 'Bali Distribution Center',
            'batch' => 'LOT-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $plateClean) . 'CGO', 0, 3)) . '-' . ($seed % 9000 + 1000),
            'gtin' => '0899' . str_pad(($seed % 9000000000) + 1000000000, 10, '0', STR_PAD_LEFT)
        ];
    }
}

/**
 * Eksekutor Tiap Tahapan Simulasi
 */
function executeStage($db, $cargoId, $stage, $avsecDecision = 'CLEARED', $truckData = null) {
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
            'message' => 'Kargo ini berstatus SUSPECT di AVSEC (tahap 4). Kargo dikunci di Ruang Khusus Karantina dan memerlukan pemeriksaan fisik sekunder sebelum dapat dilanjutkan.'
        ];
    }
    if ($cargo['status'] === 'RETURNED_TO_TRUCK' && $stage > 4) {
        return [
            'success' => false,
            'message' => 'Kargo ini telah ditolak di AVSEC dan dikembalikan ke armada truk semula. Tidak dapat dilanjutkan ke tahapan penimbangan atau pemuatan.'
        ];
    }

    $db->beginTransaction();

    try {
        switch ($stage) {
            case 1: // TAHAP 1: Kedatangan Truk & Booking Slot (TMS / TAS Gate)
                // Ambil data input dari staff atau gunakan data default
                $truckPlate = !empty($truckData['truck_plate']) ? trim($truckData['truck_plate']) : 'B 8888 KGO';
                $driverName = !empty($truckData['driver_name']) ? trim($truckData['driver_name']) : 'Bambang Pamungkas';
                $transporter = !empty($truckData['transporter_company']) ? trim($truckData['transporter_company']) : 'PT Angkasa Pura Logistik';
                $dockSlot = !empty($truckData['dock_slot']) ? trim($truckData['dock_slot']) : 'DOCK-02';
                $bookingCode = !empty($truckData['booking_code']) ? trim($truckData['booking_code']) : ('TAS-SLOT-' . rand(1000, 9999));
                $truckStatus = !empty($truckData['status']) ? trim($truckData['status']) : 'ARRIVED';

                // Buat record baru di tabel truck_arrivals
                $truckId = 'TRK-' . date('Ymd') . '-' . rand(100, 999);
                $stmtInsertTruck = $db->prepare("
                    INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmtInsertTruck->execute([
                    $truckId, $truckPlate, $driverName, $transporter, $dockSlot, $bookingCode, $truckStatus
                ]);

                // Tentukan manifes kargo yang sinkron secara spesifik dengan muatan truk ini
                $cargoInfo = getCargoForTruck($truckPlate, $driverName, $transporter);
                $sscc18 = generateSsccFromSeed($truckPlate . $cargoInfo['awb']);
                $expiryDate = date('Y-m-d', strtotime('+1 year'));

                // Update kargo aktif dengan data spesifik muatan truk ini
                $updateCargo = $db->prepare("
                    UPDATE cargo_shipments 
                    SET awb_number = ?, sscc = ?, gtin = ?, batch_no = ?, expiry_date = ?,
                        shipper = ?, consignee = ?, description = ?, commodity_type = ?,
                        quantity = ?, declared_weight_kg = ?, actual_weight_kg = ?,
                        current_stage = 1, status = 'REGISTERED', truck_arrival_id = ?
                    WHERE id = ?
                ");
                $updateCargo->execute([
                    $cargoInfo['awb'], $sscc18, $cargoInfo['gtin'], $cargoInfo['batch'], $expiryDate,
                    $cargoInfo['shipper'], $cargoInfo['consignee'], $cargoInfo['desc'], $cargoInfo['comm_type'],
                    $cargoInfo['qty'], $cargoInfo['declared_weight'], $cargoInfo['declared_weight'] + 4.0,
                    $truckId, $cargoId
                ]);

                // Ambil data kargo terbaru setelah tersimpan
                $stmtFetch = $db->prepare("
                    SELECT c.*, t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code
                    FROM cargo_shipments c
                    LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
                    WHERE c.id = ?
                ");
                $stmtFetch->execute([$cargoId]);
                $updatedCargo = $stmtFetch->fetch();

                $newTruckRecord = [
                    'id' => $truckId,
                    'truck_plate' => $truckPlate,
                    'driver_name' => $driverName,
                    'transporter_company' => $transporter,
                    'dock_slot' => $dockSlot,
                    'booking_code' => $bookingCode,
                    'arrival_time' => date('Y-m-d H:i:s'),
                    'status' => $truckStatus
                ];

                $jsonPayload = [
                    "event_type" => "TMS_TRUCK_GATE_CHECKIN",
                    "truck_id" => $truckId,
                    "truck_plate" => $truckPlate,
                    "driver_name" => $driverName,
                    "transporter" => $transporter,
                    "assigned_dock" => $dockSlot,
                    "booking_code" => $bookingCode,
                    "linked_cargo_manifest" => [
                        "e_awb_number" => $cargoInfo['awb'],
                        "sscc_18" => $sscc18,
                        "commodity" => $cargoInfo['desc'],
                        "commodity_type" => $cargoInfo['comm_type'],
                        "pieces" => $cargoInfo['qty'],
                        "declared_weight_kg" => $cargoInfo['declared_weight'],
                        "shipper" => $cargoInfo['shipper']
                    ],
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
                    "message" => "Truk " . $truckPlate . " (" . $driverName . ") & Muatan e-AWB " . $cargoInfo['awb'] . " berhasil diverifikasi di gerbang TAS & dialokasikan ke " . $dockSlot,
                    "tms_pass_token" => "TAS-TOKEN-" . strtoupper(bin2hex(random_bytes(4))),
                    "next_stage" => "CMS_AIDC_JSON_INGEST"
                ];

                logTransaction($db, $cargoId, 1, "Kedatangan Truk & Booking Slot (TMS/TAS)", "TMS RFID Gate Reader & Barrier Gate", "Intermodal TMS / Truck Appointment System (TAS)", "Verifikasi Waktu Kedatangan & Pembukaan Gerbang Otomatis", $jsonPayload, $responsePayload);
                break;

            case 2: // TAHAP 2: Pemindaian Data Inbound (JSON Ingestion)
                $scanTime = date('Y-m-d H:i:s');
                $update = $db->prepare("
                    UPDATE cargo_shipments 
                    SET current_stage = 2, status = 'SCANNED', scan_time = ? 
                    WHERE id = ?
                ");
                $update->execute([$scanTime, $cargoId]);

                $jsonPayload = [
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
                $isRelease = ($avsecDecision === 'RELEASE');
                $isReturn = ($avsecDecision === 'RETURN_TO_TRUCK');

                if ($isReturn) {
                    $statusResult = 'RETURNED_TO_TRUCK';
                    $notes = 'Kargo Ditolak di AVSEC dan dikembalikan ke armada truk semula (' . ($cargo['truck_plate'] ?? 'Truk Pengangkut') . '). Dibatalkan dari manifes penerbangan.';
                    $csdNum = null;
                } else if ($isRelease) {
                    $statusResult = 'CLEARED';
                    $notes = 'Pemeriksaan fisik sekunder (Hand Search & ETD) selesai di Ruang Khusus Karantina AVSEC. Dinyatakan AMAN dan dirilis.';
                    $csdNum = 'CSD-ID-' . date('Ymd') . '-SEC-' . rand(1000, 9999);
                } else if ($isSuspect) {
                    $statusResult = 'SUSPECT';
                    $notes = 'Peringatan AVSEC: Terdeteksi anomali kepadatan tinggi mencurigakan (Z > 20). Kargo dikunci otomatis di Ruang Khusus Karantina.';
                    $csdNum = null;
                } else {
                    $statusResult = 'CLEARED';
                    $notes = 'Screening Sinar-X Dual-View (320kV) selesai tanpa temuan mencurigakan. Kargo aman.';
                    $csdNum = 'CSD-ID-' . date('Ymd') . '-' . rand(1000, 9999);
                }

                $inspectorId = 'AVSEC-OPR-07';

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
                    $isSuspect ? 'High Density Atomic Number Z-effective > 20' : ($isReturn ? 'Threat Confirmed / Rejected' : 'Standard Organic (Z=7-8) / Light Metal'),
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
                $jitter = rand(-2, 4); // selisih kg realistis
                $actual = max(10, $declared + $jitter);
                $discrepancy = $actual - $declared;
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

        // Selalu ambil data kargo paling mutakhir beserta info truknya
        $stmtCargoAfter = $db->prepare("
            SELECT c.*, t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code
            FROM cargo_shipments c
            LEFT JOIN truck_arrivals t ON c.truck_arrival_id = t.id
            WHERE c.id = ?
        ");
        $stmtCargoAfter->execute([$cargoId]);
        $updatedCargo = $stmtCargoAfter->fetch();

        $db->commit();
        $res = [
            'success' => true,
            'message' => 'Tahap ' . $stage . ' berhasil diproses',
            'stage' => $stage,
            'cargo_id' => $cargoId,
            'cargo' => $updatedCargo
        ];
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
