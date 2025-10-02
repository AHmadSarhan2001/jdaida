<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_invoice'])) {
    $invoice_number = trim($_POST['invoiceNumber']);
    $invoice_date = date('Y-m-d'); // Use current date or get from form if provided
    $item_types = $_POST['itemType'] ?? [];
    $item_counts = $_POST['itemCount'] ?? [];
    $item_values = $_POST['itemValue'] ?? [];
    $item_notes = $_POST['itemNotes'] ?? [];
    
    if (empty($invoice_number) || empty($item_types) || empty($item_counts) || empty($item_values)) {
        echo json_encode(['success' => false, 'message' => 'رقم الفاتورة وأقلام الفاتورة مطلوبة']);
        exit();
    }
    
    // Calculate total amount
    $total_amount = 0;
    $valid_items = 0;
    for ($i = 0; $i < count($item_types); $i++) {
        if (!empty($item_types[$i]) && !empty($item_counts[$i]) && !empty($item_values[$i])) {
            $item_count = intval($item_counts[$i]);
            $item_value = floatval($item_values[$i]);
            if ($item_count > 0 && $item_value >= 0) {
                $total_amount += $item_count * $item_value;
                $valid_items++;
            }
        }
    }
    
    if ($valid_items === 0) {
        echo json_encode(['success' => false, 'message' => 'يجب إضافة قلم واحد على الأقل ببيانات صحيحة']);
        exit();
    }

    $conn->begin_transaction();

    try {
        // Insert into invoices table
        $sql_invoice = "INSERT INTO invoices (invoice_number, invoice_date, total_amount) VALUES (?, ?, ?)";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("ssd", $invoice_number, $invoice_date, $total_amount);
        $stmt_invoice->execute();
        $invoice_id = $stmt_invoice->insert_id;
        $stmt_invoice->close();

        // Insert into invoice_items table
        $sql_items = "INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);

        $inserted_items = [];
        for ($i = 0; $i < count($item_types); $i++) {
            if (!empty($item_types[$i]) && !empty($item_counts[$i]) && !empty($item_values[$i])) {
                $item_name = trim($item_types[$i]);
                $quantity = intval($item_counts[$i]);
                $unit_price = floatval($item_values[$i]);
                $notes = trim($item_notes[$i] ?? '');
                
                if ($quantity > 0 && $unit_price >= 0) {
                    $stmt_items->bind_param("isids", $invoice_id, $item_name, $quantity, $unit_price, $notes);
                    $stmt_items->execute();
                    $inserted_items[] = [
                        'item_name' => $item_name,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'notes' => $notes
                    ];
                }
            }
        }
        $stmt_items->close();

        $conn->commit();

        $new_invoice = [
            'id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'total_amount' => $total_amount,
            'items' => $inserted_items
        ];

        echo json_encode([
            'success' => true, 
            'message' => 'تم إضافة الفاتورة بنجاح',
            'data' => $new_invoice
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'خطأ في حفظ الفاتورة: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح']);
}
?>
