<?php
header('Content-Type: application/json');
include '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['maintenance_id'])) {
    $maintenance_id = intval($_POST['maintenance_id']);
    
    // Check if maintenance record exists
    $check_sql = "SELECT id FROM maintenance WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $maintenance_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'حدث الصيانة غير موجود'
        ]);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Delete the maintenance record
    $delete_sql = "DELETE FROM maintenance WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $maintenance_id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'تم حذف حدث الصيانة بنجاح'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'لم يتم حذف أي سجل'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ أثناء الحذف: ' . $conn->error
        ]);
    }
    $delete_stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'معرف حدث الصيانة مطلوب'
    ]);
}

$conn->close();
?>
