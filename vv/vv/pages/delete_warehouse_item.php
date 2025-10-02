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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'يجب استخدام POST']);
    exit();
}

// Get the item ID from JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف القلم مفقود']);
    exit();
}

$item_id = intval($input['id']);

try {
    // Verify item exists
    $check_sql = "SELECT id FROM warehouse_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $item_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        $check_stmt->close();
        echo json_encode(['success' => false, 'message' => 'القلم غير موجود']);
        exit();
    }
    $check_stmt->close();

    // Delete the item
    $delete_sql = "DELETE FROM warehouse_items WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $item_id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'تم حذف القلم بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم حذف أي قلم']);
        }
    } else {
        error_log("Error deleting warehouse item: " . $delete_stmt->error);
        echo json_encode(['success' => false, 'message' => 'خطأ في حذف القلم: ' . $delete_stmt->error]);
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    error_log("Exception in delete warehouse item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ غير متوقع: ' . $e->getMessage()]);
}

exit();
?>
