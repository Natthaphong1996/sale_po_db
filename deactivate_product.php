<?php
// ภาษา: PHP
// ชื่อไฟล์: deactivate_product.php
// คอมเมนต์: สคริปต์สำหรับเปลี่ยนสถานะสินค้าจาก Active -> Inactive ด้วย Prepared Statement

date_default_timezone_set('Asia/Bangkok');
session_start();

// เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/config_db.php';

// รับ prod_id ผ่าน GET
$prod_id = isset($_GET['prod_id']) ? (int)$_GET['prod_id'] : 0;

if ($prod_id <= 0) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: products_list.php');
    exit;
}

// เตรียมคำสั่งอัปเดตสถานะ
$sql = "UPDATE product_list SET status = 'inactive' WHERE prod_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $prod_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Deactivate product successfully.';
    } else {
        $_SESSION['error'] = 'Failed to deactivate product: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Failed to prepare statement.';
}

$conn->close();

// กลับไปยังหน้ารายการสินค้า
header('Location: products_list.php');
exit;
