<?php
// Language: PHP
// File: customer_actions.php
// Description: Handles adding, updating, activating, and deactivating customers.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';

// --- ADD NEW CUSTOMER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $cus_name = trim($_POST['cus_name'] ?? '');

    if (empty($cus_name)) {
        $_SESSION['flash_error'] = 'Customer name cannot be empty.';
    } else {
        // Check for duplicates
        $stmt = $conn->prepare("SELECT cus_id FROM customer_list WHERE cus_name = ?");
        $stmt->bind_param('s', $cus_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Customer '{$cus_name}' already exists.";
        } else {
            // Insert new customer
            $stmt_insert = $conn->prepare("INSERT INTO customer_list (cus_name, status) VALUES (?, 'active')");
            $stmt_insert->bind_param('s', $cus_name);
            if ($stmt_insert->execute()) {
                $_SESSION['flash_success'] = 'New customer added successfully.';
            } else {
                $_SESSION['flash_error'] = 'Failed to add customer.';
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
    header('Location: customers_list.php');
    exit;
}

// --- UPDATE CUSTOMER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $cus_id = (int)($_POST['cus_id'] ?? 0);
    $cus_name = trim($_POST['cus_name'] ?? '');

    if ($cus_id > 0 && !empty($cus_name)) {
        // Check for duplicates (excluding the current customer)
        $stmt = $conn->prepare("SELECT cus_id FROM customer_list WHERE cus_name = ? AND cus_id != ?");
        $stmt->bind_param('si', $cus_name, $cus_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Another customer with the name '{$cus_name}' already exists.";
        } else {
            $stmt_update = $conn->prepare("UPDATE customer_list SET cus_name = ? WHERE cus_id = ?");
            $stmt_update->bind_param('si', $cus_name, $cus_id);
            if ($stmt_update->execute()) {
                $_SESSION['flash_success'] = 'Customer updated successfully.';
            } else {
                $_SESSION['flash_error'] = 'Failed to update customer.';
            }
            $stmt_update->close();
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Invalid data provided for update.';
    }
    header('Location: customers_list.php');
    exit;
}


// --- CHANGE CUSTOMER STATUS (ACTIVATE/DEACTIVATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'], $_GET['cus_id'])) {
    $cus_id = (int)$_GET['cus_id'];
    $action = $_GET['action'];
    
    if ($cus_id > 0 && in_array($action, ['activate', 'deactivate'])) {
        $new_status = ($action === 'activate') ? 'active' : 'inactive';
        $stmt = $conn->prepare("UPDATE customer_list SET status = ? WHERE cus_id = ?");
        $stmt->bind_param('si', $new_status, $cus_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Customer has been {$new_status}d successfully.";
        } else {
            $_SESSION['flash_error'] = "Failed to {$action} customer.";
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Invalid action or customer ID.';
    }
    header('Location: customers_list.php');
    exit;
}

// Redirect if no valid action is found
$_SESSION['flash_error'] = 'Invalid request.';
header('Location: customers_list.php');
exit;
?>
