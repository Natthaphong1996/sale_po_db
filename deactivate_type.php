<?php
// Language: PHP
// File: deactivate_type.php

session_start();
include_once __DIR__ . '/config_db.php';

// Validate type_id
if (!isset($_GET['type_id']) || !is_numeric($_GET['type_id'])) {
    $_SESSION['error'] = 'Invalid product type ID.';
    header('Location: prod_type_list.php');
    exit;
}

$type_id = (int) $_GET['type_id'];

// Update status to inactive
$sql = 'UPDATE prod_type_list SET status = ? WHERE type_id = ?';
$stmt = $conn->prepare($sql);
$status = 'inactive';
$stmt->bind_param('si', $status, $type_id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Product type deactivated successfully.';
} else {
    $_SESSION['error'] = 'Failed to deactivate product type.';
}

$stmt->close();
$conn->close();

header('Location: prod_type_list.php');
exit;
?>
