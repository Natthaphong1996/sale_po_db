<?php
// Language: PHP
// File: add_product.php

// 1. SETUP
// =================================
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';

// 2. INPUT VALIDATION
// =================================
// ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products_list.php');
    exit;
}

// รับข้อมูลจากฟอร์ม
$prod_code = trim($_POST['prod_code']);
$customer_code = trim($_POST['customer_code']);
$type_id = (int)$_POST['type_id'];
$prod_desc = trim($_POST['prod_desc']);
$thickness = (int)$_POST['thickness'];
$width = (int)$_POST['width'];
$length = (int)$_POST['length'];
$price = (float)$_POST['price'];

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($prod_code) || empty($type_id) || empty($price)) {
    $_SESSION['flash_error'] = 'Product Code, Type, and Price are required.';
    header('Location: products_list.php');
    exit;
}

// 3. DATABASE TRANSACTION
// =================================
// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // === STEP 1: Insert into product_list ===
    $sql_list = "INSERT INTO product_list (prod_code, customer_code, type_id, prod_desc, thickness, width, length, price, date_price, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt_list = $conn->prepare($sql_list);
    $stmt_list->bind_param('ssisiiid', $prod_code, $customer_code, $type_id, $prod_desc, $thickness, $width, $length, $price);
    $stmt_list->execute();

    // ดึง prod_id ที่เพิ่งสร้างขึ้นมา เพื่อใช้ในตารางถัดไป
    $new_prod_id = $conn->insert_id;
    if ($new_prod_id == 0) {
        throw new Exception("Failed to create new product in product_list.");
    }

    // === STEP 2: Insert into product_price ===
    $sql_price = "INSERT INTO product_price (prod_id, price_value, date_update) VALUES (?, ?, NOW())";
    $stmt_price = $conn->prepare($sql_price);
    $stmt_price->bind_param('id', $new_prod_id, $price);
    $stmt_price->execute();

    // === COMMIT TRANSACTION ===
    // หากทุกอย่างสำเร็จ ให้ยืนยันการเปลี่ยนแปลงทั้งหมด
    $conn->commit();
    $_SESSION['flash_success'] = 'Product added successfully.';

} catch (Exception $e) {
    // === ROLLBACK TRANSACTION ===
    // หากมีข้อผิดพลาดเกิดขึ้น ให้ยกเลิกการเปลี่ยนแปลงทั้งหมดที่ทำมา
    $conn->rollback();
    $_SESSION['flash_error'] = 'Failed to add product: ' . $e->getMessage();

} finally {
    // ปิด statements ที่เปิดไว้
    if (isset($stmt_list)) $stmt_list->close();
    if (isset($stmt_price)) $stmt_price->close();
    $conn->close();
}

// 4. REDIRECT
// =================================
header('Location: products_list.php');
exit;
?>