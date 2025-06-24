<?php
session_start();
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ตรวจสอบผู้ใช้จากฐานข้อมูล
    $sql = "SELECT user_id, department, level, password FROM prod_user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $department, $level, $db_password);
    $stmt->fetch();

    // ตรวจสอบรหัสผ่านแบบตรงๆ (หากรหัสผ่านในฐานข้อมูลไม่ถูกเข้ารหัส)
    if ($stmt->num_rows > 0 && $password == $db_password) {
        // เข้าสู่ระบบสำเร็จ
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['department'] = $department;
        $_SESSION['level'] = $level;

        // รีไดเร็กต์ไปที่หน้า dashboard หรือหน้าอื่นๆ
        header("Location: landing_page.php");
        exit();
    } else {
        // ถ้าผิดพลาดให้แสดงข้อความ
        $error = "Username or password is incorrect.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .login-form {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            display: block;
            margin: 0 auto 20px;
            max-width: 90px; /* ปรับขนาดโลโก้ให้พอดี */
        }
    </style>
</head>
<body>

<div class="login-form">
    <!-- เพิ่มโลโก้ในส่วนนี้ -->
    <img src="logo/SK-Logo.png" alt="Logo" class="logo">
    
    <h2 class="text-center">LOGIN</h2>
    <?php if (isset($error)) { echo '<div class="alert alert-danger" role="alert">'.$error.'</div>'; } ?>
    <form action="index.php" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
