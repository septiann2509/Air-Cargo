<?php
// config/cargo_payload.php
// Modul Integrasi Master JSON Payload per Kargo (Hardware Replacement PoC)
// Memastikan 1 Kargo memiliki 1 Master Dokumen JSON Payload Terpadu di Semua Tahapan (1-7)

require_once __DIR__ . '/database.php';

/**
 * Pastikan tabel cargo_shipments memiliki kolom cargo_payload (LONGTEXT)
 */
function ensureCargoPayloadColumn($db) {
    static $checked = false;
    if ($checked) return;
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `cargo_shipments` LIKE 'cargo_payload'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE `cargo_shipments` ADD COLUMN `cargo_payload` LONGTEXT NULL AFTER `status`");
        }
        $checked = true;
    } catch (Exception $e) {
        // Fallback jika alter table gagal
        error_log("Failed to ensure cargo_payload column: " . $e->getMessage());
    }
}

/**
 * Buat data pembacaan sensor virtual Layer 1 (pengganti hardware fisik lapangan)
 */
function generateVirtualHardwareLayer1($cargo) {
    $seed = abs(crc32($cargo['id'] . ($cargo['sscc'] ?? '')));
    $declaredWeight = (float)($cargo['declared_weight_kg'] ?? 350.0);
    $actualWeight = isset($cargo['actual_weight_kg']) && $cargo['actual_weight_kg'] !== null 
        ? (float)$cargo['actual_weight_kg'] 
        : ($declaredWeight + (($seed % 5) - 2));

    $isSuspect = ($cargo['status'] === 'SUSPECT');
    $isCleared = ($cargo['status'] === 'CLEARED' || $cargo['status'] === 'WEIGHED' || $cargo['status'] === 'ALLOCATED' || $cargo['status'] === 'LOADED');
    $isReturned = ($cargo['status'] === 'RETURNED_TO_TRUCK');

    return [
        "tms_gate" => [
            "device_id" => "TMS-UHF-GATE-01",
            "device_name" => "TMS Long-Range RFID Reader & Barrier Gate",
            "frequency" => "902-928 MHz EPC Gen2",
            "truck_plate" => $cargo['truck_plate'] ?? "B 9482 KXT",
            "driver_name" => $cargo['driver_name'] ?? "Bambang Supriyanto",
            "assigned_dock" => $cargo['dock_slot'] ?? "DOCK-04",
            "barrier_status" => "BARRIER_OPENED",
            "booking_code" => $cargo['booking_code'] ?? "TAS-SLOT-9482",
            "sensor_health" => "OPTIMAL_100%"
        ],
        "tunnel_scanner" => [
            "device_id" => "EDGE-AIDC-TUNNEL-02",
            "device_name" => "High-Speed Barcode & RFID UHF Tunnel Scanner",
            "conveyor_speed" => "2.5 m/s",
            "carrier_frequency" => "865.7 MHz (EPC Gen2 TDS 2.0)",
            "rssi_signal_dbm" => -41.5 - ($seed % 5),
            "read_confidence" => "99.98%",
            "optical_barcode_scanned" => "(00)" . ($cargo['sscc'] ?? '') . "(01)" . ($cargo['gtin'] ?? ''),
            "protection_rating" => "IP65 Industrial Waterproof"
        ],
        "cms_server" => [
            "server_id" => "CMS-CORE-SRV-01",
            "server_name" => "Cargo Management Software Core Gateway",
            "gateway_endpoint" => "https://cms.terminal-cargo.id/api/v1/inbound",
            "protocol" => "GS1_DIGITAL_LINK / REST_MQTT",
            "e_awb_matching_status" => "MATCHED_VALID",
            "matched_awb" => $cargo['awb_number'] ?? '',
            "session_token" => "CMS-SESS-" . strtoupper(substr(md5($cargo['id']), 0, 8))
        ],
        "avsec_xray" => [
            "device_id" => "XRAY-DUALVIEW-01",
            "device_name" => "Dual-View High-Energy Air Cargo X-Ray Scanner",
            "generator_power" => "320 kV / 180x180 cm Tunnel",
            "steel_penetration" => "80 mm",
            "density_analysis_z_effective" => $isSuspect ? 22.8 : 7.4,
            "threat_classification" => $isSuspect ? "SUSPECT_ANOMALY_DETECTED" : ($isReturned ? "REJECTED_RETURNED_TO_TRUCK" : "CLEARED_ORGANIC_SAFE"),
            "csd_certificate" => $cargo['csd_number'] ?? ($isCleared ? ("CSD-ID-" . date('Ymd') . "-" . substr($cargo['sscc'], -4)) : null),
            "inspector_badge" => $cargo['inspector_id'] ?? "AVSEC-OPR-07",
            "quarantine_vault_lock" => $isSuspect ? "LOCKED_IN_VAULT" : "UNLOCKED_TRANSIT"
        ],
        "smart_floor_scale" => [
            "device_id" => "SCALE-FLOOR-ULD-01",
            "device_name" => "Smart ULD Pit Floor Scale (15 Ton Capacity)",
            "dimensions" => "3.5m x 2.5m Industrial Steel Platform",
            "sensor_type" => "IP68 Hermetic Stainless Load Cells",
            "declared_weight_kg" => $declaredWeight,
            "actual_weight_kg" => $actualWeight,
            "discrepancy_kg" => round($actualWeight - $declaredWeight, 2),
            "tolerance_evaluation" => (abs($actualWeight - $declaredWeight) <= ($declaredWeight * 0.05)) ? "WITHIN_TOLERANCE_5%" : "DISCREPANCY_WARNING"
        ],
        "uld_workstation" => [
            "station_id" => "WS-ULD-BUILDUP-03",
            "station_name" => "Automated ULD Workstation & Stacking Conveyor",
            "assigned_uld_code" => $cargo['uld_code'] ?? "AKE-12345-GA",
            "uld_type" => $cargo['uld_type'] ?? "AKE",
            "compartment" => $cargo['compartment'] ?? "AFT-LOWER-1",
            "tare_weight_kg" => 75.0,
            "cargo_payload_kg" => $actualWeight,
            "gross_weight_kg" => 75.0 + $actualWeight,
            "center_of_gravity_percent" => 28.4,
            "envelope_compliance" => "WITHIN_SAFETY_LIMITS"
        ],
        "ramp_loader" => [
            "loader_id" => "RAMP-HIGH-LOADER-02",
            "loader_name" => "Airport Ramp Dolly & Cargo High-Loader",
            "flight_number" => $cargo['flight_number'] ?? "GA-707",
            "airline" => "Garuda Indonesia Cargo",
            "aircraft_type" => $cargo['aircraft_type'] ?? "Boeing 737-800F",
            "destination" => $cargo['flight_destination'] ?? "DPS (Denpasar/Bali)",
            "loadsheet_number" => "LS-" . ($cargo['flight_number'] ?? "GA707") . "-" . date('Ymd'),
            "ramp_transfer_status" => ($cargo['status'] === 'LOADED') ? "LOADED_INTO_AIRCRAFT_BELLY" : "PENDING_RAMP_DISPATCH"
        ]
    ];
}

/**
 * Buat struktur payload untuk setiap tahapan (1-7) yang terikat pada kargo ini
 */
function generateIntegratedStagesPayload($cargo, $logsByStage, $hardwareFeed) {
    $cargoId = $cargo['id'];
    $payloadId = "CMP-" . $cargoId;
    $currentStage = (int)($cargo['current_stage'] ?? 1);
    $status = $cargo['status'] ?? 'REGISTERED';

    $stages = [];

    // TAHAP 1: TMS / TAS
    $s1Done = ($currentStage >= 2) || ($status !== 'REGISTERED') || isset($logsByStage[1]);
    $stages[1] = [
        "stage_number" => 1,
        "stage_name" => "Kedatangan Truk & Booking Slot (TMS/TAS)",
        "hardware_layer1" => "TMS RFID Gate Reader & Boom Barrier",
        "software_layer3" => "Intermodal TMS / Truck Appointment System (TAS)",
        "status" => $s1Done ? "COMPLETED" : "CURRENT_ACTIVE",
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "TMS_TRUCK_GATE_CHECKIN",
            "master_sscc" => $cargo['sscc'],
            "truck_id" => $cargo['truck_arrival_id'] ?? ("TRK-" . date('Ymd') . "-01"),
            "truck_plate" => $cargo['truck_plate'] ?? "B 9482 KXT",
            "driver_name" => $cargo['driver_name'] ?? "Bambang Supriyanto",
            "transporter_company" => $cargo['transporter_company'] ?? "PT Indotruck Multimoda Logistik",
            "dock_slot" => $cargo['dock_slot'] ?? "DOCK-04",
            "booking_code" => $cargo['booking_code'] ?? "TAS-SLOT-9482",
            "hardware_telemetry" => $hardwareFeed['tms_gate'],
            "timestamp" => $cargo['created_at'] ?? date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "message" => "Truk " . ($cargo['truck_plate'] ?? "B 9482 KXT") . " diverifikasi di gerbang TAS & dialokasikan ke " . ($cargo['dock_slot'] ?? "DOCK-04"),
            "tms_pass_token" => "TAS-TOKEN-" . strtoupper(substr(md5($cargoId . '1'), 0, 8)),
            "next_stage" => "CMS_AIDC_JSON_INGEST"
        ]
    ];

    // TAHAP 2: Inbound JSON Ingestion (AIDC Tunnel)
    $s2Done = ($currentStage >= 3) || ($status === 'SCANNED') || isset($logsByStage[2]);
    $stages[2] = [
        "stage_number" => 2,
        "stage_name" => "Scan RFID & Barcode Gerbang Inbound (AIDC Tunnel)",
        "hardware_layer1" => "High-Speed Barcode & RFID UHF Tunnel Scanner (2.5 m/s, IP65)",
        "software_layer3" => "Cargo Management Software (CMS) Inbound Edge",
        "status" => $s2Done ? "COMPLETED" : ($currentStage === 2 ? "CURRENT_ACTIVE" : "PENDING"),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "AIDC_INBOUND_JSON_INGEST",
            "master_sscc" => $cargo['sscc'],
            "device_id" => "EDGE-AIDC-SCANNER-01",
            "stream_protocol" => "GS1_DIGITAL_LINK_JSON / REST_MQTT",
            "hardware_telemetry" => $hardwareFeed['tunnel_scanner'],
            "gs1_data" => [
                "ai_00_sscc" => $cargo['sscc'],
                "ai_01_gtin" => $cargo['gtin'],
                "ai_10_batch_no" => $cargo['batch_no'],
                "ai_17_expiry_date" => $cargo['expiry_date'],
                "quantity_koli" => (int)$cargo['quantity'],
                "declared_weight_kg" => (float)$cargo['declared_weight_kg']
            ],
            "ingested_at" => $cargo['scan_time'] ?? date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "message" => "Payload JSON AIDC (SSCC-18: " . $cargo['sscc'] . ") berhasil diidentifikasi dan dikirim ke CMS",
            "cms_ingest_token" => "CMS-TOKEN-" . strtoupper(substr(md5($cargoId . '2'), 0, 8)),
            "next_action" => "CMS_REGISTRATION"
        ]
    ];

    // TAHAP 3: Registrasi CMS & e-AWB Matching
    $s3Done = ($currentStage >= 4) || isset($logsByStage[3]);
    $stages[3] = [
        "stage_number" => 3,
        "stage_name" => "Registrasi Data & Pencocokan e-AWB di CMS",
        "hardware_layer1" => "CMS Core Application Server & Industrial Network",
        "software_layer3" => "Cargo Management Software (CMS Master e-AWB)",
        "status" => $s3Done ? "COMPLETED" : ($currentStage === 3 ? "CURRENT_ACTIVE" : "PENDING"),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "CMS_AWB_MATCHING",
            "action" => "VALIDATE_ELECTRONIC_AIR_WAYBILL",
            "master_sscc" => $cargo['sscc'],
            "e_awb_number" => $cargo['awb_number'],
            "hardware_telemetry" => $hardwareFeed['cms_server'],
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
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "cms_status" => "AWB_VERIFIED",
            "routing_instruction" => "MOVE_TO_AVSEC_XRAY_LANE_02",
            "priority_level" => ($cargo['commodity_type'] === 'PHARMA' ? "URGENT_COLD_CHAIN" : "STANDARD")
        ]
    ];

    // TAHAP 4: Pemeriksaan AVSEC X-Ray
    $isSuspect = ($status === 'SUSPECT');
    $isReturned = ($status === 'RETURNED_TO_TRUCK');
    $s4Done = ($currentStage >= 5) || ($status === 'CLEARED') || isset($logsByStage[4]);
    $stages[4] = [
        "stage_number" => 4,
        "stage_name" => "Pemeriksaan Keamanan X-Ray (AVSEC System)",
        "hardware_layer1" => "Dual-View High-Energy Air Cargo X-Ray Scanner (180x180cm, 320kV)",
        "software_layer3" => "Aviation Security Management System (AVSEC System)",
        "status" => $isSuspect ? "BLOCKED_SUSPECT" : ($isReturned ? "RETURNED_TO_TRUCK" : ($s4Done ? "COMPLETED" : ($currentStage === 4 ? "CURRENT_ACTIVE" : "PENDING"))),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "SECURITY_SCREENING_EVALUATION",
            "master_sscc" => $cargo['sscc'],
            "cargo_id" => $cargoId,
            "awb_number" => $cargo['awb_number'],
            "hardware_telemetry" => $hardwareFeed['avsec_xray'],
            "inspection_result" => $status === 'CLEARED' ? 'CLEARED' : ($isSuspect ? 'SUSPECT' : ($isReturned ? 'RETURNED_TO_TRUCK' : 'PENDING_SCREENING')),
            "csd_certificate" => $hardwareFeed['avsec_xray']['csd_certificate'],
            "timestamp" => date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => $isSuspect ? "403_FORBIDDEN" : "200_OK",
            "security_clearance" => $status === 'CLEARED' ? 'CLEARED' : ($isSuspect ? 'SUSPECT' : ($isReturned ? 'RETURNED_TO_TRUCK' : 'AWAITING_INSPECTION')),
            "action_required" => $isSuspect ? "HOLD_IN_QUARANTINE_VAULT" : ($isReturned ? "RETURN_CARGO_TO_TRUCK" : "PROCEED_TO_WEIGHING_STATION"),
            "alert_triggered" => $isSuspect
        ]
    ];

    // TAHAP 5: Penimbangan Kargo (Smart Floor Scale)
    $s5Done = ($currentStage >= 6) || in_array($status, ['WEIGHED', 'ALLOCATED', 'LOADED']) || isset($logsByStage[5]);
    $stages[5] = [
        "stage_number" => 5,
        "stage_name" => "Penimbangan Kargo (Smart ULD Weighing Station)",
        "hardware_layer1" => "Smart ULD Floor Scale (15 Ton Capacity, Load Cell IP68)",
        "software_layer3" => "CMS & Modul Integrasi Weight & Balance",
        "status" => $s5Done ? "COMPLETED" : ($currentStage === 5 ? "CURRENT_ACTIVE" : "PENDING"),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "CARGO_WEIGHT_ACQUISITION",
            "master_sscc" => $cargo['sscc'],
            "hardware_telemetry" => $hardwareFeed['smart_floor_scale'],
            "flight_loading_criteria" => [
                "max_uld_gross_weight_kg" => 1588.0,
                "max_aircraft_payload_kg" => 18500.0,
                "aircraft_type" => "Boeing 737-800F (GA-707)",
                "criteria_status" => "APPROVED_FOR_FLIGHT_LOADING"
            ],
            "weighed_at" => date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "message" => "Data berat aktual berhasil disinkronkan ke CMS & Modul Weight & Balance",
            "allow_uld_buildup" => true,
            "criteria_result" => "PASSED"
        ]
    ];

    // TAHAP 6: Build-Up ULD & Kalkulasi Weight & Balance
    $s6Done = ($status === 'LOADED') || ($currentStage === 7 && $status === 'ALLOCATED') || isset($logsByStage[6]);
    $stages[6] = [
        "stage_number" => 6,
        "stage_name" => "Build-Up ULD & Kalkulasi Weight & Balance",
        "hardware_layer1" => "Automated ULD Workstation & Stacking Conveyor",
        "software_layer3" => "Weight & Balance System / Load Planning Software",
        "status" => $s6Done ? "COMPLETED" : ($currentStage === 6 ? "CURRENT_ACTIVE" : "PENDING"),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "ULD_CONSOLIDATION_AND_LOAD_PLANNING",
            "master_sscc" => $cargo['sscc'],
            "cargo_id" => $cargoId,
            "hardware_telemetry" => $hardwareFeed['uld_workstation'],
            "weight_and_balance_engine" => [
                "aircraft" => "Boeing 737-800F",
                "assigned_compartment" => $cargo['compartment'] ?? "AFT-LOWER-1",
                "center_of_gravity_index" => 28.4,
                "within_safety_envelope" => true,
                "floor_load_limit_status" => "WITHIN_STRUCTURAL_LIMIT"
            ],
            "timestamp" => date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "message" => "Kargo berhasil dikonsolidasikan ke dalam kontainer ULD " . ($cargo['uld_code'] ?? "AKE-12345-GA"),
            "load_position_locked" => true
        ]
    ];

    // TAHAP 7: Update Manifes Penerbangan & Flight Loading
    $s7Done = ($status === 'LOADED') || isset($logsByStage[7]);
    $stages[7] = [
        "stage_number" => 7,
        "stage_name" => "Update Manifes Penerbangan & Pemuatan ke Pesawat",
        "hardware_layer1" => "Airport Ramp Dolly, Cargo High-Loader, & Cockpit EFB",
        "software_layer3" => "Cargo Management Software (CMS) & Airline Flight Operations",
        "status" => $s7Done ? "COMPLETED" : ($currentStage === 7 && $status !== 'LOADED' ? "CURRENT_ACTIVE" : "PENDING"),
        "request_payload" => [
            "cargo_payload_id" => $payloadId,
            "event_type" => "FLIGHT_MANIFEST_FINALIZATION",
            "master_sscc" => $cargo['sscc'],
            "flight_number" => $cargo['flight_number'] ?? "GA-707",
            "aircraft_type" => $cargo['aircraft_type'] ?? "Boeing 737-800F",
            "hardware_telemetry" => $hardwareFeed['ramp_loader'],
            "loaded_uld_list" => [
                [
                    "uld_code" => $cargo['uld_code'] ?? "AKE-12345-GA",
                    "final_weight_kg" => (float)($hardwareFeed['uld_workstation']['gross_weight_kg'] ?? 850.0),
                    "contains_cargo_awb" => $cargo['awb_number'],
                    "ramp_loading_status" => ($status === 'LOADED') ? "TRANSFERRED_TO_AIRCRAFT_BELLY" : "PENDING_RAMP_LOADING"
                ]
            ],
            "departure_clearance" => ($status === 'LOADED') ? "APPROVED" : "PENDING",
            "timestamp" => date('c')
        ],
        "response_payload" => [
            "cargo_payload_id" => $payloadId,
            "status" => "200_OK",
            "message" => "Manifes penerbangan final telah diterbitkan. Loadsheet resmi terkirim ke kokpit & flight ops.",
            "aircraft_ramp_status" => ($status === 'LOADED') ? "READY_FOR_PUSHBACK" : "AWAITING_FINAL_DISPATCH"
        ]
    ];

    return $stages;
}

/**
 * Generate dan susun satu Master JSON Payload utuh untuk kargo tertentu
 */
function generateMasterCargoPayload($db, $cargoId) {
    ensureCargoPayloadColumn($db);

    // Ambil detail kargo dengan semua relasi
    $stmt = $db->prepare("
        SELECT 
            c.*, 
            t.truck_plate, t.dock_slot, t.driver_name, t.transporter_company, t.booking_code,
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
    $stmt->execute([$cargoId]);
    $cargo = $stmt->fetch();

    if (!$cargo) return null;

    // Ambil log transaksi yang ada
    $logStmt = $db->prepare("SELECT * FROM scan_logs WHERE cargo_id = ? ORDER BY stage ASC, timestamp ASC");
    $logStmt->execute([$cargoId]);
    $logs = $logStmt->fetchAll();
    $logsByStage = [];
    foreach ($logs as $l) {
        $logsByStage[(int)$l['stage']] = $l;
    }

    $hardwareFeed = generateVirtualHardwareLayer1($cargo);
    $stages = generateIntegratedStagesPayload($cargo, $logsByStage, $hardwareFeed);

    $payloadId = "CMP-" . $cargo['id'];

    $masterDocument = [
        "cargo_payload_id" => $payloadId,
        "master_tracking_key" => "GS1-(00)-" . $cargo['sscc'],
        "poc_architecture" => [
            "role" => "Virtual Hardware Sensing & Telemetry Payload Replacement",
            "layer_1_hardware" => "Virtual Sensing Capture (RFID, X-Ray, Floor Scale, ULD WS, Ramp Loader)",
            "layer_2_network" => "Industrial Ethernet / Wi-Fi 6 / REST MQTT Gateway",
            "layer_3_software" => "Intermodal TMS / CMS / AVSEC / Weight & Balance Engine",
            "layer_4_protocol" => "GS1 SSCC 18-Digit / JSON Schema Standard"
        ],
        "system_metadata" => [
            "generated_by" => "PT Aerospace Consultant Advisory Group",
            "project_topic" => "Topic 6: Air Cargo & Intermodal Terminal",
            "institution" => "ITL Trisakti",
            "last_synced_at" => date('c')
        ],
        "cargo_identity" => [
            "cargo_id" => $cargo['id'],
            "awb_number" => $cargo['awb_number'],
            "sscc_18" => $cargo['sscc'],
            "gtin" => $cargo['gtin'],
            "batch_no" => $cargo['batch_no'],
            "expiry_date" => $cargo['expiry_date'],
            "commodity_description" => $cargo['description'],
            "commodity_type" => $cargo['commodity_type'],
            "quantity_koli" => (int)$cargo['quantity'],
            "declared_weight_kg" => (float)$cargo['declared_weight_kg'],
            "actual_weight_kg" => isset($cargo['actual_weight_kg']) ? (float)$cargo['actual_weight_kg'] : null,
            "shipper" => $cargo['shipper'],
            "consignee" => $cargo['consignee'],
            "origin" => "CGK (Jakarta)",
            "destination" => "DPS (Denpasar/Bali)",
            "handling_code" => ($cargo['commodity_type'] === 'PHARMA') ? "COL / PIL (Cold Chain Pharma)" : (($cargo['commodity_type'] === 'PERISHABLE') ? "PER (Perishable)" : "GEN (General Cargo)")
        ],
        "virtual_hardware_layer1_feed" => $hardwareFeed,
        "stages" => $stages,
        "lifecycle" => [
            "current_stage" => (int)$cargo['current_stage'],
            "current_status" => $cargo['status'],
            "is_suspect" => ($cargo['status'] === 'SUSPECT'),
            "is_cleared" => in_array($cargo['status'], ['CLEARED', 'WEIGHED', 'ALLOCATED', 'LOADED']),
            "is_loaded" => ($cargo['status'] === 'LOADED'),
            "active_dock" => $cargo['dock_slot'] ?? 'DOCK-04',
            "assigned_uld" => $cargo['uld_code'] ?? 'AKE-12345-GA',
            "assigned_flight" => $cargo['flight_number'] ?? 'GA-707'
        ]
    ];

    return $masterDocument;
}

/**
 * Sinkronkan Master JSON Payload ke database cargo_shipments.cargo_payload
 */
function syncCargoPayload($db, $cargoId) {
    $masterDoc = generateMasterCargoPayload($db, $cargoId);
    if (!$masterDoc) return null;

    $jsonString = json_encode($masterDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    try {
        $stmt = $db->prepare("UPDATE cargo_shipments SET cargo_payload = ? WHERE id = ?");
        $stmt->execute([$jsonString, $cargoId]);
    } catch (Exception $e) {
        error_log("Failed to update cargo_payload: " . $e->getMessage());
    }

    return $masterDoc;
}
