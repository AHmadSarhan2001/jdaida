<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

$response = ['errors' => []];

// Function to execute a query and handle errors
function executeQuery($conn, $sql, $key, &$response) {
    $result = $conn->query($sql);
    if (!$result) {
        $response['errors'][$key] = $conn->error;
    }
    return $result;
}

// Daily Maintenance Count
$sql_maintenance = "SELECT COUNT(*) as count FROM maintenance WHERE DATE(maintenance_date) = CURDATE()";
$result_maintenance = executeQuery($conn, $sql_maintenance, 'daily_maintenance', $response);
$response['daily_maintenance'] = $result_maintenance ? ($result_maintenance->fetch_assoc()['count'] ?? 0) : 0;

// Daily Warehouse Items Out
$sql_warehouse = "SELECT SUM(quantity) as count FROM warehouse_log WHERE DATE(transaction_date) = CURDATE() AND transaction_type = 'out'";
$result_warehouse = executeQuery($conn, $sql_warehouse, 'daily_warehouse_out', $response);
$response['daily_warehouse_out'] = $result_warehouse ? ($result_warehouse->fetch_assoc()['count'] ?? 0) : 0;

// Daily Diesel Filled
$sql_diesel = "SELECT SUM(liters) as total FROM gas_log WHERE DATE(fill_date) = CURDATE() AND fuel_type = 'diesel'";
$result_diesel = executeQuery($conn, $sql_diesel, 'daily_diesel', $response);
$response['daily_diesel'] = $result_diesel ? ($result_diesel->fetch_assoc()['total'] ?? 0) : 0;

// Daily Gasoline Filled
$sql_gasoline = "SELECT SUM(liters) as total FROM gas_log WHERE DATE(fill_date) = CURDATE() AND fuel_type = 'gasoline'";
$result_gasoline = executeQuery($conn, $sql_gasoline, 'daily_gasoline', $response);
$response['daily_gasoline'] = $result_gasoline ? ($result_gasoline->fetch_assoc()['total'] ?? 0) : 0;

// Last 5 Warehouse Items
$sql_last_warehouse = "SELECT item_name, quantity, transaction_date FROM warehouse_log WHERE transaction_type = 'out' ORDER BY transaction_date DESC LIMIT 5";
$result_last_warehouse = executeQuery($conn, $sql_last_warehouse, 'last_warehouse_items', $response);
$response['last_warehouse_items'] = [];
if ($result_last_warehouse) {
    while($row = $result_last_warehouse->fetch_assoc()) {
        $response['last_warehouse_items'][] = $row;
    }
}

// Last 5 Maintenance Events
$sql_last_maintenance = "SELECT v.plate_number as vehicle_name, m.maintenance_type, m.maintenance_date 
                         FROM maintenance m 
                         JOIN vehicles v ON m.vehicle_id = v.id 
                         ORDER BY m.maintenance_date DESC LIMIT 5";
$result_last_maintenance = executeQuery($conn, $sql_last_maintenance, 'last_maintenance_events', $response);
$response['last_maintenance_events'] = [];
if ($result_last_maintenance) {
    while($row = $result_last_maintenance->fetch_assoc()) {
        $response['last_maintenance_events'][] = $row;
    }
}

// Last 5 Fuel Fillings
$sql_last_fuel = "SELECT v.plate_number, g.fuel_type, g.liters, g.total_cost, g.fill_date
                  FROM gas_log g
                  JOIN vehicles v ON g.vehicle_id = v.id 
                  ORDER BY g.fill_date DESC LIMIT 5";
$result_last_fuel = executeQuery($conn, $sql_last_fuel, 'last_fuel_fillings', $response);
$response['last_fuel_fillings'] = [];
if ($result_last_fuel) {
    while($row = $result_last_fuel->fetch_assoc()) {
        $row['fuel_type_ar'] = ($row['fuel_type'] == 'diesel') ? 'مازوت' : 'بنزين';
        $row['liters_formatted'] = number_format($row['liters'], 2);
        $row['total_cost_formatted'] = number_format($row['total_cost'], 0);
        $row['fill_date_formatted'] = date('Y-m-d', strtotime($row['fill_date']));
        $response['last_fuel_fillings'][] = $row;
    }
}

echo json_encode($response);

$conn->close();
?>
