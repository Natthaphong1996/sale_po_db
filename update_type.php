<?php
// Language: PHP
// File: update_type.php

session_start();
include_once __DIR__ . '/config_db.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: prod_type_list.php');
    exit;
}

// Retrieve and sanitize inputs
$type_id   = isset($_POST['type_id']) ? (int) $_POST['type_id'] : 0;
$type_name = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';

// Basic validation
if ($type_id <= 0 || $type_name === '') {
    $_SESSION['error'] = 'Invalid product type data.';
    header('Location: prod_type_list.php');
    exit;
}

// Check for duplicate name (excluding current record)
$dupSql = 'SELECT COUNT(*) FROM prod_type_list WHERE type_name = ? AND type_id != ?';
$stmtDup = $conn->prepare($dupSql);
$stmtDup->bind_param('si', $type_name, $type_id);
$stmtDup->execute();
$stmtDup->bind_result($dupCount);
$stmtDup->fetch();
$stmtDup->close();

if ($dupCount > 0) {
    $_SESSION['error'] = 'Product type name already exists.';
    header('Location: prod_type_list.php');
    exit;
}

// Perform update
$updateSql = 'UPDATE prod_type_list SET type_name = ? WHERE type_id = ?';
$stmtUpd = $conn->prepare($updateSql);
$stmtUpd->bind_param('si', $type_name, $type_id);

if ($stmtUpd->execute()) {
    $_SESSION['success'] = 'Product type updated successfully.';
} else {
    $_SESSION['error'] = 'Failed to update product type.';
}

$stmtUpd->close();
$conn->close();

// Redirect back to list page
header('Location: prod_type_list.php');
exit;
?>
