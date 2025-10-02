<?php
header('Content-Type: application/json');
include '../db_connect.php';

$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

if ($invoice_id > 0) {
    try {
        // Fetch invoice details
        $sql_invoice = "SELECT invoice_number, invoice_date, total_amount FROM invoices WHERE id = ?";
        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("i", $invoice_id);
        $stmt_invoice->execute();
        $result_invoice = $stmt_invoice->get_result();
        $invoice = $result_invoice->fetch_assoc();
        $stmt_invoice->close();

        if ($invoice) {
            // Fetch invoice items
            $sql_items = "SELECT item_name, quantity, unit_price, notes FROM invoice_items WHERE invoice_id = ?";
            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param("i", $invoice_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            $items = [];
            while ($row = $result_items->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt_items->close();

            $response = [
                'invoice_number' => $invoice['invoice_number'],
                'invoice_date' => $invoice['invoice_date'],
                'total_amount' => $invoice['total_amount'],
                'items' => $items
            ];
            echo json_encode($response);
        } else {
            echo json_encode(['error' => 'لم يتم العثور على الفاتورة']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'معرف فاتورة غير صالح']);
}

$conn->close();
?>
