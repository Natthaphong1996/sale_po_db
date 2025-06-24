<?php
// Language: PHP
// File: update_customer.php

session_start();
include_once __DIR__ . '/config_db.php';

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers_list.php');
    exit;
}

// Retrieve and sanitize inputs
$cus_id   = isset($_POST['cus_id']) ? (int) $_POST['cus_id'] : 0;$cus_name = isset($_POST['cus_name']) ? trim($_POST['cus_name']) : '';

// Validation: check required
if ($cus_id <= 0 || $cus_name === '') {
    $_SESSION['error'] = 'Invalid customer data.';
    header('Location: customers_list.php');
    exit;
}

// Check for duplicate name (excluding current record)
$dupSql = 'SELECT COUNT(*) FROM customer_list WHERE cus_name = ? AND cus_id != ?';
$stmtDup = $conn->prepare($dupSql);
$stmtDup->bind_param('si', $cus_name, $cus_id);
$stmtDup->execute();
$stmtDup->bind_result($dupCount);
$stmtDup->fetch();
$stmtDup->close();

if ($dupCount > 0) {
    $_SESSION['error'] = 'Customer name already exists.';
    header('Location: customers_list.php');
    exit;
}

// Perform update
$updateSql = 'UPDATE customer_list SET cus_name = ? WHERE cus_id = ?';
$stmtUpd = $conn->prepare($updateSql);
$stmtUpd->bind_param('si', $cus_name, $cus_id);

if ($stmtUpd->execute()) {
    $_SESSION['success'] = 'Customer updated successfully.';
} else {
    $_SESSION['error'] = 'Failed to update customer.';
}
$stmtUpd->close();

// Redirect back to list
header('Location: customers_list.php');
exit;
?>
