<?php
// ภาษา: PHP
// ชื่อไฟล์: ajax_get_po_history.php

// เริ่ม Output Buffering เพื่อป้องกันปัญหา header
ob_start();

// กำหนด header ให้เป็น JSON
header('Content-Type: application/json; charset=utf-8');

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include_once __DIR__ . '/config_db.php';
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    // ใช้ ob_end_clean() ก่อน echo เพื่อลบ buffer ที่อาจมี
    ob_end_clean();
    echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (Database connection failed)']);
    exit;
}

// ตรวจสอบว่ามี po_no ถูกส่งมาหรือไม่
if (empty($_GET['po_no'])) {
    http_response_code(400); 
    ob_end_clean();
    echo json_encode(['error' => 'ไม่พบหมายเลขใบสั่งซื้อ (PO number is missing)']);
    exit;
}
$poNo = $_GET['po_no'];

$history = [];
try {
    // เตรียม SQL query เพื่อดึงข้อมูลประวัติ
    $sql = "
        SELECT 
            h.hist_id,
            h.version,
            h.changed_data,
            h.changed_by,
            h.changed_at,
            pr.prod_code
        FROM po_item_history h
        JOIN po_items pi ON h.item_id = pi.item_id
        JOIN product_list pr ON pi.prod_id = pr.prod_id
        JOIN po_list pl ON h.po_id = pl.po_id
        WHERE pl.po_no = ?
        ORDER BY h.changed_at DESC, h.hist_id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $poNo);
    $stmt->execute();
    $result = $stmt->get_result();

    // ดึงข้อมูลทั้งหมดเก็บใน array
    $history = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();
    
    // ล้าง buffer ก่อนส่ง output สุดท้าย
    ob_end_clean();
    // ส่งข้อมูลกลับไปในรูปแบบ JSON
    echo json_encode($history);

} catch (Exception $e) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage()]);
}
?>
