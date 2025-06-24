<?php
// Language: PHP
// File: update_product.php (Corrected to handle old products)

// 1. SETUP
// =================================
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';

$user_id = $_SESSION['user_id'] ?? 0; // สมมติว่า user_id ถูกเก็บใน session

// 2. INPUT VALIDATION
// =================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products_list.php');
    exit;
}

// รับข้อมูลจากฟอร์ม
$prod_id = (int)$_POST['prod_id'];
$prod_code = trim($_POST['prod_code']);
$customer_code = trim($_POST['customer_code']);
$type_id = (int)$_POST['type_id'];
$prod_desc = trim($_POST['prod_desc']);
$thickness = (int)$_POST['thickness'];
$width = (int)$_POST['width'];
$length = (int)$_POST['length'];
$price = (float)$_POST['price'];
$status = $_POST['status'];

if (empty($prod_id) || empty($prod_code) || empty($type_id)) { // Price can be 0, so don't check empty
    $_SESSION['flash_error'] = 'Product ID, Code, and Type are required.';
    header('Location: products_list.php');
    exit;
}

// 3. DATABASE TRANSACTION
// =================================

// === STEP 0: ดึงข้อมูลปัจจุบันจากฐานข้อมูลเพื่อเปรียบเทียบ ===
$current_data_sql = "SELECT p.price, pp.price_id FROM product_list p 
                     LEFT JOIN product_price pp ON p.prod_id = pp.prod_id
                     WHERE p.prod_id = ? LIMIT 1";
$stmt_current = $conn->prepare($current_data_sql);
$stmt_current->bind_param('i', $prod_id);
$stmt_current->execute();
$result_current = $stmt_current->get_result()->fetch_assoc();
$current_price = $result_current['price'] ?? 0.0;
$price_id = $result_current['price_id'] ?? null; // ถ้าไม่เจอจะเป็น null
$stmt_current->close();

// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // === STEP 1: Update product_list ===
    $sql_list = "UPDATE product_list SET 
                    prod_code = ?, customer_code = ?, type_id = ?, prod_desc = ?, 
                    thickness = ?, width = ?, length = ?, price = ?, status = ?,
                    updated_at = NOW()
                 WHERE prod_id = ?";
    
    if ($price != $current_price) {
        $sql_list = str_replace("updated_at = NOW()", "updated_at = NOW(), date_price = NOW()", $sql_list);
    }
                 
    $stmt_list = $conn->prepare($sql_list);
    $stmt_list->bind_param('ssisiiidsi', $prod_code, $customer_code, $type_id, $prod_desc, $thickness, $width, $length, $price, $status, $prod_id);
    $stmt_list->execute();

    // === STEP 2: ตรวจสอบและจัดการการเปลี่ยนแปลงราคา (ส่วนที่แก้ไข) ===
    if ($price != $current_price) {
        
        if ($price_id) {
            // ---- กรณีที่เจอ price_id: สินค้าปกติ, ทำการ UPDATE ----
            // a) Update product_price table
            $sql_price = "UPDATE product_price SET price_value = ?, date_update = NOW() WHERE price_id = ?";
            $stmt_price = $conn->prepare($sql_price);
            $stmt_price->bind_param('di', $price, $price_id);
            $stmt_price->execute();
            if (isset($stmt_price)) $stmt_price->close();

            // b) Insert into product_price_history table
            $sql_history = "INSERT INTO product_price_history (price_id, change_from, change_to, change_date, user_id) 
                            VALUES (?, ?, ?, NOW(), ?)";
            $stmt_history = $conn->prepare($sql_history);
            $stmt_history->bind_param('idds', $price_id, $current_price, $price, $user_id);
            $stmt_history->execute();
            if (isset($stmt_history)) $stmt_history->close();

        } else {
            // ---- กรณีที่ไม่เจอ price_id: สินค้าเก่า, ทำการ INSERT ข้อมูลใหม่เข้าไป ----
            // a) Insert into product_price table
            $sql_price_insert = "INSERT INTO product_price (prod_id, price_value, date_update) VALUES (?, ?, NOW())";
            $stmt_price_insert = $conn->prepare($sql_price_insert);
            $stmt_price_insert->bind_param('id', $prod_id, $price);
            $stmt_price_insert->execute();
            
            // ดึง price_id ใหม่ที่เพิ่งสร้าง
            $new_price_id = $conn->insert_id;
            if (isset($stmt_price_insert)) $stmt_price_insert->close();

            // b) Insert into product_price_history table using the new_price_id
            $sql_history_insert = "INSERT INTO product_price_history (price_id, change_from, change_to, change_date, user_id) 
                                   VALUES (?, ?, ?, NOW(), ?)";
            $stmt_history_insert = $conn->prepare($sql_history_insert);
            $stmt_history_insert->bind_param('idds', $new_price_id, $current_price, $price, $user_id);
            $stmt_history_insert->execute();
            if (isset($stmt_history_insert)) $stmt_history_insert->close();
        }
    }
    
    // === COMMIT TRANSACTION ===
    $conn->commit();
    $_SESSION['flash_success'] = 'Product updated successfully.';

} catch (Exception $e) {
    // === ROLLBACK TRANSACTION ===
    $conn->rollback();
    $_SESSION['flash_error'] = 'Failed to update product: ' . $e->getMessage();

} finally {
    if (isset($stmt_list)) $stmt_list->close();
    $conn->close();
}

// 4. REDIRECT
// =================================
header('Location: products_list.php');
exit;
?>