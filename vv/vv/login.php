<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <form action="login_process.php" method="post">
            <h2>تسجيل الدخول</h2>
            <?php if(isset($_GET['error'])) { ?>
                <p class="error"><?php echo $_GET['error']; ?></p>
            <?php } ?>
            <label for="username">اسم المستخدم</label>
            <input type="text" name="username" id="username" required>
            <label for="password">كلمة المرور</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">تسجيل الدخول</button>
        </form>
    </div>
</body>
</html>
