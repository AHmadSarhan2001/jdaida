<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }
    
    $vehicle_id = intval($input['id']);
    
    // Log the delete attempt for debugging
    error_log("Delete vehicle attempt for ID: " . $vehicle_id);
    
    // Prepare the delete statement
    $sql = "DELETE FROM vehicles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $vehicle_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Vehicle deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error executing delete: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing delete statement: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
}

$conn->close();
?>
