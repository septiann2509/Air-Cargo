<?php
// api/stats.php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDb();

    // 1. Total Inbound Shipments
    $stmt = $db->query("SELECT COUNT(*) AS total FROM cargo_shipments");
    $totalShipments = (int)$stmt->fetch()['total'];

    // 2. Kargo per Stage
    $stmt = $db->query("
        SELECT current_stage, COUNT(*) as count 
        FROM cargo_shipments 
        GROUP BY current_stage 
        ORDER BY current_stage ASC
    ");
    $stagesCount = array_fill(1, 7, 0);
    while ($row = $stmt->fetch()) {
        $stagesCount[(int)$row['current_stage']] = (int)$row['count'];
    }

    // 3. Status Keamanan (AVSEC)
    $stmt = $db->query("SELECT xray_result, COUNT(*) as count FROM security_checks GROUP BY xray_result");
    $avsecStats = ['CLEARED' => 0, 'SUSPECT' => 0];
    while ($row = $stmt->fetch()) {
        $avsecStats[$row['xray_result']] = (int)$row['count'];
    }

    // 4. Status ULD
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM uld_containers GROUP BY status");
    $uldStats = ['EMPTY' => 0, 'LOADING' => 0, 'FULL' => 0, 'LOADED' => 0];
    while ($row = $stmt->fetch()) {
        $uldStats[$row['status']] = (int)$row['count'];
    }

    // 5. Total Berat Dimuat vs Kapasitas Manifes
    $stmt = $db->query("
        SELECT 
            SUM(current_total_weight_kg) as loaded_weight, 
            SUM(max_cargo_weight_kg) as max_capacity 
        FROM flight_manifests
    ");
    $weightStats = $stmt->fetch();

    // 6. Total Truk Tiba
    $stmt = $db->query("SELECT COUNT(*) as total FROM truck_arrivals");
    $totalTrucks = (int)$stmt->fetch()['total'];

    // 7. Total Log Transaksi Pertukaran Data
    $stmt = $db->query("SELECT COUNT(*) as total FROM scan_logs");
    $totalLogs = (int)$stmt->fetch()['total'];

    // 8. Komoditas
    $stmt = $db->query("SELECT commodity_type, COUNT(*) as count FROM cargo_shipments GROUP BY commodity_type");
    $commodities = [];
    while ($row = $stmt->fetch()) {
        $commodities[$row['commodity_type']] = (int)$row['count'];
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'total_shipments' => $totalShipments,
            'stages_breakdown' => $stagesCount,
            'avsec_stats' => $avsecStats,
            'uld_stats' => $uldStats,
            'weight_stats' => [
                'loaded_kg' => (float)($weightStats['loaded_weight'] ?? 0),
                'max_capacity_kg' => (float)($weightStats['max_capacity'] ?? 0),
            ],
            'total_trucks' => $totalTrucks,
            'total_logs' => $totalLogs,
            'commodities' => $commodities
        ]
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
