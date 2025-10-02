<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vehicle'])) {
    $plate_number = trim($_POST['plate_number']);
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $type = trim($_POST['type']);
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $monthly_allocations = floatval($_POST['monthly_allocations'] ?? 0.00);
    $recipient = trim($_POST['recipient'] ?? '');
    $chassis_number = trim($_POST['chassis_number'] ?? '');
    $engine_number = trim($_POST['engine_number'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if (empty($plate_number) || empty($make) || empty($model) || empty($type) || empty($year) || empty($fuel_type)) {
        echo json_encode(['success' => false, 'message' => 'جميع الحقول الأساسية مطلوبة بما في ذلك نوع الوقود']);
        exit();
    }

    // Validate fuel_type
    if (!in_array($fuel_type, ['diesel', 'gasoline'])) {
        echo json_encode(['success' => false, 'message' => 'نوع الوقود غير صحيح. يجب أن يكون مازوت أو بنزين']);
        exit();
    }

    // Check if plate number already exists
    $check_sql = "SELECT id FROM vehicles WHERE plate_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $plate_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'رقم اللوحة موجود مسبقاً']);
        exit();
    }
    $check_stmt->close();

    $sql = "INSERT INTO vehicles (plate_number, make, model, year, type, fuel_type, monthly_allocations, recipient, chassis_number, engine_number, color, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssisdssssss", $plate_number, $make, $model, $year, $type, $fuel_type, $monthly_allocations, $recipient, $chassis_number, $engine_number, $color, $notes);
        if ($stmt->execute()) {
            $new_vehicle = [
                'id' => $conn->insert_id,
                'plate_number' => $plate_number,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'type' => $type,
                'fuel_type' => $fuel_type,
                'monthly_allocations' => $monthly_allocations,
                'recipient' => $recipient,
                'chassis_number' => $chassis_number,
                'engine_number' => $engine_number,
                'color' => $color,
                'notes' => $notes
            ];
            echo json_encode(['success' => true, 'message' => 'تم إضافة الآلية بنجاح', 'data' => $new_vehicle]);
        } else {
            error_log("Error inserting vehicle: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'خطأ في حفظ البيانات: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        error_log("Error preparing insert statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
}
?>
