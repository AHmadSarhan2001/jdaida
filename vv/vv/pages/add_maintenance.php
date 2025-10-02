<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_maintenance'])) {
    $vehicle_id = intval($_POST['vehicle_id']);
    $maintenance_date = trim($_POST['maintenance_date']);
    $maintenance_type = trim($_POST['maintenance_type']);
    $description = trim($_POST['description']);
    $cost = floatval($_POST['cost'] ?? 0.00);

    if ($vehicle_id <= 0 || empty($maintenance_date) || empty($maintenance_type) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'الحقول الأساسية مطلوبة']);
        exit();
    }

    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $maintenance_date)) {
        echo json_encode(['success' => false, 'message' => 'تاريخ الصيانة غير صحيح']);
        exit();
    }

    // Verify vehicle exists
    $check_sql = "SELECT id FROM vehicles WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $vehicle_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'الآلية غير موجودة']);
        exit();
    }
    $check_stmt->close();

    $sql = "INSERT INTO maintenance (vehicle_id, maintenance_date, maintenance_type, description, cost) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssd", $vehicle_id, $maintenance_date, $maintenance_type, $description, $cost);
    
    if ($stmt->execute()) {
        $new_maintenance = [
            'id' => $conn->insert_id,
            'vehicle_id' => $vehicle_id,
            'maintenance_date' => $maintenance_date,
            'maintenance_type' => $maintenance_type,
            'description' => $description,
            'cost' => $cost
        ];
        echo json_encode(['success' => true, 'message' => 'تم إضافة حدث الصيانة بنجاح', 'data' => $new_maintenance]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في حفظ بيانات الصيانة: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
}
?>
