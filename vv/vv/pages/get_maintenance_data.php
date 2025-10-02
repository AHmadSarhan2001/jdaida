<?php
header('Content-Type: application/json');
include '../db_connect.php';

try {
    $sql = "SELECT 
                m.id,
                m.maintenance_date,
                m.maintenance_type,
                m.description,
                m.cost,
                v.plate_number as vehicle_plate,
                v.make as vehicle_type
            FROM maintenance m 
            JOIN vehicles v ON m.vehicle_id = v.id 
            ORDER BY m.maintenance_date DESC";

    $result = $conn->query($sql);
    
    $maintenance_data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $maintenance_data[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $maintenance_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'errors' => ['database' => 'خطأ في الاتصال بقاعدة البيانات'],
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
