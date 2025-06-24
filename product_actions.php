<?php
// Language: PHP
// File: product_actions.php
// Description: Handles all actions related to products and product types.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';

// --- PRODUCT ACTIONS (ADD/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'product') {
    $action      = $_POST['action'];
    $prod_id     = (int)($_POST['prod_id'] ?? 0);
    $prod_code   = trim($_POST['prod_code'] ?? '');
    $prod_desc   = trim($_POST['prod_desc'] ?? '');
    $type_id     = (int)($_POST['type_id'] ?? 0);
    $thickness   = (int)($_POST['thickness'] ?? 0);
    $width       = (int)($_POST['width'] ?? 0);
    $length      = (int)($_POST['length'] ?? 0);
    $price       = (float)($_POST['price'] ?? 0);
    $status      = $_POST['status'] ?? 'active';

    // Basic Validation
    if (empty($prod_code) || $type_id <= 0 || $price <= 0) {
        $_SESSION['flash_error'] = 'Product Code, Type, and Price are required.';
        header('Location: products_list.php');
        exit;
    }

    if ($action === 'add') {
        // Add new product logic here...
        $sql = "INSERT INTO product_list (prod_code, prod_desc, type_id, thickness, width, length, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssiiidd', $prod_code, $prod_desc, $type_id, $thickness, $width, $length, $price);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'New product added successfully.';
        } else {
            $_SESSION['flash_error'] = 'Failed to add product. It might be a duplicate code.';
        }
    } elseif ($action === 'update' && $prod_id > 0) {
        // Update existing product logic here...
        $sql = "UPDATE product_list SET prod_code=?, prod_desc=?, type_id=?, thickness=?, width=?, length=?, price=?, status=? WHERE prod_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssiiiddsi', $prod_code, $prod_desc, $type_id, $thickness, $width, $length, $price, $status, $prod_id);
         if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Product updated successfully.';
        } else {
            $_SESSION['flash_error'] = 'Failed to update product.';
        }
    }
    $stmt->close();
    header('Location: products_list.php');
    exit;
}

// --- PRODUCT STATUS CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['prod_id'], $_GET['action']) && in_array($_GET['action'], ['activate_prod', 'deactivate_prod'])) {
    $prod_id = (int)$_GET['prod_id'];
    $new_status = ($_GET['action'] === 'activate_prod') ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE product_list SET status = ? WHERE prod_id = ?");
    $stmt->bind_param('si', $new_status, $prod_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Product status updated.";
    } else {
        $_SESSION['flash_error'] = "Failed to update product status.";
    }
    $stmt->close();
    header('Location: products_list.php');
    exit;
}


// --- PRODUCT TYPE ACTIONS (ADD/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'type') {
    $action = $_POST['action'];
    $type_id = (int)($_POST['type_id'] ?? 0);
    $type_name = trim($_POST['type_name'] ?? '');

    if (empty($type_name)) {
        $_SESSION['flash_error'] = 'Type name cannot be empty.';
        header('Location: prod_type_list.php');
        exit;
    }

    if ($action === 'add') {
        // Add new type logic here...
        $stmt = $conn->prepare("INSERT INTO prod_type_list (type_name) VALUES (?)");
        $stmt->bind_param('s', $type_name);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'New product type added.';
        } else {
            $_SESSION['flash_error'] = 'Failed to add type. It might be a duplicate.';
        }
    } elseif ($action === 'update' && $type_id > 0) {
        // Update existing type logic here...
        $stmt = $conn->prepare("UPDATE prod_type_list SET type_name = ? WHERE type_id = ?");
        $stmt->bind_param('si', $type_name, $type_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Product type updated.';
        } else {
            $_SESSION['flash_error'] = 'Failed to update type.';
        }
    }
    $stmt->close();
    header('Location: prod_type_list.php');
    exit;
}

// --- PRODUCT TYPE STATUS CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['type_id'], $_GET['action']) && in_array($_GET['action'], ['activate_type', 'deactivate_type'])) {
    $type_id = (int)$_GET['type_id'];
    $new_status = ($_GET['action'] === 'activate_type') ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE prod_type_list SET status = ? WHERE type_id = ?");
    $stmt->bind_param('si', $new_status, $type_id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Product type status updated.";
    } else {
        $_SESSION['flash_error'] = "Failed to update type status.";
    }
    $stmt->close();
    header('Location: prod_type_list.php');
    exit;
}


// Redirect if no valid action is found
$_SESSION['flash_error'] = 'Invalid request.';
header('Location: index.php'); // Redirect to a safe page
exit;
?>
