<?php
session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'غير محدد';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'غير محدد';

// Mock data for demonstration (until database is added)
$tanks = [
    [
        'id' => 1,
        'tank_name' => 'خزان المازوت الرئيسي',
        'tank_type' => 'ديزل',
        'current_level' => 4520.75,
        'total_capacity' => 10000.00
    ],
    [
        'id' => 2,
        'tank_name' => 'خزان البنزين العادي',
        'tank_type' => 'بنزين',
        'current_level' => 2780.30,
        'total_capacity' => 8000.00
    ],
    [
        'id' => 3,
        'tank_name' => 'خزان السولار الاحتياطي',
        'tank_type' => 'سولار',
        'current_level' => 6230.00,
        'total_capacity' => 12000.00
    ]
];

// Mock recent transactions data for each tank
$recent_transactions = [
    1 => [
        [
            'transaction_type_ar' => 'تعبئة',
            'quantity' => 2000.00,
            'running_balance' => 6520.75,
            'supplier' => 'شركة الوقود الوطنية',
            'invoice_number' => 'INV-2025-010',
            'notes' => 'تعبئة يومية روتينية',
            'transaction_date' => '2025-09-10 14:30:00'
        ],
        [
            'transaction_type_ar' => 'جرد',
            'quantity' => 0.00,
            'running_balance' => 4520.75,
            'supplier' => '',
            'invoice_number' => '',
            'notes' => 'جرد يومي - الفرق: 1.25 لتر',
            'transaction_date' => '2025-09-10 09:15:00'
        ],
        [
            'transaction_type_ar' => 'وارد',
            'quantity' => 1500.00,
            'running_balance' => 4520.75,
            'supplier' => 'مورد الوقود المحلي',
            'invoice_number' => 'INV-2025-009',
            'notes' => 'تعبئة طارئة',
            'transaction_date' => '2025-09-09 18:45:00'
        ],
        [
            'transaction_type_ar' => 'جرد',
            'quantity' => 0.00,
            'running_balance' => 3020.75,
            'supplier' => '',
            'invoice_number' => '',
            'notes' => 'جرد نهاية اليوم - الفرق: 0.80 لتر',
            'transaction_date' => '2025-09-09 20:00:00'
        ],
        [
            'transaction_type_ar' => 'تعبئة',
            'quantity' => 3000.00,
            'running_balance' => 3020.75,
            'supplier' => 'شركة الوقود الوطنية',
            'invoice_number' => 'INV-2025-008',
            'notes' => 'تعبئة أسبوعية',
            'transaction_date' => '2025-09-08 11:20:00'
        ]
    ],
    2 => [
        [
            'transaction_type_ar' => 'وارد',
            'quantity' => 1000.00,
            'running_balance' => 3780.30,
            'supplier' => 'مورد البنزين المحلي',
            'invoice_number' => 'INV-2025-011',
            'notes' => 'تعبئة صغيرة',
            'transaction_date' => '2025-09-10 16:10:00'
        ],
        [
            'transaction_type_ar' => 'جرد',
            'quantity' => 0.00,
            'running_balance' => 2780.30,
            'supplier' => '',
            'invoice_number' => '',
            'notes' => 'جرد يومي - الفرق: 0.50 لتر',
            'transaction_date' => '2025-09-10 08:45:00'
        ],
        [
            'transaction_type_ar' => 'تعبئة',
            'quantity' => 1200.00,
            'running_balance' => 2780.30,
            'supplier' => 'شركة البترول الوطنية',
            'invoice_number' => 'INV-2025-007',
            'notes' => '',
            'transaction_date' => '2025-09-09 13:30:00'
        ],
        [
            'transaction_type_ar' => 'جرد',
            'quantity' => 0.00,
            'running_balance' => 1580.30,
            'supplier' => '',
            'invoice_number' => '',
            'notes' => 'جرد الصباح - الفرق: 2.10 لتر',
            'transaction_date' => '2025-09-09 07:20:00'
        ],
        [
            'transaction_type_ar' => 'وارد',
            'quantity' => 2500.00,
            'running_balance' => 1580.30,
            'supplier' => 'مورد البنزين المحلي',
            'invoice_number' => 'INV-2025-006',
            'notes' => 'تعبئة نصف الخزان',
            'transaction_date' => '2025-09-07 15:00:00'
        ]
    ],
    3 => [
        [
            'transaction_type_ar' => 'تعبئة',
            'quantity' => 4000.00,
            'running_balance' => 10230.00,
            'supplier' => 'شركة الوقود الوطنية',
            'invoice_number' => 'INV-2025-012',
            'notes' => 'تعبئة كاملة للخزان الاحتياطي',
            'transaction_date' => '2025-09-10 12:00:00'
        ],
        [
            'transaction_type_ar' => 'جرد',
            'quantity' => 0.00,
            'running_balance' => 6230.00,
            'supplier' => '',
            'invoice_number' => '',
            'notes' => 'جرد يومي - الفرق: 0.00 لتر',
            'transaction_date' => '2025-09-10 10:30:00'
        ],
        [
            'transaction_type_ar' => 'وارد',
            'quantity' => 2000.00,
            'running_balance' => 6230.00,
            'supplier' => 'مورد السولار',
            'invoice_number' => 'INV-2025-005',
            'notes' => 'تعبئة احتياطية',
            'transaction_date' => '2025-09-08 09:15:00'
        ]
    ]
];

// Handle form submission for daily inventory (will work after database is added)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_inventory') {
    $tank_id = (int)$_POST['tank_id'];
    $inventory_date = $_POST['inventory_date'];
    $digital_reading = (float)$_POST['digital_reading'];
    $secret_reading = (float)$_POST['secret_reading'];
    $notes = trim($_POST['notes']) ?? '';
    
    // For now, just show success message with details
    $success_message = "تم تسجيل الجرد اليومي بنجاح (وضع تجريبي)<br>خزان: " . array_column($tanks, null, 'id')[$tank_id]['tank_name'] . "<br>التاريخ: $inventory_date<br>العداد الرقمي: $digital_reading لتر<br>العداد السري: $secret_reading لتر<br>الفرق: " . ($digital_reading - $secret_reading) . " لتر";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الجرد اليومي للخزانات</title>
    <link rel="stylesheet" href="../assets/css/gas-station.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .inventory-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .tank-fillings-log { margin-top: 20px; }
        .filling-entry { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee; }
        .filling-entry:last-child { border-bottom: none; }
        .filling-date { font-size: 0.9em; color: #666; }
        .success-msg, .error-msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success-msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-msg { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .log-header { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>الكازية - الجرد اليومي للخزانات</h1>
            <nav>
                <a href="../main.php">الرئيسية</a>
                <a href="../logout.php">تسجيل الخروج</a>
            </nav>
        </header>

        <main>
            <?php if (isset($success_message)): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Daily Inventory Form -->
            <section class="inventory-section">
                <h2>تسجيل الجرد اليومي</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="record_inventory">
                    
                    <div class="form-group">
                        <label for="tank_id">اختر الخزان:</label>
                        <select name="tank_id" id="tank_id" required>
                            <option value="">-- اختر خزان --</option>
                            <?php foreach ($tanks as $tank): ?>
                                <option value="<?php echo $tank['id']; ?>">
                                    <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['tank_type'] . ' (الرصيد الحالي: ' . $tank['current_level'] . '/' . $tank['total_capacity'] . ' لتر)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="inventory_date">تاريخ الجرد:</label>
                        <input type="date" name="inventory_date" id="inventory_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="digital_reading">قراءة العداد الرقمي (لتر):</label>
                        <input type="number" name="digital_reading" id="digital_reading" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="secret_reading">قراءة العداد السري (لتر):</label>
                        <input type="number" name="secret_reading" id="secret_reading" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="notes">ملاحظات:</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="أي ملاحظات حول الجرد..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">تسجيل الجرد</button>
                </form>
            </section>

            <!-- Last Tank Fillings Log -->
            <section class="tank-fillings-log">
                <h2>سجل آخر التعبئات للخزانات</h2>
                <p>عرض آخر 5 تعبئات/جرد لكل خزان</p>
                
                <?php foreach ($tanks as $tank): ?>
                    <?php if (!empty($recent_transactions[$tank['id']])): ?>
                        <div class="inventory-section">
                            <div class="log-header">
                                <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['tank_type']); ?>
                                <span style="float: left; color: #666; font-size: 0.9em;">
                                    الرصيد الحالي: <?php echo $tank['current_level']; ?> / <?php echo $tank['total_capacity']; ?> لتر
                                </span>
                            </div>
                            
                            <?php foreach ($recent_transactions[$tank['id']] as $transaction): ?>
                                <div class="filling-entry">
                                    <div>
                                        <strong><?php echo htmlspecialchars($transaction['transaction_type_ar'] ?? 'غير محدد'); ?></strong>
                                        <?php if ($transaction['quantity'] > 0): ?>
                                            <span style="color: green;">+<?php echo number_format($transaction['quantity'], 2); ?> لتر</span>
                                        <?php else: ?>
                                            <span style="color: red;"><?php echo number_format($transaction['quantity'], 2); ?> لتر</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="filling-date">
                                            <?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?>
                                            <?php if ($transaction['supplier']): ?>
                                                - <?php echo htmlspecialchars($transaction['supplier']); ?>
                                                <?php if ($transaction['invoice_number']): ?>
                                                    (<?php echo htmlspecialchars($transaction['invoice_number']); ?>)
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($transaction['notes']): ?>
                                            <br><small><?php echo htmlspecialchars($transaction['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: left;">
                                        <strong>الرصيد بعد العملية:</strong><br>
                                        <?php echo number_format($transaction['running_balance'], 2); ?> لتر
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty($recent_transactions)): ?>
                    <p>لا توجد تعبئات مسجلة حتى الآن.</p>
                <?php endif; ?>
            </section>
        </main>

        <footer>
            <p>&copy; 2025 نظام إدارة الكازية</p>
        </footer>
    </div>

    <script>
        // Auto-calculate difference between readings
        document.getElementById('digital_reading').addEventListener('input', calculateDifference);
        document.getElementById('secret_reading').addEventListener('input', calculateDifference);

        function calculateDifference() {
            const digital = parseFloat(document.getElementById('digital_reading').value) || 0;
            const secret = parseFloat(document.getElementById('secret_reading').value) || 0;
            const difference = digital - secret;
            // Could add a display for difference if needed
            console.log('الفرق:', difference);
        }

        // Set today's date as default
        document.getElementById('inventory_date').valueAsDate = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
