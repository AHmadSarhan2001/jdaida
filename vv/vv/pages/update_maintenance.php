<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['maintenance_id'])) {
    $maintenance_id = intval($_POST['maintenance_id']);
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $maintenance_type = $_POST['maintenance_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $cost = $_POST['cost'] ?? '0.00';

    // Validation
    $errors = [];
    
    if (empty($vehicle_id)) {
        $errors['vehicle_id'] = 'يرجى اختيار الآلية';
    }
    
    if (empty($maintenance_date)) {
        $errors['maintenance_date'] = 'يرجى اختيار تاريخ الصيانة';
    }
    
    if (empty($maintenance_type)) {
        $errors['maintenance_type'] = 'يرجى اختيار نوع الصيانة';
    }
    
    if (empty($description)) {
        $errors['description'] = 'يرجى إدخال تفاصيل الصيانة';
    }
    
    if (empty($cost) || !is_numeric($cost) || $cost < 0) {
        $errors['cost'] = 'يرجى إدخال تكلفة صحيحة';
    }

    // Check if maintenance record exists
    if (empty($errors)) {
        $check_sql = "SELECT id FROM maintenance WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $maintenance_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'حدث الصيانة غير موجود']);
            exit();
        }
        $check_stmt->close();
    }

    // Check if vehicle exists
    if (empty($errors)) {
        $check_vehicle = $conn->prepare("SELECT id FROM vehicles WHERE id = ?");
        $check_vehicle->bind_param("i", $vehicle_id);
        $check_vehicle->execute();
        $result = $check_vehicle->get_result();
        if ($result->num_rows == 0) {
            $errors['vehicle_id'] = 'الآلية المحددة غير موجودة';
        }
        $check_vehicle->close();
    }

    if (empty($errors)) {
        // Update the maintenance record
        $stmt = $conn->prepare("UPDATE maintenance SET vehicle_id = ?, maintenance_date = ?, maintenance_type = ?, description = ?, cost = ? WHERE id = ?");
        $stmt->bind_param("isssdi", $vehicle_id, $maintenance_date, $maintenance_type, $description, $cost, $maintenance_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث حدث الصيانة بنجاح'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'لم يتم إجراء أي تغييرات'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحديث: ' . $conn->error
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'message' => 'يرجى تصحيح الأخطاء الموجودة'
        ]);
    }
} elseif (isset($_GET['id'])) {
    // Get maintenance record for editing
    $maintenance_id = intval($_GET['id']);
    $sql = "SELECT m.*, v.plate_number FROM maintenance m JOIN vehicles v ON m.vehicle_id = v.id WHERE m.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $maintenance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $maintenance = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => $maintenance
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'حدث الصيانة غير موجود'
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'معرف حدث الصيانة مطلوب'
    ]);
}

$conn->close();
?>
