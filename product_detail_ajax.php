<?php
// Language: PHP
// File: product_detail_ajax.php (Final Version with prod_user.thainame)

// --- START: DEBUGGING CODE ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END: DEBUGGING CODE ---

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/config_db.php';

// โครงสร้างสำหรับตอบกลับ (เผื่อกรณี Error)
$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'data' => null
];

// ตรวจสอบ Input
if (!isset($_GET['prod_id']) || !filter_var($_GET['prod_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    $response['message'] = 'Invalid or missing Product ID.';
    echo json_encode($response);
    exit;
}

$prod_id = (int)$_GET['prod_id'];

try {
    // --- 1. Fetch Main Product Details (ส่วนนี้ไม่มีการเปลี่ยนแปลง) ---
    $dataSql = "
        SELECT 
            p.*, 
            t.type_name, 
            CONCAT(p.thickness, '×', p.width, '×', p.length) AS dimension
        FROM product_list p
        LEFT JOIN prod_type_list t ON p.type_id = t.type_id
        WHERE p.prod_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($dataSql);
    if ($stmt === false) {
        throw new Exception("Database prepare failed for product details. Error: " . $conn->error);
    }
    $stmt->bind_param('i', $prod_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_data = $result->fetch_assoc();
    $stmt->close();

    if (!$product_data) {
        http_response_code(404);
        $response['message'] = "Product with ID {$prod_id} not found.";
        echo json_encode($response);
        exit;
    }

    // --- 2. Fetch Price History (ส่วนที่แก้ไข) ---
    // เปลี่ยนจากการ JOIN ตาราง 'users' เป็น 'prod_user' ตามที่คุณต้องการ
    $historySql = "
        SELECT 
            h.change_from,
            h.change_to,
            h.change_date,
            h.user_id,
            u.thainame      -- ดึงชื่อผู้ใช้จากตาราง prod_user
        FROM 
            product_price_history h
        INNER JOIN 
            product_price pp ON h.price_id = pp.price_id
        LEFT JOIN
            prod_user u ON h.user_id = u.user_id -- << แก้ไขเป็น prod_user ตรงนี้
        WHERE 
            pp.prod_id = ?
        ORDER BY 
            h.change_date DESC
    ";
    $stmtHistory = $conn->prepare($historySql);
    if ($stmtHistory === false) {
        throw new Exception("Database prepare failed for history. Check your table/column names (prod_user). Error: " . $conn->error);
    }
    
    $stmtHistory->bind_param('i', $prod_id);
    $stmtHistory->execute();
    $historyResult = $stmtHistory->get_result();
    $price_history = $historyResult->fetch_all(MYSQLI_ASSOC);
    $stmtHistory->close();

    // --- 3. Combine Data & Prepare JSON Response ---
    $product_data['history'] = $price_history;
    
    // แปลง Type ข้อมูลสำหรับ JSON
    $product_data['price'] = (float)$product_data['price'];
    foreach ($product_data['history'] as &$h) {
        $h['change_from'] = isset($h['change_from']) ? (float)$h['change_from'] : 0;
        $h['change_to'] = isset($h['change_to']) ? (float)$h['change_to'] : 0;
    }

    // ส่งข้อมูลกลับไปให้ JavaScript
    echo json_encode($product_data);

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาดใดๆ ใน try block
    http_response_code(500); // Internal Server Error
    $response['message'] = "Server Error: " . $e->getMessage();
    echo json_encode($response);

} finally {
    // ปิดการเชื่อมต่อฐานข้อมูลเสมอ
    if (isset($conn)) {
        $conn->close();
    }
}

exit;
?>
