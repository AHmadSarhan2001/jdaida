<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user role from session
$role = $_SESSION['role'];

// Define permissions for each role
$permissions = [
    'it' => ['dashboard', 'gas-station', 'warehouse', 'maintenance', 'vehicles', 'gas-report', 'maintenance-report', 'report', 'warehouse-report'],
    'warehouse_keeper' => ['warehouse', 'warehouse-report'],
    'vehicles_manager' => ['dashboard', 'gas-station', 'warehouse', 'maintenance', 'vehicles', 'gas-report', 'maintenance-report', 'report', 'warehouse-report'],
    'laundry_worker' => ['maintenance', 'maintenance-report'],
    'diwan' => ['vehicles', 'report'],
    'gas_station_worker' => ['gas-station', 'gas-report']
];

// Get the allowed pages for the current user's role
$allowed_pages = $permissions[$role];

// If user doesn't have dashboard access, hide it and show first allowed page
if (!in_array('dashboard', $allowed_pages)) {
    echo '<style>#page-dashboard { display: none !important; }</style>';
    $first_page = $allowed_pages[0];
    echo '<script>document.addEventListener("DOMContentLoaded", function() { 
        document.getElementById("page-" + first_page).style.display = "block";
        window.location.hash = "#page-" + first_page;
    });</script>';
}

// ===== WAREHOUSE: HANDLE FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_warehouse_item'])) {
    $item_name = $_POST['itemName'];
    $quantity = $_POST['itemQuantity'];
    $unit_price = isset($_POST['unitPrice']) ? $_POST['unitPrice'] : 0.00;

    $sql = "INSERT INTO warehouse_items (item_name, quantity, unit_price) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sid", $item_name, $quantity, $unit_price);
    if ($stmt->execute()) {
        header("Location: main.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}

// ===== WAREHOUSE: HANDLE WITHDRAW ITEM =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['withdraw_item'])) {
    $item_id = $_POST['withdrawItemId'];
    $quantity = $_POST['withdrawQuantity'];
    $destination_entity = $_POST['withdrawnTo']; // Changed variable name for clarity

    // Start a transaction
    $conn->begin_transaction();

    try {
        // 1. Get current quantity
        $sql_select = "SELECT quantity FROM warehouse_items WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $item_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $item = $result->fetch_assoc();
        $current_quantity = $item['quantity'];
        $stmt_select->close();

        if ($current_quantity >= $quantity) {
            // 2. Update warehouse_items table
            $new_quantity = $current_quantity - $quantity;
            $sql_update = "UPDATE warehouse_items SET quantity = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ii", $new_quantity, $item_id);
            $stmt_update->execute();
            $stmt_update->close();

            // 3. Log the transaction in warehouse_log
            $sql_log = "INSERT INTO warehouse_log (item_name, quantity, transaction_type, destination_entity, transaction_date) VALUES ((SELECT item_name FROM warehouse_items WHERE id = ?), ?, 'out', ?, NOW())";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("iis", $item_id, $quantity, $destination_entity);
            $stmt_log->execute();
            $stmt_log->close();

            // Commit the transaction
            $conn->commit();
            $_SESSION['success_message'] = "تم تخريج القلم بنجاح";
            header("Location: main.php");
            exit();
        } else {
            throw new Exception("Insufficient quantity.");
        }
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: main.php");
        exit();
    }
}

// ===== WAREHOUSE: HANDLE ADD INVOICE =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_invoice'])) {
    $invoice_number = $_POST['invoiceNumber'];
    $invoice_date = date('Y-m-d'); // Or get from a form field if you have one
    $item_types = $_POST['itemType'];
    $item_counts = $_POST['itemCount'];
    $item_values = $_POST['itemValue'];
    $item_notes = $_POST['itemNotes'];
    $total_amount = 0;

    // Calculate total amount
    for ($i = 0; $i < count($item_values); $i++) {
        $total_amount += $item_values[$i] * $item_counts[$i];
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

        for ($i = 0; $i < count($item_types); $i++) {
            $stmt_items->bind_param("isids", $invoice_id, $item_types[$i], $item_counts[$i], $item_values[$i], $item_notes[$i]);
            $stmt_items->execute();
        }
        $stmt_items->close();

        $conn->commit();
        header("Location: main.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}

// ===== VEHICLES: HANDLE FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vehicle'])) {
    $plate_number = trim($_POST['plate_number']);
    $make = trim($_POST['make']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $type = trim($_POST['type']);
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $monthly_allocations = floatval($_POST['monthly_allocations'] ?? 0.00);
    $recipient = trim($_POST['recipient'] ?? '');
    $chassis_number = trim($_POST['chassis_number'] ?? '');
    $engine_number = trim($_POST['engine_number'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if (empty($plate_number) || empty($make) || empty($model) || empty($type) || empty($year) || empty($fuel_type)) {
        $_SESSION['error_message'] = 'جميع الحقول الأساسية مطلوبة بما في ذلك نوع الوقود';
        header("Location: main.php");
        exit();
    }

    // Validate fuel_type
    if (!in_array($fuel_type, ['diesel', 'gasoline'])) {
        $_SESSION['error_message'] = 'نوع الوقود غير صحيح. يجب أن يكون مازوت أو بنزين';
        header("Location: main.php");
        exit();
    }

    $sql = "INSERT INTO vehicles (plate_number, make, model, year, type, fuel_type, monthly_allocations, recipient, chassis_number, engine_number, color, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Corrected bind_param string to match the 12 placeholders
        $stmt->bind_param("sssisdssssss", $plate_number, $make, $model, $year, $type, $fuel_type, $monthly_allocations, $recipient, $chassis_number, $engine_number, $color, $notes);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'تم إضافة الآلية بنجاح';
            header("Location: main.php");
            exit();
        } else {
            error_log("Error inserting vehicle: " . $stmt->error);
            $_SESSION['error_message'] = 'خطأ في حفظ البيانات: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing insert statement: " . $conn->error);
        $_SESSION['error_message'] = 'خطأ في الاتصال بقاعدة البيانات';
    }
    header("Location: main.php");
    exit();
}

// ===== VEHICLES: HANDLE UPDATE FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_vehicle'])) {
    // Always return JSON for update_vehicle requests (AJAX only)
    header('Content-Type: application/json');
    
    // Log POST data for debugging
    error_log("Update vehicle POST data: " . print_r($_POST, true));
    
    try {
        $vehicle_id = intval($_POST['vehicle_id']);
        $make = trim($_POST['make']);
        $model = trim($_POST['model']);
        $year = intval($_POST['year']);
        $type = trim($_POST['type']);
        $recipient = trim($_POST['recipient']);
        $plate_number = trim($_POST['plate_number']);
        $chassis_number = trim($_POST['chassis_number']);
        $engine_number = trim($_POST['engine_number']);
        $color = trim($_POST['color']);
        $fuel_type = $_POST['fuel_type'] ?? null;
        $monthly_allocations = floatval($_POST['monthly_allocations'] ?? 0.00);
        $notes = $_POST['notes'] ?? null;

        // Validate required fields
        if (empty($vehicle_id) || empty($make) || empty($type) || empty($plate_number)) {
            echo json_encode(['success' => false, 'message' => 'البيانات الأساسية مفقودة']);
            exit();
        }

        // Verify vehicle exists
        $check_sql = "SELECT id FROM vehicles WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $vehicle_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'الآلية غير موجودة']);
            exit();
        }
        $check_stmt->close();

        // Get the new field values from POST FIRST (moved before prepare/bind)
        $fuel_type = $_POST['fuel_type'] ?? null;
        $monthly_allocations = floatval($_POST['monthly_allocations'] ?? 0.00);
        $notes = $_POST['notes'] ?? null;

        // Prepare the UPDATE query
        $sql = "UPDATE vehicles SET make = ?, model = ?, year = ?, type = ?, recipient = ?, plate_number = ?, chassis_number = ?, engine_number = ?, color = ?, fuel_type = ?, monthly_allocations = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Bind parameters in the correct order
            $stmt->bind_param("ssissssssdsi", $make, $model, $year, $type, $recipient, $plate_number, $chassis_number, $engine_number, $color, $fuel_type, $monthly_allocations, $notes, $vehicle_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Success - return updated data
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
                    // No changes made
                    echo json_encode(['success' => false, 'message' => 'لم يتم إجراء أي تعديلات']);
                }
            } else {
                // Error executing query
                error_log("Error updating vehicle: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'خطأ في تنفيذ التحديث: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Error preparing statement
            error_log("Error preparing update statement: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'خطأ في إعداد الاستعلام: ' . $conn->error]);
        }
    } catch (Exception $e) {
        error_log("Exception in update vehicle: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'خطأ غير متوقع: ' . $e->getMessage()]);
    }
    
    // CRITICAL: Always exit after JSON response to prevent HTML output
    exit();
}


// ===== GAS STATION: HANDLE FORM SUBMISSION =====
// Removed - now handled via AJAX in update_gas_log.php


// ===== FETCH ALL DATA FOR DISPLAY =====
// Fetch Warehouse Items
$items_sql = "SELECT id, item_name, quantity, unit_price FROM warehouse_items ORDER BY id DESC";
$warehouse_items_result = $conn->query($items_sql);

// Fetch Vehicles
$vehicles_sql = "SELECT id, plate_number, make, model, year, type, fuel_type, monthly_allocations, recipient, chassis_number, engine_number, color, notes FROM vehicles";
$vehicles_result = $conn->query($vehicles_sql);

// Fetch Maintenance Records
$maintenance_sql = "SELECT m.id, m.maintenance_date, m.description, m.cost, v.plate_number, v.model 
                    FROM maintenance m 
                    JOIN vehicles v ON m.vehicle_id = v.id 
                    ORDER BY m.maintenance_date DESC";
$maintenance_result = $conn->query($maintenance_sql);

// Fetch Gas Logs (all for other uses)
$gas_sql = "SELECT g.id, g.fill_date, g.liters, g.total_cost, v.plate_number, v.model, g.fuel_type
            FROM gas_log g
            JOIN vehicles v ON g.vehicle_id = v.id 
            ORDER BY g.fill_date DESC";
$gas_log_result = $conn->query($gas_sql);

// Fetch Last 5 Fuel Fillings for Dashboard
$last_five_fillings_sql = "SELECT g.id, g.fill_date, g.liters, g.total_cost, v.plate_number, v.model, g.fuel_type
                           FROM gas_log g
                           JOIN vehicles v ON g.vehicle_id = v.id 
                           ORDER BY g.fill_date DESC LIMIT 5";
$last_five_fillings_result = $conn->query($last_five_fillings_sql);

// Fetch Today's Fuel Fillings for Gas Station Table
$today_fillings_sql = "SELECT g.id, g.vehicle_id, g.fill_date, g.fuel_type, g.liters, g.total_cost, g.notes, v.plate_number, v.model 
                       FROM gas_log g
                       JOIN vehicles v ON g.vehicle_id = v.id 
                       WHERE DATE(g.fill_date) = CURDATE()
                       ORDER BY g.fill_date DESC";
$today_fillings_result = $conn->query($today_fillings_sql);

// Fetch all vehicles again for dropdowns (in case connection was closed)
$vehicles_for_dropdown_sql = "SELECT id, plate_number, model FROM vehicles";
$vehicles_for_dropdown_result = $conn->query($vehicles_for_dropdown_sql);
$vehicles_options = [];
if ($vehicles_for_dropdown_result->num_rows > 0) {
    while($vehicle = $vehicles_for_dropdown_result->fetch_assoc()) {
        $vehicles_options[] = $vehicle;
    }
}

// Fetch Warehouse Items for dropdown
$warehouse_items_for_dropdown_sql = "SELECT id, item_name FROM warehouse_items WHERE quantity > 0 ORDER BY item_name ASC";
$warehouse_items_for_dropdown_result = $conn->query($warehouse_items_for_dropdown_sql);
$warehouse_items_options = [];
if ($warehouse_items_for_dropdown_result->num_rows > 0) {
    while($item = $warehouse_items_for_dropdown_result->fetch_assoc()) {
        $warehouse_items_options[] = $item;
    }
}

// Fetch Invoices for display
$invoices_sql = "SELECT id, invoice_number, invoice_date, total_amount FROM invoices ORDER BY invoice_date DESC";
$invoices_result = $conn->query($invoices_sql);

// ===== WAREHOUSE: HANDLE INVOICE SEARCH (AJAX) =====
if (isset($_GET['action']) && $_GET['action'] == 'search_invoices' && isset($_GET['search_term'])) {
    $search_term = '%' . $_GET['search_term'] . '%';
    
    $sql_search_invoices = "
        SELECT DISTINCT i.id, i.invoice_number, i.invoice_date, i.total_amount
        FROM invoices i
        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
        WHERE i.invoice_number LIKE ? OR ii.item_name LIKE ?
        ORDER BY i.invoice_date DESC
    ";
    $stmt_search_invoices = $conn->prepare($sql_search_invoices);
    $stmt_search_invoices->bind_param("ss", $search_term, $search_term);
    $stmt_search_invoices->execute();
    $result_search_invoices = $stmt_search_invoices->get_result();
    
    $found_invoices = [];
    while ($row = $result_search_invoices->fetch_assoc()) {
        $found_invoices[] = $row;
    }
    $stmt_search_invoices->close();
    
    header('Content-Type: application/json');
    echo json_encode($found_invoices);
    exit(); // Terminate script after sending JSON response
}

// ===== FETCH DATA FOR DASHBOARD =====
// Daily Maintenance Count
$sql_maintenance_count = "SELECT COUNT(*) as count FROM maintenance WHERE DATE(maintenance_date) = CURDATE()";
$result_maintenance_count = $conn->query($sql_maintenance_count);
$daily_maintenance_count = $result_maintenance_count->fetch_assoc()['count'] ?? 0;

// Daily Warehouse Items Out
$sql_warehouse_out_count = "SELECT SUM(quantity) as count FROM warehouse_log WHERE DATE(transaction_date) = CURDATE() AND transaction_type = 'out'";
$result_warehouse_out_count = $conn->query($sql_warehouse_out_count);
$daily_warehouse_out_count = $result_warehouse_out_count->fetch_assoc()['count'] ?? 0;

    // Daily Diesel Filled - only explicit diesel records (exclude null/empty)
    $sql_diesel_total = "SELECT COALESCE(SUM(liters), 0) as total FROM gas_log WHERE DATE(fill_date) = CURDATE() AND fuel_type = 'diesel'";
    $result_diesel_total = $conn->query($sql_diesel_total);
    $daily_diesel_total = $result_diesel_total->fetch_assoc()['total'] ?? 0;

    // Daily Gasoline Filled - only explicit gasoline records
    $sql_gasoline_total = "SELECT COALESCE(SUM(liters), 0) as total FROM gas_log WHERE DATE(fill_date) = CURDATE() AND fuel_type = 'gasoline'";
    $result_gasoline_total = $conn->query($sql_gasoline_total);
    $daily_gasoline_total = $result_gasoline_total->fetch_assoc()['total'] ?? 0;

// Last 5 Warehouse Items
$dash_warehouse_sql = "SELECT item_name, quantity, transaction_date FROM warehouse_log WHERE transaction_type = 'out' ORDER BY transaction_date DESC LIMIT 5";
$dash_warehouse_result = $conn->query($dash_warehouse_sql);

// Last 5 Maintenance Events
$dash_maintenance_sql = "SELECT v.plate_number, m.maintenance_type, m.maintenance_date 
                         FROM maintenance m 
                         JOIN vehicles v ON m.vehicle_id = v.id 
                         ORDER BY m.maintenance_date DESC LIMIT 5";
$dash_maintenance_result = $conn->query($dash_maintenance_sql);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام إدارة المحطة</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/warehouse.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/maintenance.css">
    <link rel="stylesheet" href="assets/css/gas-station.css">
    <link rel="stylesheet" href="assets/css/vehicles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="assets/imges/logo.png" alt="معبر جديدة يابوس" width="120" height="80" style="max-width: 100%; height: auto; border-radius: 8px;">
                <h2> معبر جديدة يابوس</h2>

            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <?php if (in_array('dashboard', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-page="dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>لوحة التحكم</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('gas-station', $allowed_pages)): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-page="gas-station">
                            <i class="fas fa-gas-pump"></i>
                            <span>الكازية</span>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="dropdown-item">
                                <a href="#" class="nav-link" data-subpage="fueling">
                                    <i class="fas fa-filling-station"></i>
                                    <span>التعبئة اليومية</span>
                                </a>
                            </li>
                            <li class="dropdown-item">
                                <a href="#" class="nav-link" data-subpage="tanks">
                                    <i class="fas fa-tint"></i>
                                    <span>إدارة الخزانات</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('warehouse', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="warehouse">
                            <i class="fas fa-warehouse"></i>
                            <span>مستودع</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('maintenance', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="maintenance">
                            <i class="fas fa-tools"></i>
                            <span>الصيانة</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('vehicles', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="vehicles">
                            <i class="fas fa-truck"></i>
                            <span>الآليات</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-divider"></li>
                    
                    <?php if (in_array('gas-report', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="gas-report">
                            <i class="fas fa-chart-line"></i>
                            <span>تقرير كازية</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('maintenance-report', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="maintenance-report">
                            <i class="fas fa-chart-bar"></i>
                            <span>تقرير صيانة</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('report', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="report">
                            <i class="fas fa-chart-pie"></i>
                            <span>تقرير آليات</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('warehouse-report', $allowed_pages)): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="warehouse-report">
                            <i class="fas fa-chart-area"></i>
                            <span>تقرير مستودع</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-content">
                    <h1 class="page-title">لوحة التحكم</h1>
                    <div class="header-actions">
                        <span class="date-time" id="currentDateTime"></span>
                        <button class="btn-refresh" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>تسجيل الخروج</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content" id="page-content">
                <div id="page-dashboard" class="page-wrapper">
                    <div class="dashboard-cards">
                        <div class="card">
                            <div class="card-icon maintenance">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="card-content">
                                <h3>الصيانات اليومية</h3>
                                <div class="card-number"><?php echo $daily_maintenance_count; ?></div>
                                <div class="card-subtitle">صيانة مكتملة اليوم</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon warehouse">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <div class="card-content">
                                <h3>الأقلام الخارجة</h3>
                                <div class="card-number"><?php echo $daily_warehouse_out_count ?? 0; ?></div>
                                <div class="card-subtitle">قلم خرج من المستودع اليوم</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon diesel">
                                <i class="fas fa-oil-can"></i>
                            </div>
                            <div class="card-content">
                                <h3>المازوت المعبأ</h3>
                                <div class="card-number"><?php echo number_format($daily_diesel_total ?? 0); ?></div>
                                <div class="card-subtitle">لتر مازوت معبأ اليوم</div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-icon gasoline">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <div class="card-content">
                                <h3>البنزين المعبأ</h3>
                                <div class="card-number"><?php echo number_format($daily_gasoline_total ?? 0); ?></div>
                                <div class="card-subtitle">لتر بنزين معبأ اليوم</div>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-charts">
                        <div class="chart-container">
                            <h3>إحصائيات الأسبوع</h3>
                            <div class="chart-placeholder">
                                <i class="fas fa-chart-line"></i>
                                <p>سيتم إضافة الرسوم البيانية لاحقاً</p>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-tables">
                        <div class="table-container">
                            <h3>آخر 5 أقلام خرجت من المستودع</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>اسم القلم</th>
                                        <th>الكمية</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($dash_warehouse_result && $dash_warehouse_result->num_rows > 0) {
                                        while($row = $dash_warehouse_result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["quantity"]) . "</td>";
                                            echo "<td>" . htmlspecialchars(date('Y-m-d', strtotime($row["transaction_date"]))) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3'>لا توجد سجلات حالياً</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-container">
                            <h3>آخر 5 أحداث صيانة</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>الآلية</th>
                                        <th>نوع الصيانة</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($dash_maintenance_result && $dash_maintenance_result->num_rows > 0) {
                                        while($row = $dash_maintenance_result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["plate_number"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["maintenance_type"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($row["maintenance_date"]) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3'>لا توجد سجلات صيانة حالياً</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Last 5 Fuel Fillings Table -->
                        <div class="table-container">
                            <h3>آخر 5 تعبئات وقود</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>الآلية</th>
                                        <th>نوع الوقود</th>
                                        <th>الكمية (لتر)</th>
                                        <th>التكلفة (ل.س)</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($last_five_fillings_result && $last_five_fillings_result->num_rows > 0) {
                                        while($row = $last_five_fillings_result->fetch_assoc()) {
                                            $fuel_type_ar = ($row["fuel_type"] == 'diesel') ? 'مازوت' : 'بنزين';
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row["plate_number"]) . "</td>";
                                            echo "<td>" . htmlspecialchars($fuel_type_ar) . "</td>";
                                            echo "<td>" . number_format($row["liters"], 2) . "</td>";
                                            echo "<td>" . number_format($row["total_cost"], 0) . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row["fill_date"])) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5'>لا توجد سجلات تعبئة حالياً</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>