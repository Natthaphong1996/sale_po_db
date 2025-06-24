<?php
// Language: PHP
// File: deactivate_customer.php

session_start();
include_once __DIR__ . '/config_db.php';

// Ensure GET request with valid cus_id
if (!isset($_GET['cus_id']) || !is_numeric($_GET['cus_id'])) {
    $_SESSION['error'] = 'Invalid customer ID.';
    header('Location: customers_list.php');
    exit;
}

$cus_id = (int) $_GET['cus_id'];

// Update status to inactive
$sql = 'UPDATE customer_list SET status = ? WHERE cus_id = ?';
$stmt = $conn->prepare($sql);
$status = 'inactive';
$stmt->bind_param('si', $status, $cus_id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Customer has been deactivated successfully.';
} else {
    $_SESSION['error'] = 'Failed to deactivate customer.';
}

$stmt->close();
$conn->close();

// Redirect back to customer list
header('Location: customers_list.php');
exit;
?>