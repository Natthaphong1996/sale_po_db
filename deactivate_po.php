<?php
// Language: PHP
// File: deactivate_po.php

/**
 * -------------------------------------------------------------------------
 * Deactivate Purchase Order Script with Reason (Fixed)
 * -------------------------------------------------------------------------
 * This script handles changing a PO's status to 'deactivated' and
 * records the reason for the cancellation.
 * Includes improved error handling for diagnostics.
 */

// Set timezone and start session if not already started.
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration.
include_once __DIR__ . '/config_db.php';

// Security Check 1: Ensure the user is logged in.
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_error'] = 'กรุณาเข้าสู่ระบบเพื่อดำเนินการ';
    header('Location: login.php');
    exit;
}

// Security Check 2: Ensure the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'คำขอไม่ถูกต้อง';
    header('Location: po_list.php');
    exit;
}

// Retrieve and validate po_no and the cancellation reason from the POST request.
$po_no = trim($_POST['po_no'] ?? '');
$cancel_reason = trim($_POST['cancel_reason'] ?? '');

// Validation
if (empty($po_no)) {
    $_SESSION['flash_error'] = 'ไม่พบหมายเลขใบสั่งซื้อ';
    header('Location: po_list.php');
    exit;
}
// Ensure the reason is not empty.
if (empty($cancel_reason)) {
    $_SESSION['flash_error'] = 'กรุณาระบุเหตุผลในการยกเลิกใบสั่งซื้อ';
    header('Location: po_list.php');
    exit;
}

// --- Database Update Logic ---

// Prepare the SQL statement to update the status and the cancellation reason.
// The SQL statement now updates two fields: status and cancel_reason.
$stmt = $conn->prepare("UPDATE po_list SET status = 'deactivated', cancel_reason = ? WHERE po_no = ?");

// Check if the statement preparation was successful.
// This is a critical step for debugging. If it fails, it often means there's a typo in the table or column names.
if ($stmt === false) {
    // Log the actual database error for the administrator.
    error_log("SQL Prepare Error: " . $conn->error);
    // Provide a user-friendly error message.
    $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL. กรุณาตรวจสอบว่าคอลัมน์ 'cancel_reason' มีอยู่ในตาราง 'po_list'.";
    header('Location: po_list.php');
    exit;
}

// Bind parameters to the prepared statement.
// 's' for string (cancel_reason)
// 's' for string (po_no)
$stmt->bind_param('ss', $cancel_reason, $po_no);

// Execute the statement and set flash messages based on the outcome.
if ($stmt->execute()) {
    // Success!
    $_SESSION['flash_success'] = "ใบสั่งซื้อ #{$po_no} ถูกยกเลิกเรียบร้อยแล้ว";
} else {
    // Failure! Log the specific error.
    error_log("SQL Execute Error: " . $stmt->error);
    $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการยกเลิกใบสั่งซื้อ #{$po_no}.";
}

// Close the statement and the database connection.
$stmt->close();
$conn->close();

// Redirect the user back to the PO list page to see the result.
header('Location: po_list.php');
exit;
?>
