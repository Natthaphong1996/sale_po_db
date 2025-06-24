<?php
// ภาษา: PHP
// ชื่อไฟล์: delete_po.php

session_start();
include_once __DIR__ . '/config_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_no'])) {
    $po_no = trim($_POST['po_no']);

    // ลบทั้ง PO หลัก และ PDF ที่เกี่ยวข้อง
    $conn->begin_transaction();
    try {
        // ลบจาก po_list
        $stmt = $conn->prepare("DELETE FROM po_list WHERE po_no = ?");
        $stmt->bind_param("s", $po_no);
        $stmt->execute();
        $stmt->close();

        // ลบ PDF ไฟล์ถ้ามี
        $pdfQuery = $conn->prepare("SELECT path_file FROM po_pdf WHERE po_no = ?");
        $pdfQuery->bind_param("s", $po_no);
        $pdfQuery->execute();
        $pdfQuery->bind_result($filePath);
        while ($pdfQuery->fetch()) {
            $fullPath = __DIR__ . '/' . $filePath;
            if (file_exists($fullPath)) unlink($fullPath); // ลบไฟล์ออกจาก server
        }
        $pdfQuery->close();

        $stmt2 = $conn->prepare("DELETE FROM po_pdf WHERE po_no = ?");
        $stmt2->bind_param("s", $po_no);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        header("Location: po_list.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
    }
} else {
    header("Location: po_list.php");
    exit;
}
