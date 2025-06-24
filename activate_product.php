<?php
// ภาษา: PHP
// ชื่อไฟล์: activate_product.php
// คอมเมนต์: สคริปต์สำหรับเปลี่ยนสถานะสินค้าจาก Inactive -> Active ด้วย Prepared Statement

date_default_timezone_set('Asia/Bangkok');
session_start();

// เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/config_db.php';

// รับ prod_id ผ่าน GET และแปลงเป็น integer
$prod_id = isset($_GET['prod_id']) ? (int)$_GET['prod_id'] : 0;

if ($prod_id <= 0) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: products_list.php');
    exit;
}

// เตรียมคำสั่งอัปเดตสถานะ
$sql = "UPDATE product_list SET status = 'active' WHERE prod_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $prod_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Activate product successfully.';
    } else {
        $_SESSION['error'] = 'Failed to activate product: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Failed to prepare statement.';
}

$conn->close();

// รีไดเรกต์กลับหน้ารายการสินค้า
header('Location: products_list.php');
exit;
