<?php
// api/tables.php
require_once __DIR__ . '/../config/database.php';

$table = $_GET['table'] ?? 'cargo_shipments';
$search = trim($_GET['search'] ?? '');
$allowedTables = [
    'cargo_shipments',
    'truck_arrivals',
    'security_checks',
    'weight_records',
    'uld_containers',
    'flight_manifests',
    'scan_logs'
];

if (!in_array($table, $allowedTables)) {
    jsonResponse(['success' => false, 'message' => 'Tabel tidak valid'], 400);
}

try {
    $db = getDb();
    
    // Dapatkan skema kolom
    $colsStmt = $db->query("DESCRIBE `$table`");
    $columns = $colsStmt->fetchAll();
    $columnNames = array_column($columns, 'Field');

    $sql = "SELECT * FROM `$table`";
    $params = [];

    if ($search !== '') {
        $whereClauses = [];
        foreach ($columnNames as $col) {
            $whereClauses[] = "`$col` LIKE ?";
            $params[] = "%$search%";
        }
        $sql .= " WHERE " . implode(' OR ', $whereClauses);
    }

    // Default sorting
    if (in_array('created_at', $columnNames)) {
        $sql .= " ORDER BY created_at DESC";
    } elseif (in_array('timestamp', $columnNames)) {
        $sql .= " ORDER BY timestamp DESC";
    } elseif (in_array('id', $columnNames)) {
        $sql .= " ORDER BY id DESC";
    }

    $sql .= " LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'table' => $table,
        'columns' => $columns,
        'total_rows' => count($rows),
        'rows' => $rows
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
