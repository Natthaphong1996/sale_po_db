<?php
// Language: PHP
// File: add_type.php

session_start();
include_once __DIR__ . '/config_db.php';

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_name = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';

    // Validate
    if ($type_name === '') {
        $_SESSION['error'] = 'Type name is required.';
        header('Location: prod_type_list.php');
        exit;
    }

    // Check duplicate
    $dupSql = 'SELECT COUNT(*) FROM prod_type_list WHERE type_name = ?';
    $stmtDup = $conn->prepare($dupSql);
    $stmtDup->bind_param('s', $type_name);
    $stmtDup->execute();
    $stmtDup->bind_result($dupCount);
    $stmtDup->fetch();
    $stmtDup->close();

    if ($dupCount > 0) {
        $_SESSION['error'] = 'Product type already exists.';
        header('Location: prod_type_list.php');
        exit;
    }

    // Insert new type (default status active)
    $insertSql = 'INSERT INTO prod_type_list (type_name, status) VALUES (?, ?)';
    $stmtIns = $conn->prepare($insertSql);
    $status = 'active';
    $stmtIns->bind_param('ss', $type_name, $status);

    if ($stmtIns->execute()) {
        $_SESSION['success'] = 'New product type added successfully.';
    } else {
        $_SESSION['error'] = 'Failed to add new product type.';
    }
    $stmtIns->close();
    $conn->close();

    header('Location: prod_type_list.php');
    exit;
}

// If not POST, redirect back
header('Location: prod_type_list.php');
exit;
?>
