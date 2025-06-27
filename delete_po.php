<?php
// Language: PHP
// File: delete_po.php

/**
 * -------------------------------------------------------------------------
 * Delete Purchase Order Script
 * -------------------------------------------------------------------------
 * This script handles the complete deletion of a Purchase Order and all its
 * related data (items, history, PDFs) within a database transaction
 * to ensure data integrity.
 */

// --- SETUP AND SECURITY ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Bangkok');
include_once __DIR__ . '/config_db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_error'] = 'Please log in to perform this action.';
    header('Location: login.php');
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: po_list.php');
    exit;
}

// Get the PO number from the form submission
$po_no = $_POST['po_no'] ?? null;

if (empty($po_no)) {
    $_SESSION['flash_error'] = 'PO Number is missing.';
    header('Location: po_list.php');
    exit;
}

// --- DATABASE TRANSACTION ---
// Start a transaction to ensure all or nothing is deleted.
$conn->begin_transaction();

try {
    // 1. Get the po_id from the po_no
    $stmt_get_id = $conn->prepare("SELECT po_id FROM po_list WHERE po_no = ?");
    $stmt_get_id->bind_param('s', $po_no);
    $stmt_get_id->execute();
    $result_id = $stmt_get_id->get_result();
    $po_data = $result_id->fetch_assoc();
    $stmt_get_id->close();

    if (!$po_data) {
        throw new Exception("PO Number " . htmlspecialchars($po_no) . " not found.");
    }
    $po_id = $po_data['po_id'];

    // 2. Get all item_ids associated with this po_id
    $stmt_get_items = $conn->prepare("SELECT item_id FROM po_items WHERE po_id = ?");
    $stmt_get_items->bind_param('i', $po_id);
    $stmt_get_items->execute();
    $items_result = $stmt_get_items->get_result();
    $item_ids = [];
    while ($row = $items_result->fetch_assoc()) {
        $item_ids[] = $row['item_id'];
    }
    $stmt_get_items->close();

    // 3. Delete from child tables first to avoid foreign key constraint errors
    
    // 3a. Delete from po_item_history (if any items existed)
    if (!empty($item_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($item_ids), '?'));
        $types = str_repeat('i', count($item_ids));
        
        $stmt_delete_history = $conn->prepare("DELETE FROM po_item_history WHERE item_id IN ($ids_placeholder)");
        $stmt_delete_history->bind_param($types, ...$item_ids);
        $stmt_delete_history->execute();
        $stmt_delete_history->close();
    }
    
    // 3b. Delete from po_items
    $stmt_delete_items = $conn->prepare("DELETE FROM po_items WHERE po_id = ?");
    $stmt_delete_items->bind_param('i', $po_id);
    $stmt_delete_items->execute();
    $stmt_delete_items->close();

    // 3c. Delete related PDF files from po_pdf
    $stmt_delete_pdf = $conn->prepare("DELETE FROM po_pdf WHERE po_no = ?");
    $stmt_delete_pdf->bind_param('s', $po_no);
    $stmt_delete_pdf->execute();
    $stmt_delete_pdf->close();

    // 4. Finally, delete the parent record from po_list
    $stmt_delete_po = $conn->prepare("DELETE FROM po_list WHERE po_id = ?");
    $stmt_delete_po->bind_param('i', $po_id);
    $stmt_delete_po->execute();
    $stmt_delete_po->close();

    // 5. If all deletions were successful, commit the transaction
    $conn->commit();
    $_SESSION['flash_success'] = "Successfully deleted PO #" . htmlspecialchars($po_no) . " and all related data.";

} catch (Exception $e) {
    // If any error occurs, rollback all changes
    $conn->rollback();
    // Log the error for the admin and show a generic message to the user
    error_log("Failed to delete PO #{$po_no}: " . $e->getMessage());
    $_SESSION['flash_error'] = "An error occurred while trying to delete the PO. Please contact support.";

} finally {
    // Always close the connection and redirect
    if (isset($conn)) {
        $conn->close();
    }
    header('Location: po_list.php');
    exit;
}
