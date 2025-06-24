<?php
// Language: PHP
// File: register_new_po_view.php

// กำหนดโซนเวลาและเริ่มต้น Session
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include_once __DIR__ . '/config_db.php';

// ดึงข้อมูลลูกค้าที่ยัง Active อยู่
$customers = $conn->query(
    "SELECT cus_id, cus_name FROM customer_list WHERE status='active' ORDER BY cus_name"
)->fetch_all(MYSQLI_ASSOC);

// ดึงข้อมูลสินค้าทั้งหมด พร้อมราคาล่าสุดจากตาราง product_list
$products = $conn->query(
    "SELECT prod_id, prod_code, price, CONCAT(thickness,'×',width,'×',length) AS size 
     FROM product_list 
     ORDER BY prod_code"
)->fetch_all(MYSQLI_ASSOC);

// เข้ารหัสข้อมูลสินค้าเป็น JSON เพื่อใช้ใน JavaScript อย่างปลอดภัย
$products_json = json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// ดึงข้อมูลฟอร์มเก่าหรือข้อผิดพลาดจาก Session (ถ้ามี) เพื่อแสดงผล
$old_data = $_SESSION['old_data'] ?? [];
$po_no    = $old_data['po_no'] ?? '';
$po_date  = $old_data['po_date'] ?? date('Y-m-d');
$cus_id   = $old_data['cus_id'] ?? '';
$errors   = $_SESSION['errors'] ?? [];

// ล้างข้อมูลเก่าออกจาก Session หลังจากใช้งานแล้ว
unset($_SESSION['old_data']);
unset($_SESSION['errors']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Purchase Order</title>

    <!-- Section: CSS Libraries (CDN) -->
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Select2 CSS for advanced dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- DateRangePicker CSS for date selection -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <style>
        /* Custom styles to improve UI */
        body {
            background-color: #f8f9fa; /* Light gray background */
        }
        .card {
            border: none; /* Remove default card border */
        }
        .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px); /* Match Bootstrap input height */
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.035);
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <div class="container my-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary bg-gradient text-white">
                <h4 class="mb-0"><i class="bi bi-journal-plus me-2"></i>Register New Purchase Order</h4>
            </div>
            <div class="card-body p-4">

                <!-- Display validation errors if any -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5 class="alert-heading">Please correct the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="post" action="register_new_po.php" id="po-form" enctype="multipart/form-data" novalidate>
                    <!-- Section 1: PO Header Information -->
                    <fieldset class="border p-3 rounded mb-4">
                        <legend class="float-none w-auto px-2 h6">Header Information</legend>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="po_no" class="form-label">PO Number <span class="text-danger">*</span></label>
                                <input type="text" id="po_no" name="po_no" class="form-control" value="<?= htmlspecialchars($po_no, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="po_date" class="form-label">PO Date <span class="text-danger">*</span></label>
                                <input type="text" id="po_date" name="po_date" class="form-control" value="<?= htmlspecialchars($po_date, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label for="cus_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select id="cus_id" name="cus_id" class="form-select select2-customer" required>
                                    <option value="" disabled <?= empty($cus_id) ? 'selected' : '' ?>>-- Select Customer --</option>
                                    <?php foreach($customers as $c): ?>
                                        <option value="<?= $c['cus_id'] ?>" <?= ($cus_id == $c['cus_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['cus_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Section 2: Product Items -->
                    <fieldset class="border p-3 rounded mb-4">
                        <legend class="float-none w-auto px-2 h6">Product Items</legend>
                        <div class="d-flex justify-content-end mb-2">
                            <button type="button" id="addRowBtn" class="btn btn-sm btn-success">
                                <i class="bi bi-plus-circle me-1"></i>Add Row
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0" id="productTable">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th style="width:5%;">#</th>
                                        <th style="width:35%;">Product</th>
                                        <th style="width:10%;">Qty</th>
                                        <th style="width:15%;">Unit Price</th>
                                        <th style="width:15%;">Delivery Date</th>
                                        <th style="width:15%;">Actual Delivery</th>
                                        <th style="width:5%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic rows will be inserted here by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <!-- Section 3: Attachment and Submission -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                             <label for="attachment" class="form-label">Attach PDF (optional)</label>
                             <input type="file" id="attachment" name="attachment" accept="application/pdf" class="form-control">
                        </div>
                    </div>
                    <div class="text-end border-top pt-3">
                        <a href="po_list.php" class="btn btn-secondary me-2">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Save Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Section: JavaScript Libraries (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script>
    // Execute script when the document is fully loaded
    $(function(){
        // Store product data from PHP into a JavaScript variable
        const productsData = <?= $products_json ?>;

        // --- INITIALIZE PLUGINS ---
        
        // Initialize Select2 for the main customer dropdown
        $('.select2-customer').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Select Customer --'
        });

        // Initialize Daterangepicker for the main PO date field
        $('#po_date').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoApply: true,
            locale: {
                format: 'YYYY-MM-DD'
            }
        });

        // --- DYNAMIC ROW LOGIC ---

        /**
         * Creates the HTML for a new product row in the table.
         * @param {number} index - The index of the new row.
         * @returns {string} The HTML string for the new table row.
         */
        function createProductRow(index) {
            // Generate <option> tags for the product dropdown
            const productOptions = productsData.map(p => 
                `<option value="${p.prod_id}" data-price="${p.price}">
                    ${p.prod_code} - ${p.size}
                </option>`
            ).join('');

            return `
                <tr data-index="${index}">
                    <td class="text-center align-middle row-number">${index + 1}</td>
                    <td>
                        <select name="items[${index}][prod_id]" class="form-select select2-product" required>
                            <option value="" disabled selected>-- Select Product --</option>
                            ${productOptions}
                        </select>
                    </td>
                    <td><input type="number" name="items[${index}][qty]" class="form-control qty-input text-end" min="1" required></td>
                    <td><input type="number" step="0.01" name="items[${index}][price]" class="form-control price-input text-end" required></td>
                    <td><input type="text" name="items[${index}][delivery_date]" class="form-control date-picker" required autocomplete="off"></td>
                    <td><input type="text" name="items[${index}][actual_delivery_date]" class="form-control actual-picker" readonly></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        /**
         * Initializes plugins (Select2, Daterangepicker) for a newly added row.
         * @param {jQuery} $row - The jQuery object representing the new table row.
         */
        function initializePluginsForRow($row) {
            // Initialize Select2 for the product dropdown in the new row
            $row.find('.select2-product').select2({
                theme: 'bootstrap-5',
                placeholder: '-- Select Product --'
            });

            // Initialize Daterangepicker for the delivery date field
            $row.find('.date-picker').daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                autoApply: true,
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
        }
        
        /**
         * Re-numbers all rows in the table after a deletion.
         */
        function renumberRows() {
            $('#productTable tbody tr').each(function(index) {
                $(this).find('.row-number').text(index + 1);
            });
        }

        // Function to add a new row to the table
        function addRow() {
            const newIndex = $('#productTable tbody tr').length;
            const newRowHtml = createProductRow(newIndex);
            const $newRow = $(newRowHtml);
            $('#productTable tbody').append($newRow);
            initializePluginsForRow($newRow);
        }

        // --- EVENT HANDLERS ---
        
        // Add Row Button: Click event to add a new product row.
        $('#addRowBtn').on('click', addRow);

        // Product Table: Delegated event for dynamically added elements.
        $('#productTable tbody').on('click', '.remove-row-btn', function() {
            // Allow removal only if there is more than one row
            if ($('#productTable tbody tr').length > 1) {
                $(this).closest('tr').remove();
                renumberRows();
            } else {
                alert('At least one product item is required.');
            }
        }).on('change', '.select2-product', function() {
            // When a product is selected, automatically fill its price.
            const $row = $(this).closest('tr');
            const selectedOption = $(this).find('option:selected');
            const price = selectedOption.data('price') || 0;
            $row.find('.price-input').val(parseFloat(price).toFixed(2));
        }).on('apply.daterangepicker', '.date-picker', function(event, picker) {
            // When a delivery date is picked, set the actual delivery date to the same value.
            const $row = $(this).closest('tr');
            const selectedDate = picker.startDate.format('YYYY-MM-DD');
            $row.find('.actual-picker').val(selectedDate);
        });

        // --- INITIAL STATE ---
        
        // Add one row by default when the page loads.
        addRow();
    });
    </script>
</body>
</html>
