<?php
header('Content-Type: application/json');

// Include database connection
require_once '../db_connect.php'; // Adjust path as necessary

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle search by car code (make) or driver name (recipient)
    if (isset($_GET['search'])) {
        $search_term = trim($_GET['search']);
        
        if (empty($search_term)) {
            echo json_encode(['success' => false, 'message' => 'Search term is required.']);
            exit();
        }
        
        // Prepare search query - search in make (car code) OR recipient (driver name)
        $sql = "SELECT * FROM vehicles WHERE make LIKE ? OR recipient LIKE ?";
        $search_pattern = '%' . $search_term . '%';
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ss", $search_pattern, $search_pattern);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $vehicles = [];
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Map database fields to frontend-friendly names
                        $vehicles[] = [
                            'id' => $row['id'],
                            'car_code' => $row['make'] ?? '',
                            'car_name' => $row['model'] ?? '',
                            'driver_name' => $row['recipient'] ?? '',
                            'department' => $row['type'] ?? '',
                            'license_plate' => $row['plate_number'] ?? '',
                            'fuel_type' => $row['fuel_type'] ?? '',
                            'year' => $row['year'] ?? '',
                            'chassis_number' => $row['chassis_number'] ?? '',
                            'engine_number' => $row['engine_number'] ?? '',
                            'color' => $row['color'] ?? '',
                            'monthly_allocations' => $row['monthly_allocations'] ?? 0.00,
                            'notes' => $row['notes'] ?? ''
                        ];
                    }
                    
                    echo json_encode(['success' => true, 'data' => $vehicles, 'count' => count($vehicles)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No car found with this code or driver name.', 'data' => []]);
                }
            } else {
                error_log("Error executing search query: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Error executing search query: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            error_log("Error preparing search statement: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error preparing search statement: ' . $conn->error]);
        }
    }
    // Handle fetch by vehicle ID (existing functionality)
    elseif (isset($_GET['vehicle_id'])) {
        $vehicle_id = intval($_GET['vehicle_id']);

        $sql = "SELECT * FROM vehicles WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $vehicle_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $vehicle_data = $result->fetch_assoc();
                    
                    $mapped_data = [
                        'id' => $vehicle_data['id'],
                        'make' => $vehicle_data['make'] ?? '',
                        'type' => $vehicle_data['type'] ?? '',
                        'plate_number' => $vehicle_data['plate_number'] ?? '',
                        'model' => $vehicle_data['model'] ?? '',
                        'year' => $vehicle_data['year'] ?? '',
                        'recipient' => $vehicle_data['recipient'] ?? '',
                        'chassis_number' => $vehicle_data['chassis_number'] ?? '',
                        'engine_number' => $vehicle_data['engine_number'] ?? '',
                        'color' => $vehicle_data['color'] ?? '',
                        'fuel_type' => $vehicle_data['fuel_type'] ?? '',
                        'monthly_allocations' => $vehicle_data['monthly_allocations'] ?? 0.00,
                        'notes' => $vehicle_data['notes'] ?? ''
                    ];
                    
                    echo json_encode(['success' => true, 'data' => $mapped_data]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No vehicle found with the given ID.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error executing fetch query: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error preparing fetch statement: ' . $conn->error]);
        }
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Provide either search term or vehicle_id parameter.']);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only GET requests are allowed.']);
}

$conn->close();
?>
