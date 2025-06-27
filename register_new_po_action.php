<?php
// Language: PHP
// File: register_new_po_action.php

date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';

// ตรวจสอบว่าผู้ใช้ Login อยู่หรือไม่
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_error'] = 'Please log in to perform this action.';
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];

// ตรวจสอบว่าเป็น POST request หรือไม่
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: register_new_po_view.php');
    exit;
}

// รับและตรวจสอบข้อมูลจากฟอร์ม
$po_no = trim($_POST['po_no'] ?? '');
$cus_id = trim($_POST['cus_id'] ?? '');
$po_date = trim($_POST['po_date'] ?? '');

if (empty($po_no) || empty($cus_id) || empty($po_date)) {
    $_SESSION['flash_error'] = 'PO No, Customer, and PO Date are required.';
    header('Location: register_new_po_view.php');
    exit;
}

// เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
$conn->begin_transaction();

try {
    // 1. ตรวจสอบว่า PO No. นี้ซ้ำหรือไม่
    $stmt_check = $conn->prepare("SELECT po_id FROM po_list WHERE po_no = ?");
    $stmt_check->bind_param("s", $po_no);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        throw new Exception("This Purchase Order Number (PO No.) already exists.");
    }
    $stmt_check->close();

    // 2. เพิ่มข้อมูล PO หลัก พร้อมกับ status
    // *** การเปลี่ยนแปลงอยู่ตรงนี้ ***
    // เพิ่มคอลัมน์ 'status' และค่า 'active' เข้าไปในคำสั่ง INSERT
    $stmt_po = $conn->prepare("INSERT INTO po_list (po_no, cus_id, po_date, status, created_by) VALUES (?, ?, ?, 'active', ?)");
    $stmt_po->bind_param("siss", $po_no, $cus_id, $po_date, $username);
    if (!$stmt_po->execute()) {
        throw new Exception("Failed to create new PO header: " . $stmt_po->error);
    }
    $po_id = $stmt_po->insert_id; // ดึง ID ของ PO ที่เพิ่งสร้าง
    $stmt_po->close();

    // 3. เตรียม statement สำหรับเพิ่มรายการสินค้า
    $stmt_item = $conn->prepare("INSERT INTO po_items (po_id, prod_id, qty, price, delivery_date) VALUES (?, ?, ?, ?, ?)");
    
    // วนลูปเพื่อเพิ่มรายการสินค้าแต่ละรายการ
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        foreach ($_POST['products'] as $product) {
            $prod_id = $product['prod_id'] ?? null;
            $qty = empty($product['qty']) ? 0 : filter_var($product['qty'], FILTER_VALIDATE_INT);
            $price = empty($product['price']) ? 0.0 : filter_var($product['price'], FILTER_VALIDATE_FLOAT);
            $delivery_date = empty($product['delivery_date']) ? null : $product['delivery_date'];

            // เพิ่มเฉพาะรายการที่มีข้อมูลครบถ้วน
            if ($prod_id && $qty > 0) {
                $stmt_item->bind_param("iiids", $po_id, $prod_id, $qty, $price, $delivery_date);
                if (!$stmt_item->execute()) {
                    throw new Exception("Failed to add item (Product ID: {$prod_id}): " . $stmt_item->error);
                }
            }
        }
    }
    $stmt_item->close();
    
    // ถ้าทุกอย่างสำเร็จ ให้ Commit transaction
    $conn->commit();
    $_SESSION['flash_success'] = "Successfully created Purchase Order #{$po_no}.";
    header("Location: po_detail.php?po_no=" . urlencode($po_no));
    exit;

} catch (Exception $e) {
    // หากมีข้อผิดพลาด ให้ Rollback transaction
    $conn->rollback();
    $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    header('Location: register_new_po_view.php');
    exit;
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>