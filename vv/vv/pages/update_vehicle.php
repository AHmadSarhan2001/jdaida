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
    
    // Map JavaScript field names to database field names
    $make = trim($input['make'] ?? '');
    $type = trim($input['type'] ?? '');
    $plate_number = trim($input['plate_number'] ?? '');
    $model = trim($input['model'] ?? '');
    $year = isset($input['year']) ? intval($input['year']) : null;
    $recipient = trim($input['recipient'] ?? '');
    $chassis_number = trim($input['chassis_number'] ?? '');
    $engine_number = trim($input['engine_number'] ?? '');
    $color = trim($input['color'] ?? '');
    $fuel_type = trim($input['fuel_type'] ?? '');
    $monthly_allocations = isset($input['monthly_allocations']) ? floatval($input['monthly_allocations']) : 0.00;
    $notes = trim($input['notes'] ?? '');

    // Log received data for debugging
    error_log("Update vehicle received data: " . print_r($input, true));
    
    // Build UPDATE query with all possible fields
    $sql = "UPDATE vehicles SET make = ?, type = ?, plate_number = ?, model = ?, year = ?, recipient = ?, chassis_number = ?, engine_number = ?, color = ?, fuel_type = ?, monthly_allocations = ?, notes = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssissssssdsi", $make, $type, $plate_number, $model, $year, $recipient, $chassis_number, $engine_number, $color, $fuel_type, $monthly_allocations, $notes, $vehicle_id);
        
        if ($stmt->execute()) {
            // Always return success if no SQL error occurred
            $updated_data = [
                'id' => $vehicle_id,
                'make' => $make,
                'type' => $type,
                'plate_number' => $plate_number,
                'model' => $model,
                'year' => $year,
                'recipient' => $recipient,
                'chassis_number' => $chassis_number,
                'engine_number' => $engine_number,
                'color' => $color,
                'fuel_type' => $fuel_type,
                'monthly_allocations' => $monthly_allocations,
                'notes' => $notes
            ];
            echo json_encode(['success' => true, 'message' => 'تم تحديث الآلية بنجاح', 'data' => $updated_data]);
        } else {
            error_log("Error executing update for vehicle ID " . $vehicle_id . ": " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'خطأ في تنفيذ التحديث: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        error_log("Error preparing update statement for vehicle ID " . $vehicle_id . ": " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'خطأ في إعداد الاستعلام: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
}

$conn->close();
?>
