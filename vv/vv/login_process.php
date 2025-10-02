<?php
session_start();
include 'db_connect.php';

if (isset($_POST['username']) && isset($_POST['password'])) {
    function validate($data){
       $data = trim($data);
       $data = stripslashes($data);
       $data = htmlspecialchars($data);
       return $data;
    }

    $username = validate($_POST['username']);
    $password = validate($_POST['password']);

    if (empty($username)) {
        header("Location: login.php?error=اسم المستخدم مطلوب");
        exit();
    } else if (empty($password)) {
        header("Location: login.php?error=كلمة المرور مطلوبة");
        exit();
    } else {
        $sql = "SELECT * FROM users WHERE username='$username'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($password, $row['password'])) {
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['id'] = $row['id'];
                header("Location: main.php");
                exit();
            } else {
                header("Location: login.php?error=اسم المستخدم أو كلمة المرور غير صحيحة");
                exit();
            }
        } else {
            header("Location: login.php?error=اسم المستخدم أو كلمة المرور غير صحيحة");
            exit();
        }
    }
} else {
    header("Location: login.php");
    exit();
}
?>
