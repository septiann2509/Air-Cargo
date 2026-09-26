<?php
// api/reset.php
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Only POST allowed'], 405);
}

try {
    $db = getDb();

    // Disable foreign key checks momentarily for clean wipe
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE scan_logs;");
    $db->exec("TRUNCATE TABLE weight_records;");
    $db->exec("TRUNCATE TABLE security_checks;");
    $db->exec("TRUNCATE TABLE cargo_shipments;");
    $db->exec("TRUNCATE TABLE truck_arrivals;");
    $db->exec("TRUNCATE TABLE uld_containers;");
    $db->exec("TRUNCATE TABLE flight_manifests;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Re-seed flight_manifests
    $db->exec("
        INSERT INTO flight_manifests (id, flight_number, airline, aircraft_type, origin, destination, departure_time, max_cargo_weight_kg, current_total_weight_kg, status) VALUES
        ('FL-GA707-2026', 'GA-707', 'Garuda Indonesia Cargo', 'Boeing 737-800F', 'CGK (Jakarta)', 'DPS (Denpasar/Bali)', DATE_ADD(NOW(), INTERVAL 3 HOUR), 18000.00, 0.00, 'OPEN'),
        ('FL-SQ801-2026', 'SQ-801', 'Singapore Airlines Cargo', 'Boeing 777F', 'CGK (Jakarta)', 'SIN (Singapore)', DATE_ADD(NOW(), INTERVAL 6 HOUR), 45000.00, 0.00, 'OPEN');
    ");

    // Re-seed uld_containers
    $db->exec("
        INSERT INTO uld_containers (id, uld_code, uld_type, tare_weight_kg, max_weight_kg, current_weight_kg, flight_id, compartment, status) VALUES
        ('ULD-AKE-12345', 'AKE-12345-GA', 'AKE', 75.00, 1588.00, 75.00, 'FL-GA707-2026', 'AFT-LOWER-1', 'EMPTY'),
        ('ULD-AKE-67890', 'AKE-67890-GA', 'AKE', 75.00, 1588.00, 75.00, 'FL-GA707-2026', 'FWD-LOWER-2', 'EMPTY'),
        ('ULD-PMC-99120', 'PMC-99120-SQ', 'PMC', 110.00, 6800.00, 110.00, 'FL-SQ801-2026', 'MAIN-DECK-01', 'EMPTY');
    ");

    // Re-seed truck_arrivals
    $db->exec("
        INSERT INTO truck_arrivals (id, truck_plate, driver_name, transporter_company, dock_slot, booking_code, arrival_time, status, notes) VALUES
        ('TRK-20260926-01', 'B 9482 KXT', 'Bambang Supriyanto', 'PT Indotruck Multimoda Logistik', 'DOCK-04', 'TAS-SLOT-9482', NOW(), 'ARRIVED', 'Truk membawa muatan kargo farmasi & ekspor garmen'),
        ('TRK-20260926-02', 'B 9112 PXZ', 'Agus Setiawan', 'PT Lintas Samudera Darat', 'DOCK-02', 'TAS-SLOT-9112', DATE_SUB(NOW(), INTERVAL 1 HOUR), 'COMPLETED', 'Bongkar muat selesai');
    ");

    // Re-seed cargo_shipments
    $db->exec("
        INSERT INTO cargo_shipments (id, awb_number, sscc, gtin, batch_no, expiry_date, shipper, consignee, description, commodity_type, quantity, declared_weight_kg, actual_weight_kg, truck_arrival_id, uld_id, scan_time, current_stage, status) VALUES
        ('CGO-20260926-001', '126-98741022', '389912345000000128', '08991234567890', 'LOT-BIO-9988', '2027-06-30', 'PT Bio Farma Internasional', 'Bali Medical Distribution Ltd', 'Vaksin & Produk Medis Cold Chain', 'PHARMA', 80, 480.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED'),
        ('CGO-20260926-002', '126-44589211', '389912345000000135', '08994567891234', 'BATCH-TEX-2026', '2029-12-31', 'PT Sinar Garmen Nusantara', 'Tropical Resort Boutique Bali', 'Pakaian & Tekstil Premium Ekspor', 'GENERAL_CARGO', 120, 320.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED'),
        ('CGO-20260926-003', '126-77812049', '389912345000000142', '08997788990011', 'LOT-EXP-4401', '2026-11-20', 'PT Sumber Laut Tropis', 'Gourmet Seafood Resto Denpasar', 'Fresh Tuna Loin Perishable Pack', 'PERISHABLE', 50, 290.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED');
    ");

    // Log awal untuk kargo 1 di tahap 1
    $logPayload = [
        'event' => 'TRUCK_ARRIVAL_BOOKING',
        'truck_id' => 'TRK-20260926-01',
        'plate_number' => 'B 9482 KXT',
        'driver' => 'Bambang Supriyanto',
        'assigned_dock' => 'DOCK-04',
        'awb_reference' => '126-98741022',
        'eta_status' => 'ON_TIME',
        'arrival_timestamp' => date('c')
    ];
    $logResponse = [
        'status' => 'SUCCESS',
        'message' => 'Truk berhasil dialokasikan ke dock DOCK-04',
        'gate_clearance_code' => 'GATE-OK-9482'
    ];
    $stmtLog = $db->prepare("
        INSERT INTO scan_logs (id, cargo_id, stage, stage_name, hardware_device, software_system, action, json_payload, response_payload, http_status, timestamp)
        VALUES (?, 'CGO-20260926-001', 1, 'Kedatangan Truk & Booking Slot', 'TMS RFID Gate Reader', 'Intermodal TMS / TAS', 'Registrasi Kedatangan Truk & Alokasi Slot Dock', ?, ?, 200, NOW())
    ");
    $stmtLog->execute([
        'LOG-' . uniqid(),
        json_encode($logPayload, JSON_PRETTY_PRINT),
        json_encode($logResponse, JSON_PRETTY_PRINT)
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Data simulasi berhasil di-reset ke kondisi awal demo'
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
