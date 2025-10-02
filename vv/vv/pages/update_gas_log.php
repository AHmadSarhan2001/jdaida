<?php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $vehicle_id = intval($_POST['vehicle_id']);
        $liters = floatval($_POST['liters']);
        $notes = trim($_POST['notes'] ?? '');

        // Validate required fields for both insert and update
        if (empty($vehicle_id) || $liters <= 0) {
            echo json_encode(['success' => false, 'message' => 'معرف السيارة والكمية مطلوبان']);
            exit();
        }

        // Verify vehicle exists
        $vehicle_sql = "SELECT id, fuel_type FROM vehicles WHERE id = ?";
        $vehicle_stmt = $conn->prepare($vehicle_sql);
        $vehicle_stmt->bind_param("i", $vehicle_id);
        $vehicle_stmt->execute();
        $vehicle_result = $vehicle_stmt->get_result();
        
        if ($vehicle_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'السيارة غير موجودة']);
            exit();
        }
        
        $vehicle_data = $vehicle_result->fetch_assoc();
        $fuel_type = $vehicle_data['fuel_type'];
        
        // Fallback if fuel_type is not set or invalid
        if (empty($fuel_type) || !in_array($fuel_type, ['diesel', 'gasoline'])) {
            error_log("Warning: Vehicle ID $vehicle_id has invalid fuel_type. Defaulting to 'diesel'.");
            $fuel_type = 'diesel';
        }
        
        $vehicle_stmt->close();

        // Handle UPDATE if id is provided
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $log_id = intval($_POST['id']);
            $fill_date = $_POST['fill_date'] ?? date('Y-m-d H:i:s');

            // Validate fuel_type if provided in update
            if (isset($_POST['fuel_type']) && !empty($_POST['fuel_type'])) {
                $fuel_type = $_POST['fuel_type'];
                if (!in_array($fuel_type, ['diesel', 'gasoline'])) {
                    echo json_encode(['success' => false, 'message' => 'نوع الوقود غير صحيح']);
                    exit();
                }
            }

            // Verify log exists and belongs to the vehicle
            $check_sql = "SELECT id, vehicle_id FROM gas_log WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $log_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'سجل التعبئة غير موجود']);
                exit();
            }
            
            $log_data = $check_result->fetch_assoc();
            if ($log_data['vehicle_id'] != $vehicle_id) {
                echo json_encode(['success' => false, 'message' => 'خطأ في بيانات السيارة']);
                exit();
            }
            $check_stmt->close();

            // Update the gas log
            $sql = "UPDATE gas_log SET fuel_type = ?, liters = ?, notes = ?, fill_date = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsi", $fuel_type, $liters, $notes, $fill_date, $log_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Return updated data
                    $updated_data = [
                        'id' => $log_id,
                        'vehicle_id' => $vehicle_id,
                        'fill_date' => $fill_date,
                        'fuel_type' => $fuel_type,
                        'liters' => $liters,
                        'notes' => $notes
                    ];
                    echo json_encode(['success' => true, 'message' => 'تم تحديث سجل التعبئة بنجاح', 'data' => $updated_data, 'action' => 'update']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'لم يتم إجراء أي تعديلات']);
                }
            } else {
                error_log("Error updating gas log: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'خطأ في تنفيذ التحديث: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Handle INSERT (no id provided)
            $fill_datetime = date('Y-m-d H:i:s'); // Current date and time for new entries

            $sql = "INSERT INTO gas_log (vehicle_id, fill_date, fuel_type, liters, total_cost, notes) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // Calculate total_cost based on fuel type (you can adjust prices)
            $fuel_prices = ['diesel' => 5000, 'gasoline' => 8000]; // SYP per liter
            $total_cost = $liters * ($fuel_prices[$fuel_type] ?? 5000);
            
            $stmt->bind_param("issdds", $vehicle_id, $fill_datetime, $fuel_type, $liters, $total_cost, $notes);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                
                // Return new record data
                $new_data = [
                    'id' => $new_id,
                    'vehicle_id' => $vehicle_id,
                    'fill_date' => $fill_datetime,
                    'fuel_type' => $fuel_type,
                    'liters' => $liters,
                    'notes' => $notes,
                    'total_cost' => $total_cost
                ];
                echo json_encode(['success' => true, 'message' => 'تمت إضافة التعبئة بنجاح', 'data' => $new_data, 'action' => 'insert']);
            } else {
                error_log("Error inserting gas log: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'خطأ في حفظ بيانات التعبئة: ' . $stmt->error]);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Exception in gas log operation: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ غير متوقع: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'طلب غير صحيح']);
}
?>
