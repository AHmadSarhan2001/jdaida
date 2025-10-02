<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_warehouse_item'])) {
    $item_name = trim($_POST['itemName']);
    $quantity = intval($_POST['itemQuantity']);
    $unit_price = floatval($_POST['unitPrice'] ?? 0.00);

    if (empty($item_name) || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'اسم القلم والكمية مطلوبان']);
        exit();
    }

    $sql = "INSERT INTO warehouse_items (item_name, quantity, unit_price) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sid", $item_name, $quantity, $unit_price);
    
    if ($stmt->execute()) {
        $new_item = [
            'id' => $conn->insert_id,
            'item_name' => $item_name,
            'quantity' => $quantity,
            'unit_price' => $unit_price
        ];
        echo json_encode(['success' => true, 'message' => 'تم إضافة القلم بنجاح', 'data' => $new_item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في حفظ البيانات: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
}
?>
