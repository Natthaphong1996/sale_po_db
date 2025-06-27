<?php
// Language: PHP
// File: edit_po.php

// --- SETUP AND SECURITY ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Bangkok');
include_once __DIR__ . '/config_db.php';

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: po_list.php');
    exit;
}

// --- DATA VALIDATION ---
$po_no      = $_POST['po_no'] ?? null;
$po_date    = $_POST['po_date'] ?? null;
$items      = $_POST['items'] ?? [];
$username   = $_SESSION['username'] ?? 'system'; // Get username from session

if (empty($po_no) || empty($po_date) || empty($items)) {
    $_SESSION['flash_error'] = 'Invalid data submitted. Please try again.';
    header('Location: edit_po_view.php?po_no=' . urlencode($po_no));
    exit;
}

// --- DATABASE TRANSACTION ---
$conn->begin_transaction();

try {
    // 1. Get po_id and status from po_no
    // *** CHANGE: Fetch 'status' along with 'po_id' to check if editing is allowed. ***
    $stmt_get_po_info = $conn->prepare("SELECT po_id, status FROM po_list WHERE po_no = ?");
    $stmt_get_po_info->bind_param('s', $po_no);
    $stmt_get_po_info->execute();
    $po_info = $stmt_get_po_info->get_result()->fetch_assoc();
    $stmt_get_po_info->close();

    if (!$po_info) {
        throw new Exception("PO number not found.");
    }
    
    $po_id = $po_info['po_id'];
    
    // *** MAJOR CHANGE: Server-side check to prevent updating a deactivated PO. ***
    // This is a crucial security step.
    if ($po_info['status'] === 'deactivated') {
        throw new Exception("Cannot update a cancelled PO (#" . htmlspecialchars($po_no) . ").");
    }

    // 2. Update the main PO date in po_list
    $stmt_update_po = $conn->prepare("UPDATE po_list SET po_date = ? WHERE po_id = ?");
    $stmt_update_po->bind_param('si', $po_date, $po_id);
    $stmt_update_po->execute();
    $stmt_update_po->close();

    $changes_found = false;

    // 3. Loop through each item to check for updates
    foreach ($items as $item_data) {
        $item_id                = $item_data['item_id'];
        $new_qty                = $item_data['qty'];
        $new_delivery_date      = $item_data['delivery_date'];
        $new_actual_delivery_date = $item_data['actual_delivery_date'];
        
        // Fetch the original item data to compare
        $stmt_fetch_old = $conn->prepare("SELECT qty, delivery_date, actual_delivery_date FROM po_items WHERE item_id = ? AND po_id = ?");
        $stmt_fetch_old->bind_param('ii', $item_id, $po_id);
        $stmt_fetch_old->execute();
        $old_item = $stmt_fetch_old->get_result()->fetch_assoc();
        $stmt_fetch_old->close();

        if (!$old_item) continue; // Skip if item not found

        // Compare old and new values to see if anything changed
        $item_changed_data = [];
        if ($old_item['qty'] != $new_qty) {
            $item_changed_data['qty'] = ['from' => $old_item['qty'], 'to' => $new_qty];
        }
        if ($old_item['delivery_date'] != $new_delivery_date) {
            $item_changed_data['delivery_date'] = ['from' => $old_item['delivery_date'], 'to' => $new_delivery_date];
        }
        if ($old_item['actual_delivery_date'] != $new_actual_delivery_date) {
             $item_changed_data['actual_delivery_date'] = ['from' => $old_item['actual_delivery_date'], 'to' => $new_actual_delivery_date];
        }
        
        // If there were changes for this item, update it and log history
        if (!empty($item_changed_data)) {
            $changes_found = true;
            
            // 3a. Update the item in po_items table
            $stmt_update_item = $conn->prepare(
                "UPDATE po_items SET qty = ?, delivery_date = ?, actual_delivery_date = ? WHERE item_id = ?"
            );
            $stmt_update_item->bind_param('issi', $new_qty, $new_delivery_date, $new_actual_delivery_date, $item_id);
            if (!$stmt_update_item->execute()) {
                throw new Exception("Failed to update item ID: {$item_id}.");
            }
            $stmt_update_item->close();
            
            // 3b. Insert a record into po_item_history
            $json_changed_data = json_encode($item_changed_data);
            $stmt_insert_history_final = $conn->prepare(
                "INSERT INTO po_item_history (item_id, po_id, version, price, changed_data, changed_by)
                 VALUES (?, ?, (SELECT COALESCE(MAX(h.version), 0) + 1 FROM po_item_history h WHERE h.item_id = ?), (SELECT i.price FROM po_items i WHERE i.item_id = ?), ?, ?)"
            );
            $stmt_insert_history_final->bind_param('iiiiss', $item_id, $po_id, $item_id, $item_id, $json_changed_data, $username);

            if (!$stmt_insert_history_final->execute()) {
                throw new Exception("Failed to save history for item ID: {$item_id}.");
            }
            $stmt_insert_history_final->close();
        }
    }

    // --- COMMIT AND REDIRECT ---
    $conn->commit();
    $_SESSION['flash_success'] = $changes_found 
        ? 'Update completed and history saved.' 
        : 'Update successful. No changes were detected.';

} catch (Exception $e) {
    // If any error occurs, rollback all changes
    $conn->rollback();
    // Set a more specific error message to the session
    $_SESSION['flash_error'] = 'An error occurred: ' . $e->getMessage();

} finally {
    // Always close the connection and redirect
    if (isset($conn)) {
        $conn->close();
    }
    header('Location: edit_po_view.php?po_no=' . urlencode($po_no));
    exit;
}
