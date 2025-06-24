<?php
// Language: PHP
// File: register_new_po.php
// Description: Securely processes the creation of a new Purchase Order with its items using a database transaction.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Bangkok');
include_once __DIR__ . '/config_db.php';

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: po_list.php');
    exit;
}

// --- 1. Data Validation and Preparation ---
$po_no   = trim($_POST['po_no'] ?? '');
$po_date = trim($_POST['po_date'] ?? '');
$cus_id  = (int)($_POST['cus_id'] ?? 0);
$items   = $_POST['items'] ?? [];

$errors = [];
if (empty($po_no)) $errors[] = 'PO Number is required.';
if (empty($po_date)) $errors[] = 'PO Date is required.';
if ($cus_id <= 0) $errors[] = 'A valid customer must be selected.';
if (empty($items)) $errors[] = 'At least one product item is required.';

// Further validation for each item
foreach ($items as $index => $item) {
    if (empty($item['prod_id']) || (int)$item['prod_id'] <= 0) $errors[] = "Product must be selected for item #" . ($index + 1) . ".";
    if (empty($item['qty']) || (int)$item['qty'] <= 0) $errors[] = "Quantity must be a positive number for item #" . ($index + 1) . ".";
    if (empty($item['price']) || !is_numeric($item['price'])) $errors[] = "Price is invalid for item #" . ($index + 1) . ".";
    if (empty($item['delivery_date'])) $errors[] = "Delivery Date is required for item #" . ($index + 1) . ".";
}

// Check for duplicate PO Number
$stmt_check = $conn->prepare("SELECT po_id FROM po_list WHERE po_no = ?");
$stmt_check->bind_param('s', $po_no);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $errors[] = "PO Number '{$po_no}' already exists.";
}
$stmt_check->close();

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_data'] = $_POST; // Keep form data to refill
    header('Location: register_new_po_view.php');
    exit;
}


// --- 2. Database Transaction ---
// Use a transaction to ensure all or nothing is saved.
$conn->begin_transaction();

try {
    // --- Step 2a: Insert into po_list (the header) ---
    $stmt_po = $conn->prepare("INSERT INTO po_list (po_no, cus_id, po_date) VALUES (?, ?, ?)");
    $stmt_po->bind_param('sis', $po_no, $cus_id, $po_date);
    if (!$stmt_po->execute()) {
        throw new Exception("Failed to save PO header: " . $stmt_po->error);
    }

    // Get the ID of the newly inserted PO
    $po_id = $conn->insert_id;
    $stmt_po->close();

    // --- Step 2b: Insert each item into po_items ---
    $stmt_item = $conn->prepare(
        "INSERT INTO po_items (po_id, prod_id, qty, price, delivery_date, actual_delivery_date) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $prod_id = (int)$item['prod_id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $delivery_date = $item['delivery_date'];
        // The actual_delivery_date is the same as delivery_date on creation
        $actual_delivery_date = $item['actual_delivery_date'] ?: $delivery_date; 
        
        $stmt_item->bind_param('iiidss', $po_id, $prod_id, $qty, $price, $delivery_date, $actual_delivery_date);
        
        if (!$stmt_item->execute()) {
            // If any item fails, throw an exception to trigger the rollback
            throw new Exception("Failed to save PO item: " . $stmt_item->error);
        }
    }
    $stmt_item->close();

    // --- Step 2c: Handle File Upload (if any) ---
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/pdf/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = time() . '_' . basename($_FILES['attachment']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
            $stmt_pdf = $conn->prepare("INSERT INTO po_pdf (po_no, path_file) VALUES (?, ?)");
            $stmt_pdf->bind_param('ss', $po_no, $targetPath);
            if (!$stmt_pdf->execute()) {
                // Log error but don't fail the whole transaction for an optional PDF
                error_log("Failed to save PDF path for PO {$po_no}: " . $stmt_pdf->error);
            }
            $stmt_pdf->close();
        } else {
             error_log("Failed to move uploaded file for PO {$po_no}.");
        }
    }

    // --- 3. Commit Transaction ---
    // If all steps were successful, commit the changes to the database
    $conn->commit();
    $_SESSION['flash_success'] = "Purchase Order '{$po_no}' has been created successfully.";
    header('Location: po_list.php');
    exit;

} catch (Exception $e) {
    // --- 4. Rollback Transaction on Error ---
    $conn->rollback();
    $_SESSION['errors'] = ['An error occurred during save: ' . $e->getMessage()];
    $_SESSION['old_data'] = $_POST;
    header('Location: register_new_po_view.php');
    exit;

} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
