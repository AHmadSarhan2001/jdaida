<?php
header('Content-Type: application/json');
include '../db_connect.php';

try {
    $sql = "SELECT id, plate_number FROM vehicles ORDER BY plate_number ASC";
    $result = $conn->query($sql);
    
    $vehicles = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $vehicles[] = [
                'id' => $row['id'],
                'plate_number' => $row['plate_number']
            ];
        }
    }
    
    echo json_encode($vehicles);
} catch (Exception $e) {
    echo json_encode([]);
} finally {
    $conn->close();
}
?>
