<?php
session_start();
include '../db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit();
}

header('Content-Type: application/json');

// Log POST data for debugging
error_log("Update warehouse item POST data: " . print_r($_POST, true));

try {
    // Validate input
    if (!isset($_POST['item_id']) || !isset($_POST['item_name']) || !isset($_POST['quantity'])) {
        echo json_encode(['success' => false, 'message' => 'البيانات المطلوبة مفقودة']);
        exit();
    }

    $item_id = intval($_POST['item_id']);
    $item_name = trim($_POST['item_name']);
    $quantity = intval($_POST['quantity']);
    $unit_price = isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : 0.00;

    // Validate required fields
    if (empty($item_id) || empty($item_name) || $quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'البيانات الأساسية غير صحيحة']);
        exit();
    }

    // Verify item exists
    $check_sql = "SELECT id FROM warehouse_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $item_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'القلم غير موجود']);
        exit();
    }
    $check_stmt->close();

    // Prepare the UPDATE query
    $sql = "UPDATE warehouse_items SET item_name = ?, quantity = ?, unit_price = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Bind parameters in the correct order
        $stmt->bind_param("sidi", $item_name, $quantity, $unit_price, $item_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Success - return updated data
                $updated_data = [
                    'id' => $item_id,
                    'item_name' => $item_name,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price
                ];
                echo json_encode(['success' => true, 'message' => 'تم تحديث القلم بنجاح', 'data' => $updated_data]);
            } else {
                // No changes made
                echo json_encode(['success' => false, 'message' => 'لم يتم إجراء أي تعديلات']);
            }
        } else {
            // Error executing query
            error_log("Error updating warehouse item: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'خطأ في تنفيذ التحديث: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        // Error preparing statement
        error_log("Error preparing update statement: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'خطأ في إعداد الاستعلام: ' . $conn->error]);
    }
} catch (Exception $e) {
    error_log("Exception in update warehouse item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ غير متوقع: ' . $e->getMessage()]);
}

// Always exit after JSON response
exit();
?>
