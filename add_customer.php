<?php
// Language: PHP
// File: add_customer.php

session_start();
include_once __DIR__ . '/config_db.php';

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cus_name = isset($_POST['cus_name']) ? trim($_POST['cus_name']) : '';

    // Validate
    if ($cus_name === '') {
        $_SESSION['error'] = 'Customer name is required.';
        header('Location: customers_list.php');
        exit;
    }

    // Check duplicate
    $dupSql = 'SELECT COUNT(*) FROM customer_list WHERE cus_name = ?';
    $stmtDup = $conn->prepare($dupSql);
    $stmtDup->bind_param('s', $cus_name);
    $stmtDup->execute();
    $stmtDup->bind_result($dupCount);
    $stmtDup->fetch();
    $stmtDup->close();

    if ($dupCount > 0) {
        $_SESSION['error'] = 'Customer name already exists.';
        header('Location: customers_list.php');
        exit;
    }

    // Insert new
    $insertSql = 'INSERT INTO customer_list (cus_name, status) VALUES (?, ?)';
    $stmtIns = $conn->prepare($insertSql);
    $status = 'active';
    $stmtIns->bind_param('ss', $cus_name, $status);

    if ($stmtIns->execute()) {
        $_SESSION['success'] = 'New customer added successfully.';
    } else {
        $_SESSION['error'] = 'Failed to add new customer.';
    }
    $stmtIns->close();
    $conn->close();

    header('Location: customers_list.php');
    exit;
}

// If not POST, redirect
header('Location: customers_list.php');
exit;
?>
