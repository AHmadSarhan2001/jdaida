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

// Check if item_id is provided
if (!isset($_GET['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف القلم مفقود']);
    exit();
}

$item_id = intval($_GET['item_id']);

try {
    // Verify item exists and fetch details
    $sql = "SELECT id, item_name, quantity, unit_price FROM warehouse_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        echo json_encode([
            'success' => true, 
            'data' => $item
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'القلم غير موجود'
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Exception in get item details: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ غير متوقع: ' . $e->getMessage()
    ]);
}

exit();
?>
