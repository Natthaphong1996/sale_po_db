<?php
// Language: PHP
// File: edit_po_view.php

// กำหนดโซนเวลาและเริ่มต้น Session
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบและรับค่า po_no จาก GET parameter
$poNo = $_GET['po_no'] ?? $_GET['edit_po_no'] ?? null;
if (empty($poNo)) {
    $_SESSION['flash_error'] = 'ไม่พบหมายเลขใบสั่งซื้อ (PO No. is missing).';
    header('Location: po_list.php');
    exit;
}
$poNo = trim($poNo);

// 2. รวมไฟล์เชื่อมต่อฐานข้อมูล
include_once __DIR__ . '/config_db.php';

// 3. ดึงข้อมูลส่วนหัวของ PO และชื่อลูกค้า รวมถึงสถานะ
// *** CHANGE: Added 'pl.status' to the SELECT query to fetch the PO's status. ***
$stmt = $conn->prepare(
    "SELECT pl.po_id, pl.po_date, pl.cus_id, c.cus_name, pl.status 
     FROM po_list pl
     JOIN customer_list c ON pl.cus_id = c.cus_id
     WHERE pl.po_no = ?"
);
$stmt->bind_param('s', $poNo);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูลใบสั่งซื้อ (PO not found).';
    header('Location: po_list.php');
    exit;
}

// *** MAJOR CHANGE: Check if the PO is deactivated. If so, block editing. ***
// 4. ตรวจสอบสถานะ ถ้าถูกยกเลิกแล้ว ให้หยุดการทำงานและแสดงข้อความแจ้งเตือน
if ($header['status'] === 'deactivated') {
    include_once __DIR__ . '/navbar.php'; // Include navbar for consistent UI
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>Error: ไม่สามารถแก้ไขได้</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
    echo '</head><body>';
    echo '<div class="container mt-5">';
    echo '  <div class="alert alert-danger text-center" role="alert">';
    echo '      <h4 class="alert-heading"><i class="bi bi-x-circle-fill"></i> ไม่สามารถแก้ไขได้</h4>';
    echo '      <p>ใบสั่งซื้อหมายเลข <strong>' . htmlspecialchars($poNo) . '</strong> ได้ถูกยกเลิกไปแล้ว จึงไม่สามารถทำการแก้ไขได้</p>';
    echo '      <hr>';
    echo '      <a href="po_list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> กลับสู่หน้ารายการ</a>';
    echo '  </div>';
    echo '</div>';
    include_once __DIR__ . '/footer.php'; // Include footer
    echo '</body></html>';
    $conn->close();
    exit; // Stop script execution immediately
}


// 5. ดึงรายการสินค้าทั้งหมดของ PO นี้ (จะทำงานก็ต่อเมื่อ PO ไม่ได้ถูกยกเลิก)
$stmt_items = $conn->prepare(
    "SELECT 
        pi.item_id, pi.prod_id, pi.qty, pi.price,
        pi.delivery_date, pi.actual_delivery_date,
        p.prod_code, CONCAT(p.thickness,'×',p.width,'×',p.length) AS size
     FROM po_items pi
     JOIN product_list p ON pi.prod_id = p.prod_id
     WHERE pi.po_id = ?"
);
$stmt_items->bind_param('i', $header['po_id']);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit PO: <?= htmlspecialchars($poNo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style> body { background-color: #f8f9fa; } .card { border: none; } .form-control-plaintext { padding-left: 0; padding-right: 0; } </style>
</head>
<body>
<?php include_once __DIR__ . '/navbar.php'; ?>
<div class="container my-5">
    <div class="card shadow-lg">
        <div class="card-header bg-warning bg-gradient text-dark"><h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Purchase Order: <?= htmlspecialchars($poNo) ?></h4></div>
        <div class="card-body p-4">
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
            <?php endif; ?>

            <form id="edit-po-form" method="post" action="edit_po.php">
                <input type="hidden" name="po_no" value="<?= htmlspecialchars($poNo) ?>">
                <fieldset class="border p-3 rounded mb-4">
                    <legend class="float-none w-auto px-2 h6">Header Information</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="po_date" class="form-label">PO Date</label>
                            <input type="text" id="po_date" name="po_date" class="form-control" value="<?= htmlspecialchars($header['po_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($header['cus_name']) ?>" readonly disabled>
                        </div>
                    </div>
                </fieldset>
                
                <fieldset class="border p-3 rounded mb-4">
                    <legend class="float-none w-auto px-2 h6">Product Items</legend>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:35%;">Product</th>
                                    <th style="width:10%;">Qty</th>
                                    <th style="width:15%;">Unit Price</th>
                                    <th style="width:15%;">Delivery Date</th>
                                    <th style="width:15%;">Actual Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $i => $item): ?>
                                <tr>
                                    <td class="text-center"><?= $i + 1 ?></td>
                                    <td>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($item['prod_code'].' - '.$item['size'], ENT_QUOTES) ?>" readonly>
                                        <input type="hidden" name="items[<?= $i ?>][item_id]" value="<?= $item['item_id'] ?>">
                                    </td>
                                    <td><input type="number" name="items[<?= $i ?>][qty]" class="form-control text-end" min="1" value="<?= $item['qty'] ?>" required></td>
                                    <td><input type="text" class="form-control-plaintext text-end" value="<?= number_format($item['price'], 2) ?>" readonly></td>
                                    <td><input type="text" name="items[<?= $i ?>][delivery_date]" class="form-control date-picker" value="<?= htmlspecialchars($item['delivery_date']) ?>" required></td>
                                    <td><input type="text" name="items[<?= $i ?>][actual_delivery_date]" class="form-control date-picker" value="<?= htmlspecialchars($item['actual_delivery_date']) ?>"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>
                <div class="d-flex justify-content-end border-top pt-3">
                    <a href="po_list.php" class="btn btn-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                    <button type="button" id="submit-btn" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="confirmationModalLabel">Confirm Update</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Are you sure you want to save the changes to this Purchase Order?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" id="confirm-update-btn" class="btn btn-primary">Yes, Update</button></div></div></div></div>

<?php include_once __DIR__ . '/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
$(function(){
    $('.date-picker, #po_date').daterangepicker({ singleDatePicker: true, showDropdowns: true, autoApply: true, locale: { format: 'YYYY-MM-DD' } });
    $('#submit-btn').on('click', function() { const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal')); confirmationModal.show(); });
    $('#confirm-update-btn').on('click', function() { $('#edit-po-form').submit(); });
});
</script>
</body>
</html>
