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
                    <div id="page-gas-station" class="page-wrapper" style="display: none;">
                        <div class="section-header">
                            <h2><i class="fas fa-gas-pump"></i> إدارة الكازية</h2>
                            <p>نظام شامل لإدارة الوقود والخزانات والتقارير</p>
                        </div>

                        <div class="gas-tabs">
                            <button class="tab-link active" onclick="openGasStationTab(event, 'fueling')">
                                <i class="fas fa-filling-station"></i>
                                <span>التعبئة اليومية</span>
                            </button>
                            <button class="tab-link" onclick="openGasStationTab(event, 'tanks')">
                                <i class="fas fa-tint"></i>
                                <span>إدارة الخزانات</span>
                            </button>
                        </div>

                    <!-- Fueling Tab -->

                    <div id="fueling" class="tab-content" style="display: block;">
                        <!-- Vehicle Search Section - Alkazi Search -->
                        <div class="section-header">
                            <h3><i class="fas fa-search"></i> البحث عن السيارة</h3>
                            <p>ابحث بالكود أو اسم السائق لعرض تفاصيل السيارة الكاملة</p>
                            <div class="search-group" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                                <input type="text" id="alkaziSearchInput" class="search-input" placeholder="أدخل كود السيارة أو اسم السائق..." style="flex: 1; max-width: 400px; padding: 10px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;">
                                <button id="alkaziSearchBtn" class="btn btn-primary" onclick="searchCarByCodeOrDriver()">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                                <button class="btn btn-secondary" onclick="clearAlkaziSearch()">
                                    <i class="fas fa-times"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Search Results Display Area -->
                        <div id="alkaziSearchResults" class="search-results-container" style="display: none; margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                            <!-- Search results will be displayed here -->
                        </div>

                        <!-- Add Fuel Form - Simplified -->
                        <div class="section-header">
                            <h3><i class="fas fa-plus-circle"></i> إضافة تعبئة وقود</h3>
                            <button class="btn btn-primary" onclick="openQuickFuelingModal()" style="margin-left: auto;">
                                <i class="fas fa-bolt"></i> تعبئة خارجية
                            </button>
                        </div>

<form action="main.php" method="post" class="card" id="fuelingForm" style="margin-bottom: 25px;">
    <!-- Hidden fields for database -->
    <input type="hidden" name="vehicle_id" id="vehicle_id" required>
    <input type="hidden" name="fill_date" id="fill_date" required>
    <input type="hidden" name="fuel_type" id="fuel_type" required>
    <input type="hidden" name="total_cost" id="total_cost" required>
    <input type="hidden" name="driver_name" id="driver_name">
    <input type="hidden" name="car_name" id="car_name">
    <input type="hidden" name="department" id="department">
    <input type="hidden" name="plate_number" id="plate_number">
    <input type="hidden" name="chassis_number" id="chassis_number">
    <input type="hidden" name="engine_number" id="engine_number">
    <input type="hidden" name="year" id="year">
    <input type="hidden" name="color" id="color">
    
    <!-- Improved form layout -->
    <div class="form-row">
        <div class="form-group form-group-half">
            <label for="vehicleCodeInput">كود السيارة</label>
            <input type="text" id="vehicleCodeInput" class="form-control" readonly placeholder="سيتم ملء هذا تلقائياً بعد البحث" style="background-color: #f8f9fa; cursor: not-allowed;">
            <small class="form-text text-muted">ابحث عن السيارة أولاً ثم انقر عليها لملء هذا الحقل تلقائياً</small>
        </div>
        <div class="form-group form-group-half">
            <label for="liters">عدد اللترات</label>
            <input type="number" step="0.01" name="liters" id="liters" class="form-control" placeholder="0.00" required min="0">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="notes">ملاحظات</label>
            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="ملاحظات إضافية (اختياري)..."></textarea>
        </div>
    </div>
    <div class="form-actions" style="text-align: left; margin-top: 20px;">
        <button type="reset" class="btn btn-secondary" onclick="resetFuelingForm()" style="margin-left: 0;">
            إلغاء
        </button>
        <button type="submit" name="add_filling" class="btn btn-success" id="addFillingBtn" disabled style="margin-left: 10px;">
            <i class="fas fa-save"></i> حفظ السجل
        </button>
    </div>
</form>

                        <script>
                            // Fueling form helper functions
                            function resetFuelingForm() {
                                document.getElementById('fuelingForm').reset();
                                document.getElementById('vehicleCodeInput').value = '';
                                document.getElementById('vehicle_id').value = '';
                                document.getElementById('addFillingBtn').disabled = true;
                                // Clear search results if visible
                                const searchResults = document.getElementById('alkaziSearchResults');
                                if (searchResults) {
                                    searchResults.style.display = 'none';
                                    searchResults.innerHTML = '';
                                }
                            }

                            // Listen for form changes to enable/disable submit button
                            document.addEventListener('DOMContentLoaded', function() {
                                const fuelingForm = document.getElementById('fuelingForm');
                                if (fuelingForm) {
                                    fuelingForm.addEventListener('input', function() {
                                        const vehicleId = document.getElementById('vehicle_id').value;
                                        const liters = document.getElementById('liters').value;
                                        const addBtn = document.getElementById('addFillingBtn');
                                        
                                        if (addBtn) {
                                            addBtn.disabled = !(vehicleId && liters && parseFloat(liters) > 0);
                                        }
                                    });
                                }
                            });

                            // Auto-calculate total cost when liters change (you can adjust fuel prices)
                            document.addEventListener('DOMContentLoaded', function() {
                                const litersInput = document.getElementById('liters');
                                if (litersInput) {
                                    litersInput.addEventListener('input', function() {
                                        const liters = parseFloat(this.value) || 0;
                                        const fuelType = document.getElementById('fuel_type').value;
                                        
                                        // Fuel prices in SYP per liter (adjust as needed)
                                        const fuelPrices = {
                                            'diesel': 5000,    // 5000 SYP per liter for diesel
                                            'gasoline': 8000   // 8000 SYP per liter for gasoline
                                        };
                                        
                                        const pricePerLiter = fuelPrices[fuelType] || 5000;
                                        const totalCost = liters * pricePerLiter;
                                        
                                        document.getElementById('total_cost').value = totalCost.toFixed(2);
                                    });
                                }
                            });

                            // Enhanced selectVehicleForFueling function - works with both vehicle-search.js and direct calls
                            if (typeof window.selectVehicleForFueling === 'undefined') {
                                window.selectVehicleForFueling = function(vehicle) {
                                    console.log('selectVehicleForFueling called with:', vehicle);
                                    
                                    // Hide search results
                                    const searchResults = document.getElementById('alkaziSearchResults');
                                    if (searchResults) {
                                        searchResults.style.display = 'none';
                                    }
                                    
                                    // Populate visible fields
                                    const vehicleCodeInput = document.getElementById('vehicleCodeInput');
                                    const vehicleIdInput = document.getElementById('vehicle_id');
                                    const driverNameInput = document.getElementById('driver_name');
                                    const fillDateInput = document.getElementById('fill_date');
                                    
                                    if (vehicleCodeInput) {
                                        vehicleCodeInput.value = vehicle.car_code || vehicle.make || vehicle.plate_number || '';
                                    }
                                    
                                    if (vehicleIdInput) {
                                        vehicleIdInput.value = vehicle.id || '';
                                    }
                                    
                                    if (driverNameInput) {
                                        driverNameInput.value = vehicle.driver_name || vehicle.recipient || '';
                                    }
                                    
                                    if (fillDateInput) {
                                        fillDateInput.value = new Date().toISOString().split('T')[0];
                                    }
                                    
                                    // Set fuel type and trigger cost calculation
                                    const fuelTypeInput = document.getElementById('fuel_type');
                                    if (fuelTypeInput) {
                                        let selectedFuelType = 'diesel'; // Default fallback
                                        
                                        // Check if fuel_type is valid
                                        if (vehicle.fuel_type && (vehicle.fuel_type === 'diesel' || vehicle.fuel_type === 'gasoline')) {
                                            selectedFuelType = vehicle.fuel_type;
                                        } else if (vehicle.fuel_type === '0' || !vehicle.fuel_type) {
                                            // Show warning for invalid fuel type
                                            showNotification('تحذير: نوع الوقود غير محدد لهذه الآلية. سيتم استخدام المازوت كافتراضي. يرجى تحديث بيانات الآلية.', 'warning');
                                            selectedFuelType = 'diesel';
                                        } else {
                                            // Unknown fuel type
                                            showNotification('تحذير: نوع الوقود غير معروف لهذه الآلية. سيتم استخدام المازوت كافتراضي.', 'warning');
                                            selectedFuelType = 'diesel';
                                        }
                                        
                                        fuelTypeInput.value = selectedFuelType;
                                    }
                                    
                                    // Populate all hidden car data fields
                                    const hiddenFields = {
                                        'car_name': vehicle.car_name || vehicle.make || vehicle.model || '',
                                        'department': vehicle.department || '',
                                        'plate_number': vehicle.license_plate || vehicle.plate_number || '',
                                        'chassis_number': vehicle.chassis_number || '',
                                        'engine_number': vehicle.engine_number || '',
                                        'year': vehicle.year || '',
                                        'color': vehicle.color || ''
                                    };
                                    
                                    Object.keys(hiddenFields).forEach(fieldId => {
                                        const field = document.getElementById(fieldId);
                                        if (field) {
                                            field.value = hiddenFields[fieldId];
                                            console.log(`Populated ${fieldId}: ${hiddenFields[fieldId]}`);
                                        }
                                    });
                                    
                                    // Enable the form
                                    const addFillingBtn = document.getElementById('addFillingBtn');
                                    if (addFillingBtn) {
                                        addFillingBtn.disabled = false;
                                    }
                                    
                                    // Show success message
                                    const vehicleName = vehicle.car_name || vehicle.car_code || vehicle.make || 'السيارة المختارة';
                                    if (typeof showVehicleSelectedMessage !== 'undefined') {
                                        showVehicleSelectedMessage(vehicleName);
                                    }
                                    
                                    // Trigger cost calculation if liters already entered
                                    const litersInput = document.getElementById('liters');
                                    if (litersInput && litersInput.value) {
                                        litersInput.dispatchEvent(new Event('input'));
                                    }
                                    
                                    console.log('Form populated successfully');
                                };
                            }

                            // Helper function to show notifications
                            function showNotification(message, type = 'info') {
                                const notification = document.createElement('div');
                                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; padding: 15px; border-radius: 5px;';
                                notification.innerHTML = `
                                    ${message}
                                    <button type="button" class="close" style="float: left; font-size: 20px; font-weight: bold; line-height: 1; color: #000; text-shadow: 0 1px 0 #fff; opacity: .5; background: none; border: none; cursor: pointer;" onclick="this.parentElement.remove()">
                                        <span>&times;</span>
                                    </button>
                                `;
                                document.body.appendChild(notification);
                                
                                setTimeout(() => {
                                    if (notification.parentElement) {
                                        notification.remove();
                                    }
                                }, 5000);
                            }

                            // Auto-select first result after search - integrated with vehicle-search.js
                            // The vehicle-search.js will handle both display and auto-selection

                            // Helper function to get fuel type text in Arabic
                            if (typeof getFuelTypeText === 'undefined') {
                                window.getFuelTypeText = function(fuelType) {
                                    switch(fuelType) {
                                        case 'diesel':
                                            return 'مازوت';
                                        case 'gasoline':
                                            return 'بنزين';
                                        default:
                                            return fuelType || 'غير محدد';
                                    }
                                };
                            }

                            // Show vehicle selected message
                            if (typeof showVehicleSelectedMessage === 'undefined') {
                                window.showVehicleSelectedMessage = function(vehicleName) {
                                    const messageDiv = document.createElement('div');
                                    messageDiv.className = 'alert alert-success alert-dismissible fade show';
                                    messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; padding: 15px; border-radius: 5px;';
                                    messageDiv.innerHTML = `
                                        <i class="fas fa-check-circle"></i> تم اختيار السيارة: ${vehicleName}
                                        <button type="button" class="close" onclick="this.parentElement.remove()" style="float: left; font-size: 20px; font-weight: bold; line-height: 1; color: #000; text-shadow: 0 1px 0 #fff; opacity: .5; background: none; border: none; cursor: pointer;">
                                            <span>&times;</span>
                                        </button>
                                    `;
                                    document.body.appendChild(messageDiv);
                                    
                                    setTimeout(() => {
                                        if (messageDiv.parentElement) {
                                            messageDiv.remove();
                                        }
                                    }, 3000);
                                };
                            }

                            // Form validation and button control
                            document.addEventListener('DOMContentLoaded', function() {
                                const fuelingForm = document.getElementById('fuelingForm');
                                if (fuelingForm) {
                                    // Watch for changes in vehicle_id and liters
                                    const observer = new MutationObserver(function() {
                                        const vehicleId = document.getElementById('vehicle_id').value;
                                        const liters = document.getElementById('liters').value;
                                        const addBtn = document.getElementById('addFillingBtn');
                                        
                                        if (addBtn) {
                                            const isValid = vehicleId && liters && parseFloat(liters) > 0;
                                            addBtn.disabled = !isValid;
                                            console.log('Form validation - vehicleId:', vehicleId, 'liters:', liters, 'valid:', isValid);
                                        }
                                    });
                                    
                                    // Observe vehicle_id changes
                                    const vehicleIdInput = document.getElementById('vehicle_id');
                                    if (vehicleIdInput) {
                                        observer.observe(vehicleIdInput, { attributes: true, attributeFilter: ['value'] });
                                    }
                                    
                                    // Listen for liters input
                                    const litersInput = document.getElementById('liters');
                                    if (litersInput) {
                                        litersInput.addEventListener('input', function() {
                                            observer.disconnect();
                                            observer.observe(vehicleIdInput, { attributes: true, attributeFilter: ['value'] });
                                        });
                                    }
                                }
                            });

                            // Reset function
                            window.resetFuelingForm = function() {
                                const form = document.getElementById('fuelingForm');
                                if (form) {
                                    form.reset();
                                }
                                const vehicleCodeInput = document.getElementById('vehicleCodeInput');
                                if (vehicleCodeInput) {
                                    vehicleCodeInput.value = '';
                                }
                                const addFillingBtn = document.getElementById('addFillingBtn');
                                if (addFillingBtn) {
                                    addFillingBtn.disabled = true;
                                }
                                const searchResults = document.getElementById('alkaziSearchResults');
                                if (searchResults) {
                                    searchResults.style.display = 'none';
                                    searchResults.innerHTML = '';
                                }
                                console.log('Form reset');
                            };

                            // Clear search function
                            window.clearAlkaziSearch = function() {
                                const searchInput = document.getElementById('alkaziSearchInput');
                                if (searchInput) {
                                    searchInput.value = '';
                                }
                                const searchResults = document.getElementById('alkaziSearchResults');
                                if (searchResults) {
                                    searchResults.style.display = 'none';
                                    searchResults.innerHTML = '';
                                }
                                window.resetFuelingForm();
                            };

                            // Fuel Fillings Table Functionality
                            // Search by plate number
                            document.addEventListener('DOMContentLoaded', function() {
                                const searchInput = document.getElementById('fuelSearchInput');
                                if (searchInput) {
                                    searchInput.addEventListener('input', function() {
                                        const filter = this.value.toLowerCase();
                                        const table = document.getElementById('fuelFillingsTable');
                                        const tr = table.getElementsByTagName('tr');

                                        for (let i = 1; i < tr.length; i++) {
                                            const plate = tr[i].getAttribute('data-plate') || '';
                                            tr[i].style.display = plate.toLowerCase().includes(filter) ? '' : 'none';
                                        }
                                    });
                                }
                            });

                            // Filter by fuel type
                            document.addEventListener('DOMContentLoaded', function() {
                                const fuelTypeFilter = document.getElementById('fuelTypeFilter');
                                if (fuelTypeFilter) {
                                    fuelTypeFilter.addEventListener('change', function() {
                                        const filter = this.value;
                                        const table = document.getElementById('fuelFillingsTable');
                                        const tr = table.getElementsByTagName('tr');

                                        for (let i = 1; i < tr.length; i++) {
                                            const fuelType = tr[i].getAttribute('data-fuel-type') || '';
                                            tr[i].style.display = (filter === 'all' || fuelType === filter) ? '' : 'none';
                                        }
                                    });
                                }
                            });

                            // Load/Refresh Fuel Fillings Table (reload page for simplicity)
                            function loadFuelFillingsTable() {
                                location.reload();
                            }

                            // Open Fuel Edit Modal
                            function openFuelEditModal(id) {
                                // Get the clicked row
                                const row = document.querySelector(`#fuelFillingsTable tr[data-id="${id}"]`);
                                if (!row) return;

                                // Populate form fields from row data attributes
                                document.getElementById('editFuelId').value = row.getAttribute('data-id');
                                document.getElementById('editVehicleId').value = row.getAttribute('data-vehicle-id');
                                document.getElementById('editOriginalFillDate').value = row.cells[4].textContent.trim();
                                document.getElementById('editFuelVehicle').value = row.cells[1].textContent.trim(); // plate - model

                                // Set fuel type
                                const fuelTypeSelect = document.getElementById('editFuelType');
                                const fuelType = row.getAttribute('data-fuel-type');
                                if (fuelTypeSelect) {
                                    for (let option of fuelTypeSelect.options) {
                                        option.selected = (option.value === fuelType);
                                    }
                                }

                                // Parse liters from cells
                                const litersCell = row.cells[3].textContent.trim();
                                if (litersCell) document.getElementById('editFuelLiters').value = parseFloat(litersCell).toFixed(2);

                                // Show modal
                                document.getElementById('fuelEditModal').style.display = 'block';
                            }

                            // Close Fuel Edit Modal
                            function closeFuelEditModal() {
                                document.getElementById('fuelEditModal').style.display = 'none';
                                // Reset form
                                document.getElementById('fuelEditForm').reset();
                            }

                            // Handle Fuel Edit Form Submission (AJAX)
                            document.addEventListener('DOMContentLoaded', function() {
                                const fuelEditForm = document.getElementById('fuelEditForm');
                                if (fuelEditForm) {
                                    fuelEditForm.addEventListener('submit', function(e) {
                                        e.preventDefault();

                                        const formData = new FormData(fuelEditForm);
                                        const submitBtn = fuelEditForm.querySelector('button[type="submit"]');
                                        const originalText = submitBtn.innerHTML;
                                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
                                        submitBtn.disabled = true;

                                        fetch('pages/update_gas_log.php', {
                                            method: 'POST',
                                            body: formData
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                // Update the table row with new data
                                                const logId = document.getElementById('editFuelId').value;
                                                const row = document.querySelector(`#fuelFillingsTable tr[data-id="${logId}"]`);
                                                if (row) {
                                                    // Update fuel type
                                                    const fuelTypeCell = row.cells[2];
                                                    const newFuelType = document.getElementById('editFuelType').value;
                                                    const fuelTypeAr = newFuelType === 'diesel' ? 'مازوت' : 'بنزين';
                                                    fuelTypeCell.innerHTML = `<span class='fuel-type-badge ${newFuelType}'>${fuelTypeAr}</span>`;

                                                    // Update liters
                                                    const litersCell = row.cells[3];
                                                    const newLiters = document.getElementById('editFuelLiters').value;
                                                    litersCell.textContent = parseFloat(newLiters).toFixed(2);

                                                    // Update notes
                                                    const notesCell = row.cells[5];
                                                    const newNotes = document.getElementById('editFuelNotes').value;
                                                    notesCell.textContent = newNotes;
                                                }

                                                // Show success message
                                                showNotification(data.message || 'تم تحديث السجل بنجاح', 'success');
                                                closeFuelEditModal();
                                            } else {
                                                showNotification(data.message || 'فشل في تحديث السجل', 'error');
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            showNotification('حدث خطأ في الاتصال بالخادم', 'error');
                                        })
                                        .finally(() => {
                                            submitBtn.innerHTML = originalText;
                                            submitBtn.disabled = false;
                                        });
                                    });
                                }
                            });

                            // Close modal when clicking outside
                            window.addEventListener('click', function(event) {
                                const modal = document.getElementById('fuelEditModal');
                                if (event.target === modal) {
                                    closeFuelEditModal();
                                }
                            });
                        </script>

                        <style>
                            /* Highlight first result */
                            .first-result-highlight {
                                border-left: 4px solid #28a745;
                                background-color: #f8fff9;
                            }
                            
                            .vehicle-status.secondary {
                                background: #e2e3e5;
                                color: #383d41;
                            }
                            
                            .alert {
                                background-color: #d4edda;
                                border: 1px solid #c3e6cb;
                                color: #155724;
                                padding: 12px;
                                border-radius: 4px;
                                margin-bottom: 15px;
                            }
                            
                            .alert-success {
                                background-color: #d4edda;
                                border-color: #c3e6cb;
                                color: #155724;
                            }
                            
                            .close {
                                float: left;
                                font-size: 20px;
                                font-weight: bold;
                                line-height: 1;
                                color: #000;
                                text-shadow: 0 1px 0 #fff;
                                opacity: .5;
                                background: none;
                                border: none;
                                cursor: pointer;
                            }
                            
                            .close:hover {
                                opacity: .75;
                            }

                            /* Status Badge Styles */
                            .status-badge {
                                display: inline-block;
                                padding: 6px 12px;
                                margin: 0;
                                font-size: 12px;
                                font-weight: 600;
                                line-height: 1;
                                text-align: center;
                                white-space: nowrap;
                                vertical-align: baseline;
                                border-radius: 12px;
                                border: 1px solid transparent;
                                transition: all 0.3s ease;
                                direction: rtl;
                            }

                            .status-badge.success {
                                color: #155724;
                                background-color: #d4edda;
                                border-color: #c3e6cb;
                            }

                            .status-badge.warning {
                                color: #856404;
                                background-color: #fff3cd;
                                border-color: #ffeaa7;
                            }

                            .status-badge.danger {
                                color: #721c24;
                                background-color: #f8d7da;
                                border-color: #f5c6cb;
                            }

                            /* Table Header Hover Effect */
                            .table th:hover {
                                background-color: #e9ecef !important;
                            }
                        </style>

                        <!-- Statistics Cards -->
                         
                        <div class="fueling-stats" style="margin-top: 30px; display: flex; justify-content: center; gap: 30px; align-items: center;">
                            <div class="stat-card diesel" style="flex: 1; max-width: 250px; padding: 25px;">
                                <i class="fas fa-oil-can" style="font-size: 2.5em; margin-bottom: 15px;"></i>
                                <h4 style="font-size: 1.3em; margin-bottom: 10px;">إجمالي المازوت اليوم</h4>
                                <div class="stat-number" id="dailyDieselTotal"><?php echo number_format($daily_diesel_total ?? 0); ?></div>
                                <span class="stat-unit">لتر</span>
                            </div>
                            <div class="stat-card gasoline" style="flex: 1; max-width: 250px; padding: 25px;">
                                <i class="fas fa-gas-pump" style="font-size: 2.5em; margin-bottom: 15px;"></i>
                                <h4 style="font-size: 1.3em; margin-bottom: 10px;">إجمالي البنزين اليوم</h4>
                                <div class="stat-number" id="dailyGasolineTotal"><?php echo number_format($daily_gasoline_total ?? 0); ?></div>
                                <span class="stat-unit">لتر</span>
                            </div>
                        </div>

                        <!-- Fuel Fillings Table with Search and Filter -->
                        <div class="table-container" style="margin-top: 30px;">
                            <div class="section-header">
                                <h3>تعبئات الوقود اليومية</h3>
                                <div class="table-controls" style="display: flex; gap: 15px; align-items: center; margin-top: 15px; flex-wrap: wrap;">
                                    <!-- Search by Vehicle Code -->
                                    <div class="form-group" style="min-width: 250px;">
                                        <label for="fuelSearchInput">البحث برقم اللوحة</label>
                                        <div class="input-group" style="position: relative;">
                                            <input type="text" id="fuelSearchInput" class="form-control" placeholder="ابحث برقم اللوحة..." style="padding-right: 40px;">
                                            <i class="fas fa-search" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Filter by Fuel Type -->
                                    <div class="form-group" style="min-width: 200px;">
                                        <label for="fuelTypeFilter">تصفية حسب نوع الوقود</label>
                                        <select id="fuelTypeFilter" class="form-control">
                                            <option value="all">جميع الأنواع</option>
                                            <option value="diesel">مازوت</option>
                                            <option value="gasoline">بنزين</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Refresh Button -->
                                    <button class="btn btn-secondary" onclick="loadFuelFillingsTable()" style="margin-top: 25px; white-space: nowrap;">
                                        <i class="fas fa-sync-alt"></i> تحديث
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Fuel Fillings Table -->
                            <div id="fuelFillingsTableContainer">
                                <table id="fuelFillingsTable" class="fuel-fillings-table">
                                    <thead>
                                        <tr>
                                            <th>رقم السجل</th>
                                            <th>الآلية</th>
                                            <th>نوع الوقود</th>
                                            <th>الكمية (لتر)</th>
                                            <th>التاريخ والوقت</th>
                                            <th>الملاحظات</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fuelFillingsTableBody">
                                        <?php
                                        if ($today_fillings_result && $today_fillings_result->num_rows > 0) {
                                            while($row = $today_fillings_result->fetch_assoc()) {
                                                $fuel_type_ar = ($row["fuel_type"] == 'diesel') ? 'مازوت' : 'بنزين';
                                                $full_datetime = date('Y-m-d H:i:s', strtotime($row["fill_date"]));
                                                echo "<tr data-id='" . $row["id"] . "' data-vehicle-id='" . $row["vehicle_id"] . "' data-fuel-type='" . $row["fuel_type"] . "' data-plate='" . htmlspecialchars($row["plate_number"]) . "'>";
                                                echo "<td>" . $row["id"] . "</td>";
                                                echo "<td>" . htmlspecialchars($row["plate_number"] . ' - ' . $row["model"]) . "</td>";
                                                echo "<td><span class='fuel-type-badge " . $row["fuel_type"] . "'>" . $fuel_type_ar . "</span></td>";
                                                echo "<td>" . number_format($row["liters"], 2) . "</td>";
                                                echo "<td>" . $full_datetime . "</td>";
                                                echo "<td>" . htmlspecialchars($row["notes"]) . "</td>";
                                                echo "<td>";
                                                echo "<button class='btn-icon btn-edit' onclick='openFuelEditModal(" . $row["id"] . ")' title='تعديل'>";
                                                echo "<i class='fas fa-edit'></i>";
                                                echo "</button>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>لا توجد سجلات تعبئة اليوم</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Fuel Edit Modal -->
                        <div class="modal" id="fuelEditModal" style="display: none;">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>تعديل تعبئة الوقود</h3>
                                    <button class="modal-close" onclick="closeFuelEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="fuelEditForm" method="post" action="pages/update_gas_log.php">
                                        <input type="hidden" id="editFuelId" name="id" value="">
                                        <input type="hidden" id="editVehicleId" name="vehicle_id" value="">
                                        <input type="hidden" id="editOriginalFillDate" name="fill_date" value="">
                                        
                                        <div class="form-row">
                                            <div class="form-group form-group-full">
                                                <label for="editFuelVehicle">الآلية</label>
                                                <input type="text" id="editFuelVehicle" readonly style="background-color: #f8f9fa;">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group form-group-half">
                                                <label for="editFuelType">نوع الوقود</label>
                                                <select id="editFuelType" name="fuel_type" required>
                                                    <option value="diesel">مازوت</option>
                                                    <option value="gasoline">بنزين</option>
                                                </select>
                                            </div>
                                            <div class="form-group form-group-half">
                                                <label for="editFuelLiters">الكمية (لتر)</label>
                                                <input type="number" step="0.01" id="editFuelLiters" name="liters" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group form-group-full">
                                                <label for="editFuelNotes">ملاحظات</label>
                                                <textarea id="editFuelNotes" name="notes" rows="2" placeholder="ملاحظات (اختياري)"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" onclick="closeFuelEditModal()">إلغاء</button>
                                            <button type="submit" class="btn btn-primary">تحديث السجل</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Tanks Management Tab -->
                    <div id="tanks" class="tab-content" style="display: none;">
                        <div class="section-header">
                            <h3><i class="fas fa-tint"></i> إدارة الخزانات</h3>
                            <button class="btn btn-primary" onclick="openAddTankModal()">
                                <i class="fas fa-plus"></i> إضافة خزان جديد
                            </button>
                        </div>

                        <div class="tanks-grid">
                            <div class="tank-card diesel-tank">
                                <div class="tank-header">
                                    <h4>خزان المازوت</h4>
                                    <div class="tank-status active">نشط</div>
                                </div>
                                <div class="tank-level-container">
                                    <div class="level-bar" style="height: 75%;">
                                        <span class="level-text">75%</span>
                                    </div>
                                </div>
                                <div class="tank-info">
                                    <p><strong>السعة الكلية:</strong> 10,000 لتر</p>
                                    <p><strong>الكمية الحالية:</strong> 7,500 لتر</p>
                                    <p><strong>الكمية المتبقية:</strong> 2,500 لتر</p>
                                </div>
                                <div class="tank-actions">
                                    <button class="btn btn-success" onclick="openIncomingModal('diesel')">
                                        <i class="fas fa-arrow-down"></i> وارد
                                    </button>
                                    <button class="btn btn-warning" onclick="openReturnedModal('diesel')">
                                        <i class="fas fa-arrow-up"></i> مرتجع
                                    </button>
                                    <button class="btn btn-info" onclick="openTankRecordModal('diesel')">
                                        <i class="fas fa-history"></i> سجل الخزان
                                    </button>
                                </div>
                            </div>

                            <div class="tank-card gasoline-tank">
                                <div class="tank-header">
                                    <h4>خزان البنزين</h4>
                                    <div class="tank-status low">منخفض</div>
                                </div>
                                <div class="tank-level-container">
                                    <div class="level-bar low" style="height: 30%;">
                                        <span class="level-text">30%</span>
                                    </div>
                                </div>
                                <div class="tank-info">
                                    <p><strong>السعة الكلية:</strong> 5,000 لتر</p>
                                    <p><strong>الكمية الحالية:</strong> 1,500 لتر</p>
                                    <p><strong>الكمية المتبقية:</strong> 3,500 لتر</p>
                                </div>
                                <div class="tank-actions">
                                    <button class="btn btn-success" onclick="openIncomingModal('gasoline')">
                                        <i class="fas fa-arrow-down"></i> وارد
                                    </button>
                                    <button class="btn btn-warning" onclick="openReturnedModal('gasoline')">
                                        <i class="fas fa-arrow-up"></i> مرتجع
                                    </button>
                                    <button class="btn btn-info" onclick="openTankRecordModal('gasoline')">
                                        <i class="fas fa-history"></i> سجل الخزان
                                    </button>
                                </div>
                            </div>

                        </div>

                        <div class="tank-history">
                            <h4>سجل تحديثات الخزانات</h4>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>نوع الخزان</th>
                                            <th>المستوى السابق</th>
                                            <th>المستوى الجديد</th>
                                            <th>الفرق</th>
                                            <th>الملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>2025-09-10</td>
                                            <td>مازوت</td>
                                            <td>7,200 لتر</td>
                                            <td>7,500 لتر</td>
                                            <td>+300 لتر</td>
                                            <td>تعبئة يومية</td>
                                        </tr>
                                        <tr>
                                            <td>2025-09-09</td>
                                            <td>بنزين</td>
                                            <td>1,800 لتر</td>
                                            <td>1,500 لتر</td>
                                            <td>-300 لتر</td>
                                            <td>استهلاك يومي</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Incoming Fuel Modal -->
                        <div class="modal" id="incomingModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>إضافة وارد للخزان</h3>
                                    <button class="modal-close" onclick="closeIncomingModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="incomingForm">
                                        <input type="hidden" id="incomingTankType" name="tank_type">
                                        <div class="form-group">
                                            <label for="incomingDate">تاريخ الوارد</label>
                                            <input type="date" id="incomingDate" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="incomingQuantity">الكمية (لتر)</label>
                                            <input type="number" id="incomingQuantity" step="0.01" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="incomingSupplier">المورد</label>
                                            <input type="text" id="incomingSupplier" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="incomingNotes">ملاحظات</label>
                                            <textarea id="incomingNotes" rows="3"></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" onclick="closeIncomingModal()">إلغاء</button>
                                            <button type="submit" class="btn btn-success">حفظ الوارد</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Returned Fuel Modal -->
                        <div class="modal" id="returnedModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>إضافة مرتجع للخزان</h3>
                                    <button class="modal-close" onclick="closeReturnedModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="returnedForm">
                                        <input type="hidden" id="returnedTankType" name="tank_type">
                                        <div class="form-group">
                                            <label for="returnedDate">تاريخ المرتجع</label>
                                            <input type="date" id="returnedDate" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="returnedQuantity">الكمية المرتجعة (لتر)</label>
                                            <input type="number" id="returnedQuantity" step="0.01" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="returnedReason">سبب المرتجع</label>
                                            <input type="text" id="returnedReason" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="returnedNotes">ملاحظات</label>
                                            <textarea id="returnedNotes" rows="3"></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" onclick="closeReturnedModal()">إلغاء</button>
                                            <button type="submit" class="btn btn-success" id="returnedSubmitBtn">حفظ المرتجع</button>
                                        </div>
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const returnedForm = document.getElementById('returnedForm');
                                                const returnedSubmitBtn = document.getElementById('returnedSubmitBtn');
                                                if (returnedForm && returnedSubmitBtn) {
                                                    returnedForm.addEventListener('submit', function(e) {
                                                        e.preventDefault();
                                                        // In real implementation, send data to server
                                                        alert('تم حفظ المرتجع بنجاح');
                                                        closeReturnedModal();
                                                        // Do not refresh tank levels or change button text
                                                    });
                                                }
                                            });
                                        </script>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Tank Record Modal -->
                        <div class="modal" id="tankRecordModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>سجل الخزان</h3>
                                    <button class="modal-close" onclick="closeTankRecordModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="recordDate">تاريخ السجل</label>
                                        <input type="date" id="recordDate" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="recordLevel">مستوى الخزان (لتر)</label>
                                        <input type="number" id="recordLevel" step="0.01" min="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="recordNotes">ملاحظات</label>
                                        <textarea id="recordNotes" rows="3"></textarea>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeTankRecordModal()">إلغاء</button>
                                        <button type="button" class="btn btn-info" onclick="viewTankRecords()">عرض السجلات</button>
                                        <button type="submit" class="btn btn-success" form="addRecordForm">إضافة سجل</button>
                                    </div>
                                    <form id="addRecordForm" style="margin-top: 20px;">
                                        <input type="hidden" id="recordTankType" name="tank_type">
                                        <!-- Record fields will be populated by JS -->
                                    </form>
                                    <div id="tankRecordsList" style="margin-top: 20px;"></div>
                                </div>
                            </div>
                        </div>

                        <script>
                            // Tank Management Modal Functions
                            let currentTankType = '';

                            function openIncomingModal(tankType) {
                                currentTankType = tankType;
                                document.getElementById('incomingModal').style.display = 'block';
                                document.getElementById('incomingTankType').value = tankType;
                                document.getElementById('incomingDate').value = new Date().toISOString().split('T')[0];
                                
                                // Set modal title based on tank type
                                const modalTitle = document.querySelector('#incomingModal .modal-header h3');
                                const tankName = tankType === 'diesel' ? 'المازوت' : 'البنزين';
                                modalTitle.textContent = `إضافة وارد لخزان ${tankName}`;
                                
                                // Clear form
                                document.getElementById('incomingQuantity').value = '';
                                document.getElementById('incomingSupplier').value = '';
                                document.getElementById('incomingNotes').value = '';
                            }

                            function closeIncomingModal() {
                                document.getElementById('incomingModal').style.display = 'none';
                            }

                            function openReturnedModal(tankType) {
                                currentTankType = tankType;
                                document.getElementById('returnedModal').style.display = 'block';
                                document.getElementById('returnedTankType').value = tankType;
                                document.getElementById('returnedDate').value = new Date().toISOString().split('T')[0];
                                
                                // Set modal title based on tank type
                                const modalTitle = document.querySelector('#returnedModal .modal-header h3');
                                const tankName = tankType === 'diesel' ? 'المازوت' : 'البنزين';
                                modalTitle.textContent = `إضافة مرتجع لخزان ${tankName}`;
                                
                                // Clear form
                                document.getElementById('returnedQuantity').value = '';
                                document.getElementById('returnedReason').value = '';
                                document.getElementById('returnedNotes').value = '';
                            }

                            function closeReturnedModal() {
                                document.getElementById('returnedModal').style.display = 'none';
                            }

                            function openTankRecordModal(tankType) {
                                currentTankType = tankType;
                                document.getElementById('tankRecordModal').style.display = 'block';
                                document.getElementById('recordTankType').value = tankType;
                                document.getElementById('recordDate').value = new Date().toISOString().split('T')[0];
                                
                                // Set modal title based on tank type
                                const modalTitle = document.querySelector('#tankRecordModal .modal-header h3');
                                const tankName = tankType === 'diesel' ? 'المازوت' : 'البنزين';
                                modalTitle.textContent = `سجل خزان ${tankName}`;
                                
                                // Clear form
                                document.getElementById('recordLevel').value = '';
                                document.getElementById('recordNotes').value = '';
                                document.getElementById('tankRecordsList').innerHTML = '';
                                
                                // Load tank records
                                loadTankRecords(tankType);
                            }

                            function closeTankRecordModal() {
                                document.getElementById('tankRecordModal').style.display = 'none';
                            }

                            function loadTankRecords(tankType) {
                                // Simulate loading tank records - in real implementation, make AJAX call
                                const recordsList = document.getElementById('tankRecordsList');
                                const tankName = tankType === 'diesel' ? 'مازوت' : 'بنزين';
                                
                                // Sample data
                                const sampleRecords = [
                                    { date: '2025-09-10', level: '7500', notes: 'تعبئة يومية' },
                                    { date: '2025-09-09', level: '7200', notes: 'قياس دوري' },
                                    { date: '2025-09-08', level: '6800', notes: 'بعد توزيع' }
                                ];
                                
                                let recordsHTML = `
                                    <h4>آخر سجلات خزان ${tankName}</h4>
                                    <div class="table-container">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>التاريخ</th>
                                                    <th>المستوى (لتر)</th>
                                                    <th>الملاحظات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                `;
                                
                                sampleRecords.forEach(record => {
                                    recordsHTML += `
                                        <tr>
                                            <td>${record.date}</td>
                                            <td>${record.level}</td>
                                            <td>${record.notes}</td>
                                        </tr>
                                    `;
                                });
                                
                                recordsHTML += `
                                            </tbody>
                                        </table>
                                    </div>
                                `;
                                
                                recordsList.innerHTML = recordsHTML;
                            }

                            function viewTankRecords() {
                                // This function can be used to refresh or filter records
                                loadTankRecords(currentTankType);
                            }

                            // Form submission handlers
                            document.addEventListener('DOMContentLoaded', function() {
                                // Incoming form
                                const incomingForm = document.getElementById('incomingForm');
                                if (incomingForm) {
                                    incomingForm.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        // In real implementation, send data to server
                                        alert('تم حفظ الوارد بنجاح');
                                        closeIncomingModal();
                                        // Refresh tank levels if needed
                                    });
                                }

                                // Returned form
                                const returnedForm = document.getElementById('returnedForm');
                                if (returnedForm) {
                                    returnedForm.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        // In real implementation, send data to server
                                        alert('تم حفظ المرتجع بنجاح');
                                        closeReturnedModal();
                                        // Refresh tank levels if needed
                                    });
                                }

                                // Add record form
                                const addRecordForm = document.getElementById('addRecordForm');
                                if (addRecordForm) {
                                    addRecordForm.addEventListener('submit', function(e) {
                                        e.preventDefault();
                                        // In real implementation, send data to server
                                        alert('تم إضافة السجل بنجاح');
                                        loadTankRecords(currentTankType);
                                        // Clear form
                                        document.getElementById('recordLevel').value = '';
                                        document.getElementById('recordNotes').value = '';
                                    });
                                }

                                // Close modals when clicking outside
                                window.onclick = function(event) {
                                    const incomingModal = document.getElementById('incomingModal');
                                    const returnedModal = document.getElementById('returnedModal');
                                    const tankRecordModal = document.getElementById('tankRecordModal');
                                    
                                    if (event.target === incomingModal) {
                                        closeIncomingModal();
                                    }
                                    if (event.target === returnedModal) {
                                        closeReturnedModal();
                                    }
                                    if (event.target === tankRecordModal) {
                                        closeTankRecordModal();
                                    }
                                }
                            });

                            // Tank type display names
                            const tankNames = {
                                'diesel': 'المازوت',
                                'gasoline': 'البنزين'
                            };

                            // Auto-set current date in all date inputs
                            document.addEventListener('DOMContentLoaded', function() {
                                const dateInputs = document.querySelectorAll('input[type="date"]');
                                dateInputs.forEach(input => {
                                    if (!input.value) {
                                        input.value = new Date().toISOString().split('T')[0];
                                    }
                                });
                            });
                        </script>

                        <!-- Balance Inventory Section -->
                        <div class="inventory-section" style="margin-top: 30px;">
                            <div class="section-header">
                                <h3><i class="fas fa-balance-scale"></i> الجرد الرصيدي</h3>
                                <p>مقارنة الأرصدة الفعلية مع الواقع وحساب الفروقات</p>
                                <button class="btn btn-primary" onclick="openBalanceInventoryModal()">
                                    <i class="fas fa-plus"></i> إضافة جرد رصيدي
                                </button>
                            </div>
                            <div class="table-container">
                                <table class="table" id="balanceInventoryTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>نوع الخزان</th>
                                            <th>الرصيد الدفتري</th>
                                            <th>الرصيد الفعلي</th>
                                            <th>الفرق</th>
                                            <th>نسبة الفرق</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>2025-09-10</td>
                                            <td>مازوت</td>
                                            <td>7,500 لتر</td>
                                            <td>7,450 لتر</td>
                                            <td>-50 لتر</td>
                                            <td>0.67%</td>
                                            <td><span class="status-badge warning">فرق طفيف</span></td>
                                            <td>
                                                <button class="btn-icon" onclick="editBalanceInventory(1)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>2025-09-10</td>
                                            <td>بنزين</td>
                                            <td>1,500 لتر</td>
                                            <td>1,480 لتر</td>
                                            <td>-20 لتر</td>
                                            <td>1.33%</td>
                                            <td><span class="status-badge warning">فرق طفيف</span></td>
                                            <td>
                                                <button class="btn-icon" onclick="editBalanceInventory(2)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Book Inventory Modal -->
                        <div class="modal" id="bookInventoryModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>إضافة جرد دفتري</h3>
                                    <button class="modal-close" onclick="closeBookInventoryModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="bookInventoryForm">
                                        <div class="form-group">
                                            <label for="bookInventoryDate">تاريخ الجرد</label>
                                            <input type="date" id="bookInventoryDate" required>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group form-group-half">
                                                <label for="bookDieselBalance">رصيد المازوت الدفتري</label>
                                                <input type="number" id="bookDieselBalance" step="0.01" placeholder="7,500" required>
                                                <small class="form-text">لتر</small>
                                            </div>
                                            <div class="form-group form-group-half">
                                                <label for="bookGasolineBalance">رصيد البنزين الدفتري</label>
                                                <input type="number" id="bookGasolineBalance" step="0.01" placeholder="1,500" required>
                                                <small class="form-text">لتر</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="bookInventoryNotes">ملاحظات</label>
                                            <textarea id="bookInventoryNotes" rows="3" placeholder="ملاحظات حول الجرد..."></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" onclick="closeBookInventoryModal()">إلغاء</button>
                                            <button type="submit" class="btn btn-success">حفظ الجرد</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Inventory Modal -->
                        <div class="modal" id="balanceInventoryModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>إضافة جرد رصيدي</h3>
                                    <button class="modal-close" onclick="closeBalanceInventoryModal()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="balanceInventoryForm">
                                        <div class="form-group">
                                            <label for="balanceInventoryDate">تاريخ الجرد</label>
                                            <input type="date" id="balanceInventoryDate" required>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group form-group-half">
                                                <label for="balanceDieselBook">رصيد المازوت الدفتري</label>
                                                <input type="number" id="balanceDieselBook" step="0.01" placeholder="7,500" required>
                                                <small class="form-text">لتر</small>
                                            </div>
                                            <div class="form-group form-group-half">
                                                <label for="balanceDieselActual">رصيد المازوت الفعلي</label>
                                                <input type="number" id="balanceDieselActual" step="0.01" placeholder="7,450" required>
                                                <small class="form-text">لتر (قياس فعلي)</small>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group form-group-half">
                                                <label for="balanceGasolineBook">رصيد البنزين الدفتري</label>
                                                <input type="number" id="balanceGasolineBook" step="0.01" placeholder="1,500" required>
                                                <small class="form-text">لتر</small>
                                            </div>
                                            <div class="form-group form-group-half">
                                                <label for="balanceGasolineActual">رصيد البنزين الفعلي</label>
                                                <input type="number" id="balanceGasolineActual" step="0.01" placeholder="1,480" required>
                                                <small class="form-text">لتر (قياس فعلي)</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="balanceInventoryNotes">ملاحظات وتفسير الفروقات</label>
                                            <textarea id="balanceInventoryNotes" rows="3" placeholder="تفسير الفروقات وأسبابها..."></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" onclick="closeBalanceInventoryModal()">إلغاء</button>
                                            <button type="submit" class="btn btn-success">حفظ الجرد وحساب الفروقات</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicles Management Tab -->
                    <div id="vehicles" class="tab-content" style="display: none;">
                        <div class="section-header">
                            <h3><i class="fas fa-car"></i> إدارة الآليات المسجلة</h3>
                            <button class="btn btn-primary" onclick="openVehicleRegistrationModal()">
                                <i class="fas fa-plus"></i> تسجيل آلية جديدة
                            </button>
                        </div>

                        <div class="vehicles-grid">
                            <div class="vehicle-card">
                                <div class="vehicle-header">
                                    <div class="vehicle-icon truck">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <h4>TR-01 - شاحنة نقل</h4>
                                    <span class="vehicle-status active">نشطة</span>
                                </div>
                                <div class="vehicle-details">
                                    <p><strong>نوع الوقود:</strong> مازوت</p>
                                    <p><strong>آخر تعبئة:</strong> 2025-09-10</p>
                                    <p><strong>إجمالي التعبئة:</strong> 2,450 لتر</p>
                                    <p><strong>التكلفة الإجمالية:</strong> 1,225,000 ل.س</p>
                                </div>
                                <div class="vehicle-actions">
                                    <button class="btn btn-info" onclick="viewVehicleHistory('TR-01')">
                                        <i class="fas fa-history"></i> سجل التعبئة
                                    </button>
                                    <button class="btn btn-warning" onclick="editVehicleDetails('TR-01')">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                </div>
                            </div>

                            <div class="vehicle-card">
                                <div class="vehicle-header">
                                    <div class="vehicle-icon excavator">
                                        <i class="fas fa-tractor"></i>
                                    </div>
                                    <h4>GR-101 - جرافة</h4>
                                    <span class="vehicle-status active">نشطة</span>
                                </div>
                                <div class="vehicle-details">
                                    <p><strong>نوع الوقود:</strong> مازوت</p>
                                    <p><strong>آخر تعبئة:</strong> 2025-09-09</p>
                                    <p><strong>إجمالي التعبئة:</strong> 1,800 لتر</p>
                                    <p><strong>التكلفة الإجمالية:</strong> 900,000 ل.س</p>
                                </div>
                                <div class="vehicle-actions">
                                    <button class="btn btn-info" onclick="viewVehicleHistory('GR-101')">
                                        <i class="fas fa-history"></i> سجل التعبئة
                                    </button>
                                    <button class="btn btn-warning" onclick="editVehicleDetails('GR-101')">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                </div>
                            </div>

                            <div class="vehicle-card">
                                <div class="vehicle-header">
                                    <div class="vehicle-icon forklift">
                                        <i class="fas fa-pallet"></i>
                                    </div>
                                    <h4>FL-103 - رافعة شوكية</h4>
                                    <span class="vehicle-status maintenance">صيانة</span>
                                </div>
                                <div class="vehicle-details">
                                    <p><strong>نوع الوقود:</strong> بنزين</p>
                                    <p><strong>آخر تعبئة:</strong> 2025-09-08</p>
                                    <p><strong>إجمالي التعبئة:</strong> 450 لتر</p>
                                    <p><strong>التكلفة الإجمالية:</strong> 337,500 ل.س</p>
                                </div>
                                <div class="vehicle-actions">
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-history"></i> سجل التعبئة
                                    </button>
                                    <button class="btn btn-danger" onclick="markVehicleInactive('FL-103')">
                                        <i class="fas fa-power-off"></i> تعطيل
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="section-header">
                            <h3><i class="fas fa-list"></i> قائمة الآليات الكاملة</h3>
                        </div>

                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>كود الآلية</th>
                                        <th>النوع</th>
                                        <th>رقم اللوحة</th>
                                        <th>نوع الوقود</th>
                                        <th>الحالة</th>
                                        <th>آخر تعبئة</th>
                                        <th>إجمالي التعبئة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>TR-01</td>
                                        <td>شاحنة نقل</td>
                                        <td>258456</td>
                                        <td>مازوت</td>
                                        <td><span class="vehicle-status active">نشطة</span></td>
                                        <td>2025-09-10</td>
                                        <td>2,450 لتر</td>
                                        <td>
                                            <button class="btn-icon btn-edit" onclick="editVehicleDetails('TR-01')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-view" onclick="viewVehicleHistory('TR-01')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>GR-101</td>
                                        <td>جرافة</td>
                                        <td>54321</td>
                                        <td>مازوت</td>
                                        <td><span class="vehicle-status active">نشطة</span></td>
                                        <td>2025-09-09</td>
                                        <td>1,800 لتر</td>
                                        <td>
                                            <button class="btn-icon btn-edit" onclick="editVehicleDetails('GR-101')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-view" onclick="viewVehicleHistory('GR-101')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>FL-103</td>
                                        <td>رافعة شوكية</td>
                                        <td>12345</td>
                                        <td>بنزين</td>
                                        <td><span class="vehicle-status maintenance">صيانة</span></td>
                                        <td>2025-09-08</td>
                                        <td>450 لتر</td>
                                        <td>
                                            <button class="btn-icon btn-edit" onclick="editVehicleDetails('FL-103')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-view disabled">
                                                <i class="fas fa-eye-slash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Reports Tab -->
                    <div id="reports" class="tab-content" style="display: none;">
                        <div class="section-header">
                            <h3><i class="fas fa-chart-bar"></i> تقارير وإحصائيات الكازية</h3>
                            <div class="report-filters">
                                <div class="form-group form-group-half">
                                    <label for="vehicleCodeFilter">كود الآلية</label>
                                    <input type="text" id="vehicleCodeFilter" placeholder="أدخل كود الآلية (مثال: TR-01)">
                                </div>
                                <div class="form-group form-group-half">
                                    <label for="departmentFilter">القسم</label>
                                    <select id="departmentFilter">
                                        <option value="all">جميع الأقسام</option>
                                        <option value="النقل الثقيل">النقل الثقيل</option>
                                        <option value="قسم الهندسة">قسم الهندسة</option>
                                        <option value="المستودع">المستودع</option>
                                        <option value="الصيانة">الصيانة</option>
                                    </select>
                                </div>
                                <div class="form-group form-group-half">
                                    <label for="startDateFilter">من تاريخ</label>
                                    <input type="date" id="startDateFilter">
                                </div>
                                <div class="form-group form-group-half">
                                    <label for="endDateFilter">إلى تاريخ</label>
                                    <input type="date" id="endDateFilter">
                                </div>
                                <div class="filter-actions">
                                    <button class="btn btn-primary" onclick="generateDetailedReport()">
                                        <i class="fas fa-search"></i> عرض التقرير المفصل
                                    </button>
                                    <button class="btn btn-info" onclick="printReport()">
                                        <i class="fas fa-print"></i> طباعة
                                    </button>
                                    <button class="btn btn-success" onclick="exportToPDF()">
                                        <i class="fas fa-file-pdf"></i> تصدير PDF
                                    </button>
                                    <button class="btn btn-secondary" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel"></i> تصدير Excel
                                    </button>
                                </div>
                            </div>
                        </div>


                        <div class="detailed-reports">
                            <div class="report-section">
                                <h4>أكثر الآليات استهلاكاً</h4>
                                <div class="table-container">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>الآلية</th>
                                                <th>إجمالي التعبئة (لتر)</th>
                                                <th>عدد التعبئات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>TR-01 - شاحنة نقل</td>
                                                <td>2,450 لتر</td>
                                                <td>12</td>
                                            </tr>
                                            <tr>
                                                <td>GR-101 - جرافة</td>
                                                <td>1,800 لتر</td>
                                                <td>8</td>
                                            </tr>
                                            <tr>
                                                <td>FL-103 - رافعة</td>
                                                <td>450 لتر</td>
                                                <td>3</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-warehouse" class="page-wrapper" style="display: none;">
                    <!-- Warehouse Header -->
                    <div class="warehouse-header">
                        <div class="warehouse-actions">
                            <button class="btn btn-primary" onclick="openAddItemModal()">
                                <i class="fas fa-plus"></i>
                                إضافة قلم
                            </button>
                            <button class="btn btn-success" onclick="openWithdrawModal()">
                                <i class="fas fa-minus"></i>
                                تخريج قلم
                            </button>
                            <button class="btn btn-info" onclick="openAddInvoiceModal()">
                                <i class="fas fa-file-invoice"></i>
                                إضافة فاتورة
                            </button>
                            <button class="btn btn-secondary" onclick="openViewInvoicesPanel()">
                                <i class="fas fa-list-alt"></i>
                                عرض الفواتير
                            </button>
                            <div class="search-box">
                                <input type="text" id="searchInput" placeholder="البحث في الأقلام..." onkeyup="searchItems()">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-container">
                        <table class="items-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th>اسم القلم</th>
                                    <th>الكمية</th>
                                    <th>سعر الوحدة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <?php
                                if ($warehouse_items_result->num_rows > 0) {
                                    while($row = $warehouse_items_result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row["item_name"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["quantity"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["unit_price"]) . "</td>";
                                        echo '<td>
                                                <button class="btn-icon btn-edit" onclick="editItem(' . $row['id'] . ')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon btn-delete" onclick="deleteItem(' . $row['id'] . ')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>';
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>لا توجد عناصر في المستودع حالياً</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Item Modal -->
                    <div class="modal" id="addItemModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>إضافة قلم جديد</h3>
                                <button class="modal-close" onclick="closeAddItemModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form id="addItemForm" action="main.php" method="post">
                                    <div class="form-group">
                                        <label for="itemName">اسم القلم</label>
                                        <input type="text" id="itemName" name="itemName" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="itemQuantity">الكمية</label>
                                        <input type="number" id="itemQuantity" name="itemQuantity" min="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="unitPrice">سعر الوحدة</label>
                                        <input type="number" id="unitPrice" name="unitPrice" min="0" step="0.01" required>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeAddItemModal()">إلغاء</button>
                                        <button type="submit" name="add_warehouse_item" class="btn btn-primary">إضافة القلم</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Withdraw Items Modal -->
                    <div class="modal" id="withdrawModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>تخريج قلم</h3>
                                <button class="modal-close" onclick="closeWithdrawModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form id="withdrawForm" action="main.php" method="post">
                                    <div class="form-group">
                                        <label for="withdrawItemId">اسم القلم</label>
                                        <select id="withdrawItemId" name="withdrawItemId" required>
                                            <option value="">اختر القلم</option>
                                            <?php
                                            foreach ($warehouse_items_options as $item) {
                                                echo '<option value="' . $item['id'] . '">' . htmlspecialchars($item['item_name']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="withdrawQuantity">الكمية المسحوبة</label>
                                        <input type="number" id="withdrawQuantity" name="withdrawQuantity" min="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="withdrawnTo">الجهة المستلمة</label>
                                        <input type="text" id="withdrawnTo" name="withdrawnTo" required>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeWithdrawModal()">إلغاء</button>
                                        <button type="submit" name="withdraw_item" class="btn btn-success">تخريج</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Add Invoice Modal -->
                    <div class="modal" id="addInvoiceModal">
                        <div class="modal-content large">
                            <div class="modal-header">
                                <h3>إضافة فاتورة جديدة</h3>
                                <button class="modal-close" onclick="closeAddInvoiceModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form id="addInvoiceForm" action="main.php" method="post">
                                    <div class="form-group">
                                        <label for="invoiceNumber">رقم الفاتورة</label>
                                        <input type="text" id="invoiceNumber" name="invoiceNumber" required>
                                    </div>
                                    <hr>
                                    <h4>أقلام الفاتورة</h4>
                                    <div class="invoice-items-header">
                                        <span>نوع القلم</span>
                                        <span>العدد</span>
                                        <span>القيمة</span>
                                        <span>ملاحظات</span>
                                        <span></span> <!-- Placeholder for delete button column -->
                                    </div>
                                    <div id="invoiceItemsContainer">
                                        <!-- Invoice items will be added here dynamically -->
                                        <div class="invoice-item">
                                            <input type="text" placeholder="نوع القلم" name="itemType[]" required>
                                            <input type="number" placeholder="العدد" name="itemCount[]" min="1" required>
                                            <input type="number" placeholder="القيمة" name="itemValue[]" min="0" step="0.01" required>
                                            <input type="text" placeholder="ملاحظات" name="itemNotes[]">
                                            <button type="button" class="btn-icon btn-delete" onclick="removeInvoiceItem(this)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="addInvoiceItem()">إضافة قلم آخر</button>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeAddInvoiceModal()">إلغاء</button>
                                        <button type="submit" name="add_invoice" class="btn btn-primary">إضافة الفاتورة</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Warehouse Item Modal -->
                    <div class="modal" id="editItemModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>تعديل قلم المستودع</h3>
                                <button class="modal-close" onclick="closeEditItemModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form id="editItemForm" action="pages/update_warehouse_item.php" method="post">
                                    <input type="hidden" id="editItemId" name="item_id">
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_item_name">اسم القلم (حالي)</label>
                                            <span id="display_item_name" class="readonly-field">[اسم القلم]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_item_name">اسم القلم</label>
                                            <input type="text" id="edit_item_name" name="item_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_quantity">الكمية الحالية</label>
                                            <span id="display_quantity" class="readonly-field">[الكمية]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_quantity">الكمية</label>
                                            <input type="number" id="edit_quantity" name="quantity" min="0" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_unit_price">سعر الوحدة الحالي</label>
                                            <span id="display_unit_price" class="readonly-field">[سعر الوحدة]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_unit_price">سعر الوحدة</label>
                                            <input type="number" id="edit_unit_price" name="unit_price" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeEditItemModal()">إلغاء</button>
                                        <button type="submit" class="btn btn-primary" id="updateItemBtn">تعديل القلم</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Warehouse Item Confirmation Modal -->
                    <div class="modal" id="deleteItemModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>تأكيد الحذف</h3>
                                <button class="modal-close" onclick="closeDeleteItemModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="delete-confirmation">
                                    <i class="fas fa-exclamation-triangle delete-icon"></i>
                                    <p>هل أنت متأكد من رغبتك في حذف</p>
                                    <p class="item-info" id="itemInfo">القلم رقم <span id="itemIdDisplay"></span></p>
                                    <p class="warning-text">هذا الإجراء لا يمكن التراجع عنه!</p>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="closeDeleteItemModal()">إلغاء</button>
                                    <button type="button" class="btn btn-danger" id="confirmDeleteItemBtn">حذف القلم</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Invoices Panel -->
                    <div class="side-panel" id="viewInvoicesPanel">
                        <div class="side-panel-header">
                            <h3>قائمة الفواتير</h3>
                            <button class="side-panel-close" onclick="closeViewInvoicesPanel()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="side-panel-body">
                            <div class="search-box side-panel-search">
                                <input type="text" id="invoiceSearchInput" placeholder="ابحث برقم الفاتورة أو اسم القلم..." onkeyup="searchInvoices()">
                                <i class="fas fa-search"></i>
                            </div>
                            <ul class="invoices-list" id="invoicesList">
                                <?php
                                if ($invoices_result->num_rows > 0) {
                                    while($row = $invoices_result->fetch_assoc()) {
                                        echo "<li onclick=\"showInvoiceDetails(" . $row['id'] . ")\">";
                                        echo "<span>رقم الفاتورة: " . htmlspecialchars($row["invoice_number"]) . "</span>";
                                        echo "<span>التاريخ: " . htmlspecialchars(date('Y-m-d', strtotime($row["invoice_date"]))) . "</span>";
                                        echo "<span>الإجمالي: " . htmlspecialchars($row["total_amount"]) . "</span>";
                                        echo "</li>";
                                    }
                                } else {
                                    echo "<li>لا توجد فواتير حالياً</li>";
                                }
                                ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Invoice Details Modal -->
                    <div class="modal" id="invoiceDetailsModal">
                        <div class="modal-content large">
                            <div class="modal-header">
                                <h3 id="invoiceDetailsTitle">تفاصيل الفاتورة</h3>
                                <button class="modal-close" onclick="closeInvoiceDetailsModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body" id="invoiceDetailsBody">
                                <!-- Details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-maintenance" class="page-wrapper" style="display: none;">
                    <div class="maintenance-header">
                        <button class="btn btn-primary" onclick="openMaintenanceModal()">
                            <i class="fas fa-plus"></i>
                            إضافة حدث صيانة
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="items-table" id="maintenanceTable">
                            <thead>
                                <tr>
                                    <th>كود الآلية</th>
                                    <th>مالك الآلية</th>
                                    <th>التصليح</th>
                                    <th>مستلزمات من المستودع</th>
                                    <th>مستلزمات مشتراة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="maintenanceTableBody">
                                <!-- Dynamic content will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Add Maintenance Modal -->
                    <div class="modal" id="addMaintenanceModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>إضافة حدث صيانة جديد</h3>
                                <button class="modal-close" onclick="closeMaintenanceModal()"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="modal-body">
                                <form id="addMaintenanceForm">
                                    <div class="form-group">
                                        <label for="modalVehiclePlate">اختر الآلية</label>
                                        <select id="modalVehiclePlate" name="vehicle_id" required>
                                            <option value="">-- اختر الآلية --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenanceDate">تاريخ الصيانة</label>
                                        <input type="date" id="maintenanceDate" name="maintenance_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenanceType">نوع الصيانة</label>
                                        <select id="maintenanceType" name="maintenance_type" required>
                                            <option value="">-- اختر نوع الصيانة --</option>
                                            <option value="تغيير زيت">تغيير زيت</option>
                                            <option value="إصلاح محرك">إصلاح محرك</option>
                                            <option value="فحص دوري">فحص دوري</option>
                                            <option value="تغيير إطارات">تغيير إطارات</option>
                                            <option value="أخرى">أخرى</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenanceDescription">تفاصيل الصيانة</label>
                                        <textarea id="maintenanceDescription" name="description" rows="3" placeholder="وصف تفصيلي للصيانة المؤداة" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="maintenanceCost">تكلفة الصيانة</label>
                                        <input type="number" id="maintenanceCost" name="cost" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeMaintenanceModal()">إلغاء</button>
                                        <button type="submit" class="btn btn-primary">إضافة الحدث</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Maintenance Modal -->
                    <div class="modal" id="editMaintenanceModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>تعديل حدث صيانة</h3>
                                <button class="modal-close" onclick="closeEditMaintenanceModal()"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="modal-body">
                                <form id="editMaintenanceForm">
                                    <input type="hidden" id="editMaintenanceId" name="maintenance_id">
                                    
                                    <div class="form-group">
                                        <label for="editVehiclePlate">اختر الآلية</label>
                                        <select id="editVehiclePlate" name="vehicle_id" required>
                                            <option value="">-- اختر الآلية --</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceDateCurrent">تاريخ الصيانة الحالي</label>
                                            <span id="editMaintenanceDateCurrent" class="readonly-field">[التاريخ الحالي]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceDate">تاريخ الصيانة</label>
                                            <input type="date" id="editMaintenanceDate" name="maintenance_date" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceTypeCurrent">نوع الصيانة الحالي</label>
                                            <span id="editMaintenanceTypeCurrent" class="readonly-field">[النوع الحالي]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceType">نوع الصيانة</label>
                                            <select id="editMaintenanceType" name="maintenance_type" required>
                                                <option value="">-- اختر نوع الصيانة --</option>
                                                <option value="تغيير زيت">تغيير زيت</option>
                                                <option value="إصلاح محرك">إصلاح محرك</option>
                                                <option value="فحص دوري">فحص دوري</option>
                                                <option value="تغيير إطارات">تغيير إطارات</option>
                                                <option value="أخرى">أخرى</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceDescriptionCurrent">تفاصيل الصيانة الحالية</label>
                                            <span id="editMaintenanceDescriptionCurrent" class="readonly-field">[التفاصيل الحالية]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceDescription">تفاصيل الصيانة</label>
                                            <textarea id="editMaintenanceDescription" name="description" rows="3" required></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceCostCurrent">التكلفة الحالية</label>
                                            <span id="editMaintenanceCostCurrent" class="readonly-field">[التكلفة الحالية]</span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="editMaintenanceCost">تكلفة الصيانة</label>
                                            <input type="number" id="editMaintenanceCost" name="cost" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeEditMaintenanceModal()">إلغاء</button>
                                        <button type="submit" class="btn btn-primary" id="updateMaintenanceBtn">تحديث الحدث</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-vehicles" class="page-wrapper" style="display: none;">
                    <!-- Filters Section -->
                    <div class="filters-section card">
                        <h3><i class="fas fa-search"></i> بحث في الآليات</h3>
                        <div class="form-group">
                            <label for="filterVehicleCode">فرز حسب الآلية</label>
                            <select id="filterVehicleCode">
                                <option value="all">جميع الآليات</option>
                                <?php
                                if ($vehicles_result && $vehicles_result->num_rows > 0) {
                                    $vehicles_result->data_seek(0); // Reset result pointer
                                    while($vehicle = $vehicles_result->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($vehicle['make']) . '">' . htmlspecialchars($vehicle['make']) . ' - ' . htmlspecialchars($vehicle['type']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="searchVehicleCode">البحث عن آلية (ادخل الكود)</label>
                            <input type="text" id="searchVehicleCode" placeholder="مثال: TR-01">
                        </div>
                        <div class="filter-actions">
                            <button class="btn btn-primary" onclick="applyVehicleFilter()">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <button class="btn btn-secondary" onclick="resetVehicleFilter()">
                                <i class="fas fa-undo"></i> إعادة تعيين
                            </button>
                        </div>
                    </div>

                    <!-- Add Vehicle Button -->
                    <div class="section-header" style="margin-bottom: 20px;">
                        <button class="btn btn-primary" onclick="openVehicleModal()">
                            <i class="fas fa-plus"></i>
                            إضافة آلية جديدة
                        </button>
                    </div>

                    <!-- Results Section -->
                    <div id="vehicles-results" class="report-results">
                        <h3>نتائج البحث</h3>
                        <div class="table-container">
                            <table class="items-table" id="vehiclesTable">
                                <thead>
                                    <tr>
                                        <th>كود الآلية</th>
                                        <th>النوع</th>
                                        <th>رقم اللوحة</th>
                                        <th>اسم السائق</th>
                                        <th>نوع الوقود</th>
                                        <th>المخصصات الشهرية</th>
                                        <th>رقم الشاسيه</th>
                                        <th>رقم المحرك</th>
                                        <th>اللون</th>
                                        <th>الملاحظات</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                            <tbody id="vehiclesTableBody">
                                <?php
                                if ($vehicles_result && $vehicles_result->num_rows > 0) {
                                    $vehicles_result->data_seek(0); // Reset result pointer
                                    while($vehicle = $vehicles_result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($vehicle['make'] ?? 'N/A') . "</td>"; // كود الآلية
                                        echo "<td>" . htmlspecialchars($vehicle['type'] ?? 'N/A') . "</td>"; // النوع
                                        echo "<td>" . htmlspecialchars($vehicle['plate_number'] ?? 'N/A') . "</td>"; // رقم اللوحة
                                        echo "<td>" . htmlspecialchars($vehicle['recipient'] ?? 'N/A') . "</td>"; // اسم السائق
                                        echo "<td>" . htmlspecialchars($vehicle['fuel_type'] ?? 'N/A') . "</td>"; // نوع الوقود
                                        echo "<td>" . number_format($vehicle['monthly_allocations'] ?? 0, 2) . "</td>"; // المخصصات الشهرية
                                        echo "<td>" . htmlspecialchars($vehicle['chassis_number'] ?? 'N/A') . "</td>"; // رقم الشاسيه
                                        echo "<td>" . htmlspecialchars($vehicle['engine_number'] ?? 'N/A') . "</td>"; // رقم المحرك
                                        echo "<td>" . htmlspecialchars($vehicle['color'] ?? 'N/A') . "</td>"; // اللون
                                        echo "<td>" . htmlspecialchars($vehicle['notes'] ?? 'N/A') . "</td>"; // الملاحظات
                                        echo '<td>
                                                <button class="btn-icon btn-edit" onclick="editVehicle(' . $vehicle['id'] . ')"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon btn-delete" onclick="openDeleteVehicleModal(' . $vehicle['id'] . ')"><i class="fas fa-trash"></i></button>
                                            </td>'; // الإجراءات
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='11'>لا توجد آليات مسجلة حالياً</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Add Vehicle Modal -->
                    <div class="modal" id="addVehicleModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>إضافة آلية جديدة</h3>
                                <button class="modal-close" onclick="closeVehicleModal()"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="modal-body">
<form id="addVehicleForm">
                                    <!-- Two-column layout for form fields -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleCode">كود الآلية</label>
                                            <input type="text" id="addVehicleCode" name="make" required>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleType">النوع</label>
                                            <input type="text" id="addVehicleType" name="type" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="addVehiclePlate">رقم اللوحة</label>
                                            <input type="text" id="addVehiclePlate" name="plate_number" required>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleModel">الموديل</label>
                                            <input type="text" id="addVehicleModel" name="model" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleFuelType">نوع الوقود</label>
                                            <select id="addVehicleFuelType" name="fuel_type">
                                                <option value="">-- اختر نوع الوقود --</option>
                                                <option value="diesel">مازوت</option>
                                                <option value="gasoline">بنزين</option>
                                            </select>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleMonthlyAllocations">المخصصات الشهرية</label>
                                            <input type="number" step="0.01" id="addVehicleMonthlyAllocations" name="monthly_allocations" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleYear">السنة</label>
                                            <input type="number" id="addVehicleYear" name="year" required>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleRecipient">المستلم</label>
                                            <input type="text" id="addVehicleRecipient" name="recipient">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleChassis">رقم الشاسيه</label>
                                            <input type="text" id="addVehicleChassis" name="chassis_number">
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="addVehicleEngine">رقم المحرك</label>
                                            <input type="text" id="addVehicleEngine" name="engine_number">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group"> <!-- Full width for color -->
                                            <label for="addVehicleColor">اللون</label>
                                            <input type="text" id="addVehicleColor" name="color">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="addVehicleNotes">الملاحظات</label>
                                            <textarea id="addVehicleNotes" name="notes" rows="3" placeholder="ملاحظات إضافية..."></textarea>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeVehicleModal()">إلغاء</button>
                                        <button type="submit" class="btn btn-primary" id="addVehicleSubmit">إضافة الآلية</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- editvehicles page -->
                <div class="modal" id="editVehicleModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>تعديل آلية موجودة</h3>
                                <button class="modal-close" onclick="closeeditVehicleModal()"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="modal-body">
                                <form id="editVehicleForm" action="main.php" method="post">
                                    <input type="hidden" id="editVehicleId" name="vehicle_id">

                                    <!-- Row 1: Make (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_make">كود الآلية</label>
                                            <span id="display_make" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_make">كود الآلية</label>
                                            <input type="text" id="edit_make" name="make">
                                        </div>
                                    </div>

                                    <!-- Row 2: Type (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_type">النوع</label>
                                            <span id="display_type" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_type">النوع</label>
                                            <input type="text" id="edit_type" name="type">
                                        </div>
                                    </div>

                                    <!-- Row 3: Plate Number (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_plate_number">رقم اللوحة</label>
                                            <span id="display_plate_number" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_plate_number">رقم اللوحة</label>
                                            <input type="text" id="edit_plate_number" name="plate_number">
                                        </div>
                                    </div>

                                    <!-- Row 4: Model (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_model">الموديل</label>
                                            <span id="display_model" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_model">الموديل</label>
                                            <input type="text" id="edit_model" name="model">
                                        </div>
                                    </div>

                                    <!-- Row 5: Fuel Type (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_fuel_type">نوع الوقود</label>
                                            <span id="display_fuel_type" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_fuel_type">نوع الوقود</label>
                                            <select id="edit_fuel_type" name="fuel_type">
                                                <option value="">-- اختر نوع الوقود --</option>
                                                <option value="diesel">مازوت</option>
                                                <option value="gasoline">بنزين</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Row 6: Monthly Allocations (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_monthly_allocations">المخصصات الشهرية</label>
                                            <span id="display_monthly_allocations" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_monthly_allocations">المخصصات الشهرية</label>
                                            <input type="number" step="0.01" id="edit_monthly_allocations" name="monthly_allocations" min="0">
                                        </div>
                                    </div>

                                    <!-- Row 7: Year (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_year">السنة</label>
                                            <span id="display_year" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_year">السنة</label>
                                            <input type="number" id="edit_year" name="year">
                                        </div>
                                    </div>

                                    <!-- Row 6: Recipient (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_recipient">المستلم</label>
                                            <span id="display_recipient" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_recipient">المستلم</label>
                                            <input type="text" id="edit_recipient" name="recipient">
                                        </div>
                                    </div>

                                    <!-- Row 7: Chassis Number (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_chassis_number">رقم الشاسيه</label>
                                            <span id="display_chassis_number" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_chassis_number">رقم الشاسيه</label>
                                            <input type="text" id="edit_chassis_number" name="chassis_number">
                                        </div>
                                    </div>

                                    <!-- Row 8: Engine Number (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_engine_number">رقم المحرك</label>
                                            <span id="display_engine_number" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_engine_number">رقم المحرك</label>
                                            <input type="text" id="edit_engine_number" name="engine_number">
                                        </div>
                                    </div>

                                    <!-- Row 10: Color (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group form-group-half">
                                            <label for="display_color">اللون</label>
                                            <span id="display_color" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group form-group-half">
                                            <label for="edit_color">اللون</label>
                                            <input type="text" id="edit_color" name="color">
                                        </div>
                                    </div>

                                    <!-- Row 11: Notes (Display/Edit) -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="display_notes">الملاحظات</label>
                                            <span id="display_notes" class="readonly-field"></span>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_notes">الملاحظات</label>
                                            <textarea id="edit_notes" name="notes" rows="3"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="closeeditVehicleModal()">إلغاء</button>
                                        <button type="submit" name="update_vehicle" class="btn btn-primary" id="updateVehicleBtn">تعديل</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Vehicle Confirmation Modal for main vehicles page -->
                <div class="modal" id="deleteVehicleModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>تأكيد الحذف</h3>
                            <button class="modal-close" onclick="closeDeleteVehicleModal()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="delete-confirmation">
                                <i class="fas fa-exclamation-triangle delete-icon"></i>
                                <p>هل أنت متأكد من رغبتك في حذف</p>
                                <p class="item-info" id="vehicleInfo">الآلية رقم: <span id="vehicleIdDisplay"></span></p>
                                <p class="warning-text">هذا الإجراء لا يمكن التراجع عنه!</p>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeDeleteVehicleModal()">إلغاء</button>
                                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">نعم، احذفها</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="page-gas-report" class="page-wrapper" style="display: none;">
                    <div class="report-container">
                        <h2>تقرير الكازية</h2>

                        <!-- Filters Section -->
                        <div class="filters-section card">
                            <div class="form-group">
                                <label for="filterGasVehicle">فرز حسب الآلية</label>
                                <select id="filterGasVehicle">
                                    <option value="all">جميع الآليات</option>
                                    <option value="TR-01">TR-01</option>
                                    <option value="GR-101">GR-101</option>
                                    <option value="FL-103">FL-103</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="gasDateRange">تحديد الفترة الزمنية</label>
                                <select id="gasDateRange">
                                    <option value="all">كل الأوقات</option>
                                    <option value="today">اليوم</option>
                                    <option value="this_week">هذا الأسبوع</option>
                                    <option value="this_month" selected>هذا الشهر</option>
                                    <option value="custom">فترة مخصصة</option>
                                </select>
                            </div>
                            <div class="form-group date-picker-group" id="gasCustomDateRange" style="display: none;">
                                <label for="gasStartDate">من تاريخ</label>
                                <input type="date" id="gasStartDate">
                                <label for="gasEndDate">إلى تاريخ</label>
                                <input type="date" id="gasEndDate">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="applyGasReportFilter()">
                                    <i class="fas fa-search"></i> عرض التقرير
                                </button>
                                <button class="btn btn-secondary" onclick="resetGasReportFilter()">
                                    <i class="fas fa-undo"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Report Results Section -->
                        <div id="gas-report-results" class="report-results">
                            <h3>تقرير التعبئة</h3>
                            <div class="table-container">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th onclick="sortGasTable(0)">تاريخ التعبئة <i class="fas fa-sort"></i></th>
                                            <th onclick="sortGasTable(1)">كود الآلية <i class="fas fa-sort"></i></th>
                                            <th>نوع الوقود</th>
                                            <th onclick="sortGasTable(2)">الكمية (لتر) <i class="fas fa-sort"></i></th>
                                            <th>السائق</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gasReportBody">
                                        <!-- Data will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-maintenance-report" class="page-wrapper" style="display: none;">
                    <div class="report-container">
                        <h2>تقرير الصيانة</h2>

                        <!-- Filters Section -->
                        <div class="filters-section card">
                            <div class="form-group">
                                <label for="maintenanceFilterVehicle">فرز حسب الآلية</label>
                                <select id="maintenanceFilterVehicle">
                                    <option value="all">جميع الآليات</option>
                                    <option value="TR-01">TR-01</option>
                                    <option value="GR-101">GR-101</option>
                                    <option value="FL-103">FL-103</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="maintenanceFilterMaintenanceType">فرز حسب نوع الصيانة</label>
                                <select id="maintenanceFilterMaintenanceType">
                                    <option value="all">جميع الأنواع</option>
                                    <option value="تغيير زيت">تغيير زيت</option>
                                    <option value="إصلاح محرك">إصلاح محرك</option>
                                    <option value="فحص دوري">فحص دوري</option>
                                    <option value="تغيير إطارات">تغيير إطارات</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="maintenanceDateRange">تحديد الفترة الزمنية</label>
                                <select id="maintenanceDateRange">
                                    <option value="all">كل الأوقات</option>
                                    <option value="today">اليوم</option>
                                    <option value="this_week">هذا الأسبوع</option>
                                    <option value="this_month" selected>هذا الشهر</option>
                                    <option value="custom">فترة مخصصة</option>
                                </select>
                            </div>
                            <div class="form-group date-picker-group" id="maintenanceCustomDateRange" style="display: none;">
                                <label for="maintenanceStartDate">من تاريخ</label>
                                <input type="date" id="maintenanceStartDate">
                                <label for="maintenanceEndDate">إلى تاريخ</label>
                                <input type="date" id="maintenanceEndDate">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="applyMaintenanceReportFilter()">
                                    <i class="fas fa-search"></i> عرض التقرير
                                </button>
                                <button class="btn btn-secondary" onclick="resetMaintenanceReportFilter()">
                                    <i class="fas fa-undo"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Report Results Section -->
                        <div id="report-results" class="report-results">
                            <h3>تقرير أحداث الصيانة</h3>
                            <div class="table-container">
                                <table class="items-table" id="maintenanceReportTable">
                                    <thead>
                                        <tr>
                                            <th onclick="sortTable('maintenanceReportTable', 0)">تاريخ الصيانة <i class="fas fa-sort"></i></th>
                                            <th onclick="sortTable('maintenanceReportTable', 1)">كود الآلية <i class="fas fa-sort"></i></th>
                                            <th>نوع الآلية</th>
                                            <th onclick="sortTable('maintenanceReportTable', 2)">نوع الصيانة <i class="fas fa-sort"></i></th>
                                            <th>تفاصيل الصيانة</th>
                                            <th>المواد المستخدمة</th>
                                        </tr>
                                    </thead>
                                    <tbody id="maintenanceReportBody">
                                        <!-- Data will be populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-report" class="page-wrapper" style="display: none;">
                    <div class="report-container">
                        <h2>تقرير الآليات</h2>

                        <!-- Filters Section -->
                        <div class="filters-section card">
                            <div class="form-group">
                                <label for="searchVehicleCode">البحث عن آلية (ادخل الكود)</label>
                                <input type="text" id="searchVehicleCode" placeholder="مثال: TR-01">
                            </div>
                            <div class="form-group">
                                <label for="warehouseDateRange">تحديد الفترة الزمنية</label>
                                <select id="warehouseDateRange">
                                    <option value="all">كل الأوقات</option>
                                    <option value="today">اليوم</option>
                                    <option value="this_week">هذا الأسبوع</option>
                                    <option value="this_month" selected>هذا الشهر</option>
                                    <option value="custom">فترة مخصصة</option>
                                </select>
                            </div>
                            <div class="form-group date-picker-group" id="warehouseCustomDateRange" style="display: none;">
                                <label for="warehouseStartDate">من تاريخ</label>
                                <input type="date" id="warehouseStartDate">
                                <label for="warehouseEndDate">إلى تاريخ</label>
                                <input type="date" id="warehouseEndDate">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="applyVehicleReportFilter()">
                                    <i class="fas fa-search"></i> عرض التقرير
                                </button>
                                <button class="btn btn-secondary" onclick="resetVehicleReportFilter()">
                                    <i class="fas fa-undo"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Report Results Section -->
                        <div id="report-results" class="report-results">
                            <!-- View for All Vehicles -->
                            <div id="all-vehicles-view">
                                <h3>التقرير الشامل للآليات</h3>
                                <div class="table-container">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>كود الآلية</th>
                                                <th>النوع</th>
                                                <th>المستلم</th>
                                                <th>رقم اللوحة</th>
                                                <th>نوع الوقود</th>
                                                <th>القسم</th>
                                                <th>آخر حدث صيانة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>TR-01</td>
                                                <td>شاحنة</td>
                                                <td>أحمد محمد</td>
                                                <td>258456</td>
                                                <td>مازوت</td>
                                                <td>النقل الثقيل</td>
                                                <td>تغيير زيت (2025-08-12)</td>
                                            </tr>
                                            <tr>
                                                <td>GR-101</td>
                                                <td>جرافة</td>
                                                <td>علي محمد</td>
                                                <td>54321</td>
                                                <td>ديزل</td>
                                                <td>قسم الهندسة</td>
                                                <td>إصلاح محرك (2025-08-10)</td>
                                            </tr>
                                             <tr>
                                                <td>FL-103</td>
                                                <td>رافعة شوكية</td>
                                                <td>محمود حسين</td>
                                                <td>12345</td>
                                                <td>ديزل</td>
                                                <td>المستودع</td>
                                                <td>فحص دوري (2025-08-11)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- View for a Specific Vehicle's Maintenance History -->
                            <div id="single-vehicle-view" style="display: none;">
                                <h3>سجل صيانة الآلية: <span id="selectedVehicleCode"></span></h3>
                                 <div class="table-container">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>تاريخ الصيانة</th>
                                                <th>تفاصيل التصليح</th>
                                                <th>مستلزمات من المستودع</th>
                                                <th>مستلزمات مشتراة</th>
                                            </tr>
                                        </thead>
                                        <tbody id="maintenanceHistoryBody">
                                            <!-- Maintenance records will be populated by JavaScript -->
                                            <tr>
                                                <td>2025-08-12</td>
                                                <td>تغيير زيت المحرك</td>
                                                <td>فلتر زيت (2), زيت محرك (5L)</td>
                                                <td>لا يوجد</td>
                                            </tr>
                                            <tr>
                                                <td>2025-07-20</td>
                                                <td>تغيير إطارات</td>
                                                <td>إطارات (4)</td>
                                                <td>لا يوجد</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-warehouse-report" class="page-wrapper" style="display: none;">
                    <div class="report-container">
                        <h2>تقرير المستودع</h2>

                        <!-- Filters Section -->
                        <div class="filters-section card">
                            <div class="form-group">
                                <label for="filterItem">فرز حسب القلم</label>
                                <select id="filterItem">
                                    <option value="all">جميع الأقلام</option>
                                    <option value="زيت محرك">زيت محرك</option>
                                    <option value="فلتر زيت">فلتر زيت</option>
                                    <option value="إطارات">إطارات</option>
                                    <option value="سائل تبريد">سائل تبريد</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filterQuantity">فرز حسب الكمية</label>
                                <input type="number" id="filterQuantity" placeholder="أدخل الكمية">
                            </div>
                            <div class="form-group">
                                <label for="dateRange">تحديد الفترة الزمنية</label>
                                <select id="dateRange">
                                    <option value="all">كل الأوقات</option>
                                    <option value="today">اليوم</option>
                                    <option value="this_week">هذا الأسبوع</option>
                                    <option value="this_month" selected>هذا الشهر</option>
                                    <option value="custom">فترة مخصصة</option>
                                </select>
                            </div>
                            <div class="form-group date-picker-group" id="customDateRange" style="display: none;">
                                <label for="startDate">من تاريخ</label>
                                <input type="date" id="startDate">
                                <label for="endDate">إلى تاريخ</label>
                                <input type="date" id="endDate">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="applyWarehouseReportFilter()">
                                    <i class="fas fa-search"></i> عرض التقرير
                                </button>
                                <button class="btn btn-secondary" onclick="resetWarehouseReportFilter()">
                                    <i class="fas fa-undo"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Report Results Section -->
                        <div id="report-results" class="report-results">
                            <h3>تقرير حركة المواد</h3>
                            <div class="table-container">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th onclick="sortTable(0)">تاريخ الخروج <i class="fas fa-sort"></i></th>
                                            <th onclick="sortTable(1)">اسم القلم <i class="fas fa-sort"></i></th>
                                            <th onclick="sortTable(2)">الكمية <i class="fas fa-sort"></i></th>
                                            <th>المستلم</th>
                                            <th>ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody id="warehouseReportBody">
                                        <!-- Data will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/vehicle-search.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/maintenance-report.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/warehouse-report.js"></script>
    <script src="assets/js/gas-report.js"></script>

    <script>
        // Enhanced Gas Station Reports Functions
        function generateDetailedReport() {
            const vehicleCode = document.getElementById('vehicleCodeFilter').value;
            const department = document.getElementById('departmentFilter').value;
            const startDate = document.getElementById('startDateFilter').value;
            const endDate = document.getElementById('endDateFilter').value;

            // Show loading state
            const tableBody = document.getElementById('detailedTableBody');
            tableBody.innerHTML = '<tr><td colspan="8">جاري تحميل البيانات...</td></tr>';

            // Simulate AJAX call to fetch filtered data (in real implementation, call PHP endpoint)
            setTimeout(() => {
                // Sample data - in real app, fetch from database via AJAX
                const sampleData = [
                    { date: '2025-09-10', code: 'TR-01', fuelType: 'مازوت', liters: 250, cost: 1,250,000, driver: 'أحمد محمد', department: 'النقل الثقيل', notes: 'تعبئة دورية' },
                    { date: '2025-09-10', code: 'GR-101', fuelType: 'مازوت', liters: 180, cost: 900,000, driver: 'علي محمد', department: 'قسم الهندسة', notes: '' },
                    { date: '2025-09-09', code: 'TR-01', fuelType: 'مازوت', liters: 200, cost: 1,000,000, driver: 'أحمد محمد', department: 'النقل الثقيل', notes: 'طوارئ' }
                ];

                // Filter data based on user input
                let filteredData = sampleData;

                if (vehicleCode) {
                    filteredData = filteredData.filter(row => row.code.includes(vehicleCode));
                }
                if (department !== 'all') {
                    filteredData = filteredData.filter(row => row.department === department);
                }
                if (startDate) {
                    filteredData = filteredData.filter(row => row.date >= startDate);
                }
                if (endDate) {
                    filteredData = filteredData.filter(row => row.date <= endDate);
                }

                if (filteredData.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8">لا توجد بيانات مطابقة للمعايير المحددة</td></tr>';
                    return;
                }

                // Populate table
                let tableHTML = '';
                filteredData.forEach(row => {
                    tableHTML += `
                        <tr>
                            <td>${row.date}</td>
                            <td>${row.code}</td>
                            <td>${row.fuelType}</td>
                            <td>${row.liters}</td>
                            <td>${row.cost}</td>
                            <td>${row.driver}</td>
                            <td>${row.department}</td>
                            <td>${row.notes}</td>
                        </tr>
                    `;
                });
                tableBody.innerHTML = tableHTML;

                // Show the table
                document.getElementById('detailedReportTable').style.display = 'block';

                // Update summary cards with filtered data
                updateSummaryCards(filteredData);
            }, 1000);
        }

        function updateSummaryCards(data) {
            const totalLiters = data.reduce((sum, row) => sum + row.liters, 0);
            const totalCost = data.reduce((sum, row) => sum + row.cost, 0);
            const avgEfficiency = data.length > 0 ? (totalLiters / data.length).toFixed(1) : 0;

            // Update existing cards or create new ones
            document.querySelector('.report-number').textContent = totalLiters;
            document.querySelectorAll('.report-number')[1].textContent = totalCost.toLocaleString();
            document.querySelectorAll('.report-number')[2].textContent = avgEfficiency + '%';
        }

        function printReport() {
            const printContent = document.getElementById('reports').innerHTML;
            const originalContent = document.body.innerHTML;
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload(); // Refresh to restore original content
        }

        function exportToPDF() {
            // Simple PDF export using jsPDF (include library if needed)
            const { jsPDF } = window.jspdf;
            if (typeof jsPDF === 'undefined') {
                alert('مكتبة jsPDF غير متوفرة. يرجى تضمينها لتصدير PDF.');
                return;
            }

            const doc = new jsPDF();
            doc.text('تقرير الكازية المفصل', 14, 15);
            doc.text(new Date().toLocaleDateString(), 14, 20);
            let yPosition = 30;
            const tableData = [
                ['التاريخ', 'الآلية', 'الوقود', 'الكمية', 'التكلفة'],
                // Add actual data here
            ];
            
            doc.autoTable({
                head: [tableData[0]],
                body: tableData.slice(1),
                startY: yPosition,
                theme: 'grid',
                styles: { fontSize: 8 },
                headStyles: { fillColor: [41, 128, 185] },
                margin: { right: 10, left: 10 }
            });

            doc.save('تقرير_الكازية.pdf');
        }

        function exportToExcel() {
            // Export to CSV as Excel alternative
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'التاريخ,كود الآلية,نوع الوقود,الكمية (لتر),التكلفة (ل.س),السائق,القسم,ملاحظات\n';
            
            // Add data rows
            const sampleData = [
                ['2025-09-10', 'TR-01', 'مازوت', '250', '1,250,000', 'أحمد محمد', 'النقل الثقيل', 'تعبئة دورية'],
                ['2025-09-10', 'GR-101', 'مازوت', '180', '900,000', 'علي محمد', 'قسم الهندسة', ''],
                ['2025-09-09', 'TR-01', 'مازوت', '200', '1,000,000', 'أحمد محمد', 'النقل الثقيل', 'طوارئ']
            ];
            
            csvContent += sampleData.map(row => row.join(',')).join('\n');
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'تقرير_الكازية.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Initialize date pickers with current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            document.getElementById('startDateFilter').valueAsDate = firstDay;
            document.getElementById('endDateFilter').valueAsDate = today;
        });
    </script>

    <style>
        /* Alkazi Search Styles */
        .search-results-container {
            min-height: 100px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .search-results-header {
            background: #007bff;
            color: white;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
        }

        .search-results-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-results-content {
            padding: 20px 0;
        }

        .vehicle-result-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .vehicle-header h5 {
            margin: 0;
            color: #007bff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vehicle-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .vehicle-status.active {
            background: #d4edda;
            color: #155724;
        }

        .vehicle-details-grid {
            display: grid;
            gap: 15px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 10px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }

        .detail-item strong {
            color: #495057;
            display: block;
            margin-bottom: 5px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .no-results h4 {
            color: #495057;
            margin: 10px 0;
        }

        .no-results p {
            margin: 5px 0;
            line-height: 1.6;
        }

        .loading, .error {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .detail-row {
                grid-template-columns: 1fr;
            }
            
            .search-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-input {
                max-width: none;
            }
        }
    </style>
    <script>
        // Vehicle filtering functions
        function applyVehicleFilter() {
            const vehicleFilter = document.getElementById('filterVehicleCode').value;
            const searchCode = document.getElementById('searchVehicleCode').value.toLowerCase();
            const table = document.getElementById('vehiclesTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let visible = true;
                const td = tr[i].getElementsByTagName('td');
                
                // Filter by vehicle selection
                if (vehicleFilter !== 'all' && td.length > 0 && td[0]) {
                    if (td[0].textContent.toLowerCase() !== vehicleFilter.toLowerCase()) {
                        visible = false;
                    }
                }
                
                // Filter by search code
                if (visible && searchCode && td.length > 0 && td[0]) {
                    if (td[0].textContent.toLowerCase().indexOf(searchCode) === -1) {
                        visible = false;
                    }
                }
                
                tr[i].style.display = visible ? '' : 'none';
            }
            
            // Update results header
            const resultsHeader = document.querySelector('#vehicles-results h3');
            if (resultsHeader) {
                if (vehicleFilter !== 'all' || searchCode) {
                    resultsHeader.textContent = 'نتائج البحث المفلترة';
                } else {
                    resultsHeader.textContent = 'جميع الآليات';
                }
            }
        }

        function resetVehicleFilter() {
            document.getElementById('filterVehicleCode').value = 'all';
            document.getElementById('searchVehicleCode').value = '';
            const table = document.getElementById('vehiclesTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                tr[i].style.display = '';
            }
            
            // Update results header
            const resultsHeader = document.querySelector('#vehicles-results h3');
            if (resultsHeader) {
                resultsHeader.textContent = 'جميع الآليات';
            }
        }

        // Vehicle search functionality (for backward compatibility)
        function searchVehicles() {
            const input = document.getElementById('vehicleSearch');
            if (!input) return; // If the old search input doesn't exist
            
            const filter = input.value.toLowerCase();
            const table = document.getElementById('vehiclesTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let visible = false;
                const td = tr[i].getElementsByTagName('td');
                
                // Check vehicle code (column 0 - make)
                if (td.length > 0 && td[0] && td[0].textContent.toLowerCase().indexOf(filter) > -1) {
                    visible = true;
                }
                
                // Check plate number (column 2)
                if (!visible && td.length > 2 && td[2] && td[2].textContent.toLowerCase().indexOf(filter) > -1) {
                    visible = true;
                }
                
                // Check driver name (column 3 - recipient)
                if (!visible && td.length > 3 && td[3] && td[3].textContent.toLowerCase().indexOf(filter) > -1) {
                    visible = true;
                }
                
                tr[i].style.display = visible ? '' : 'none';
            }
        }

        // Setup delete confirmation for vehicles section
        document.addEventListener('DOMContentLoaded', function() {
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    if (window.vehicleToDelete !== null) {
                        // Call the delete function from main.js
                        if (typeof window.deleteVehicle === 'function') {
                            window.deleteVehicle(window.vehicleToDelete);
                        }
                        if (typeof window.closeDeleteVehicleModal === 'function') {
                            window.closeDeleteVehicleModal();
                        }
                    }
                });
            }
        });
        
    var dailyDieselTotal = <?php echo $daily_diesel_total ?? 0; ?>;
    var dailyGasolineTotal = <?php echo $daily_gasoline_total ?? 0; ?>;
    
    document.getElementById("dailyDieselTotal").innerText = dailyDieselTotal;
    document.getElementById("dailyGasolineTotal").innerText = dailyGasolineTotal;

    </script>

</body>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- View for a Specific Vehicle's Maintenance History -->
                            <div id="single-vehicle-view" style="display: none;">
                                <h3>سجل صيانة الآلية: <span id="selectedVehicleCode"></span></h3>
                                 <div class="table-container">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>تاريخ الصيانة</th>
                                                <th>تفاصيل التصليح</th>
                                                <th>مستلزمات من المستودع</th>
                                                <th>مستلزمات مشتراة</th>
                                            </tr>
                                        </thead>
                                        <tbody id="maintenanceHistoryBody">
                                            <!-- Maintenance records will be populated by JavaScript -->
                                            <tr>
                                                <td>2025-08-12</td>
                                                <td>تغيير زيت المحرك</td>
                                                <td>فلتر زيت (2), زيت محرك (5L)</td>
                                                <td>لا يوجد</td>
                                            </tr>
                                            <tr>
                                                <td>2025-07-20</td>
                                                <td>تغيير إطارات</td>
                                                <td>إطارات (4)</td>
                                                <td>لا يوجد</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="page-warehouse-report" class="page-wrapper" style="display: none;">
                    <div class="report-container">
                        <h2>تقرير المستودع</h2>

                        <!-- Filters Section -->
                        <div class="filters-section card">
                            <div class="form-group">
                                <label for="filterItem">فرز حسب القلم</label>
                                <select id="filterItem">
                                    <option value="all">جميع الأقلام</option>
                                    <option value="زيت محرك">زيت محرك</option>
                                    <option value="فلتر زيت">فلتر زيت</option>
                                    <option value="إطارات">إطارات</option>
                                    <option value="سائل تبريد">سائل تبريد</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filterQuantity">فرز حسب الكمية</label>
                                <input type="number" id="filterQuantity" placeholder="أدخل الكمية">
                            </div>
                            <div class="form-group">
                                <label for="dateRange">تحديد الفترة الزمنية</label>
                                <select id="dateRange">
                                    <option value="all">كل الأوقات</option>
                                    <option value="today">اليوم</option>
                                    <option value="this_week">هذا الأسبوع</option>
                                    <option value="this_month" selected>هذا الشهر</option>
                                    <option value="custom">فترة مخصصة</option>
                                </select>
                            </div>
                            <div class="form-group date-picker-group" id="customDateRange" style="display: none;">
                                <label for="startDate">من تاريخ</label>
                                <input type="date" id="startDate">
                                <label for="endDate">إلى تاريخ</label>
                                <input type="date" id="endDate">
                            </div>
                            <div class="filter-actions">
                                <button class="btn btn-primary" onclick="applyWarehouseReportFilter()">
                                    <i class="fas fa-search"></i> عرض التقرير
                                </button>
                                <button class="btn btn-secondary" onclick="resetWarehouseReportFilter()">
                                    <i class="fas fa-undo"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <!-- Report Results Section -->
                        <div id="report-results" class="report-results">
                            <h3>تقرير حركة المواد</h3>
                            <div class="table-container">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th onclick="sortTable(0)">تاريخ الخروج <i class="fas fa-sort"></i></th>
                                            <th onclick="sortTable(1)">اسم القلم <i class="fas fa-sort"></i></th>
                                            <th onclick="sortTable(2)">الكمية <i class="fas fa-sort"></i></th>
                                            <th>المستلم</th>
                                            <th>ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody id="warehouseReportBody">
                                        <!-- Data will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/maintenance-report.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/warehouse-report.js"></script>
    <script src="assets/js/gas-report.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var dailyDieselTotal = <?php echo $daily_diesel_total ?? 0; ?>;
        var dailyGasolineTotal = <?php echo $daily_gasoline_total ?? 0; ?>;

        document.getElementById("dailyDieselTotal").innerText = dailyDieselTotal;
        document.getElementById("dailyGasolineTotal").innerText = dailyGasolineTotal;
    });
</script>

</body>
</html>
