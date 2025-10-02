<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['withdraw_item'])) {
    $item_id = intval($_POST['withdrawItemId']);
    $quantity = intval($_POST['withdrawQuantity']);
    $destination_entity = trim($_POST['withdrawnTo']);

    if ($item_id <= 0 || $quantity <= 0 || empty($destination_entity)) {
        echo json_encode(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
        exit();
    }

    // Start a transaction
    $conn->begin_transaction();

    try {
        // 1. Get current quantity
        $sql_select = "SELECT quantity, item_name FROM warehouse_items WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $item_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $item = $result->fetch_assoc();
        $current_quantity = $item['quantity'];
        $item_name = $item['item_name'];
        $stmt_select->close();

        if ($current_quantity < $quantity) {
            throw new Exception("الكمية المطلوبة غير متوفرة. الكمية المتوفرة: " . $current_quantity);
        }

        // 2. Update warehouse_items table
        $new_quantity = $current_quantity - $quantity;
        $sql_update = "UPDATE warehouse_items SET quantity = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $new_quantity, $item_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 3. Log the transaction in warehouse_log
        $sql_log = "INSERT INTO warehouse_log (item_name, quantity, transaction_type, destination_entity, transaction_date) VALUES (?, ?, 'out', ?, NOW())";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("sis", $item_name, $quantity, $destination_entity);
        $stmt_log->execute();
        $stmt_log->close();

        // Commit the transaction
        $conn->commit();

        $updated_item = [
            'id' => $item_id,
            'item_name' => $item_name,
            'new_quantity' => $new_quantity
        ];

        echo json_encode([
            'success' => true, 
            'message' => 'تم تخريج القلم بنجاح',
            'data' => $updated_item
        ]);

    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
}
?>
