<?php
// Language: PHP
// File: po_detail.php

// กำหนดโซนเวลาและเริ่มต้น Session
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. รวมไฟล์ที่จำเป็น
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/navbar.php';

// 2. ตรวจสอบและรับค่า po_no จาก GET parameter
$poNo = $_GET['po_no'] ?? null;
if (empty($poNo)) {
    $_SESSION['flash_error'] = 'ไม่พบหมายเลขใบสั่งซื้อ (PO No. is missing).';
    header('Location: po_list.php');
    exit;
}
$poNo = trim($poNo);

// 3. ดึงข้อมูลส่วนหัวของ PO และชื่อลูกค้า
$stmtHeader = $conn->prepare(
    "SELECT pl.po_id, pl.po_no, pl.po_date, c.cus_name
     FROM po_list pl
     JOIN customer_list c ON pl.cus_id = c.cus_id
     WHERE pl.po_no = ? LIMIT 1"
);
$stmtHeader->bind_param('s', $poNo);
$stmtHeader->execute();
$headerResult = $stmtHeader->get_result();
if ($headerResult->num_rows === 0) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูลใบสั่งซื้อ (PO not found).';
    header('Location: po_list.php');
    exit;
}
$header = $headerResult->fetch_assoc();
$stmtHeader->close();

// 4. ดึงข้อมูลรายการสินค้าทั้งหมดของ PO นี้
$stmtItems = $conn->prepare(
    "SELECT 
        pi.item_id, pr.prod_code, pr.prod_desc,
        CONCAT(pr.thickness, '×', pr.width, '×', pr.length) AS size,
        ptl.type_name, pi.qty, pi.price, (pi.qty * pi.price) AS total_amount,
        pi.delivery_date, pi.actual_delivery_date
    FROM po_items pi
    JOIN product_list pr ON pi.prod_id = pr.prod_id
    LEFT JOIN prod_type_list ptl ON pr.type_id = ptl.type_id
    WHERE pi.po_id = ? ORDER BY pi.item_id ASC"
);
$stmtItems->bind_param('i', $header['po_id']);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

// คำนวณยอดรวมทั้งหมด
$grandTotal = array_sum(array_column($items, 'total_amount'));

// 5. ดึงไฟล์ PDF ที่เกี่ยวข้องทั้งหมด
$stmtPdf = $conn->prepare("SELECT path_file, create_at FROM po_pdf WHERE po_no = ? ORDER BY create_at DESC");
$stmtPdf->bind_param('s', $poNo);
$stmtPdf->execute();
$pdfFiles = $stmtPdf->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPdf->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO Detail: <?= htmlspecialchars($poNo) ?></title>

    <!-- Section: CSS Libraries (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; }
        .table th { white-space: nowrap; }
    </style>
</head>
<body>

    <div class="container my-5">
        <div class="card shadow-lg">
            <div class="card-header bg-info bg-gradient text-white">
                <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Purchase Order Detail</h4>
            </div>
            <div class="card-body p-4">
                <!-- PO Header Information -->
                <fieldset class="border p-3 rounded mb-4">
                    <legend class="float-none w-auto px-2 h6">PO #: <?= htmlspecialchars($header['po_no']) ?></legend>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Customer:</strong> <?= htmlspecialchars($header['cus_name']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>PO Date Received:</strong> <?= date('d F Y', strtotime($header['po_date'])) ?>
                        </div>
                    </div>
                </fieldset>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-start align-items-center border-top pt-3 mb-4">
                     <a href="po_list.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left"></i> Back to List</a>
                     <?php if (!empty($pdfFiles)): ?>
                        <a href="<?= htmlspecialchars($pdfFiles[0]['path_file']) ?>" target="_blank" class="btn btn-success me-2"><i class="bi bi-file-earmark-pdf"></i> View Latest PDF</a>
                     <?php endif; ?>
                     <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#historyModal"><i class="bi bi-clock-history"></i> View History</button>
                </div>


                <!-- PO Items Table -->
                <h5 class="mb-3">Product Items</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width:5%;">#</th>
                                <th style="width:15%;">Product Code</th>
                                <th style="width:25%;">Description</th>
                                <th style="width:10%;">Type</th>
                                <th style="width:5%;">Qty</th>
                                <th style="width:10%;">Unit Price</th>
                                <th style="width:10%;">Total</th>
                                <th style="width:10%;">Delivery Date</th>
                                <th style="width:10%;">Actual Date</th> <!-- เพิ่มหัวตาราง -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="9" class="text-center text-muted">No items found for this PO.</td></tr> <!-- แก้ไข colspan -->
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($item['prod_code']) ?></td>
                                    <td><?= htmlspecialchars($item['prod_desc'] . ' (' . $item['size'] . ')') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($item['type_name'] ?? '-') ?></td>
                                    <td class="text-end"><?= number_format($item['qty']) ?></td>
                                    <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($item['total_amount'], 2) ?></td>
                                    <td class="text-center"><?= $item['delivery_date'] ? date('d-m-Y', strtotime($item['delivery_date'])) : '-' ?></td>
                                    <td class="text-center"><?= $item['actual_delivery_date'] ? date('d-m-Y', strtotime($item['actual_delivery_date'])) : '-' ?></td> <!-- เพิ่มข้อมูล -->
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="6" class="text-end fw-bold">Grand Total (฿)</td>
                                <td class="text-end fw-bolder fs-5"><?= number_format($grandTotal, 2) ?></td>
                                <td colspan="2"></td> <!-- แก้ไข colspan -->
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Other PDFs Section -->
                <?php if (count($pdfFiles) > 1): ?>
                <div class="mt-4">
                    <h6>Other Related PDF Files:</h6>
                    <ul class="list-group">
                        <?php for ($i = 1; $i < count($pdfFiles); $i++): ?>
                        <li class="list-group-item">
                            <a href="<?= htmlspecialchars($pdfFiles[$i]['path_file']) ?>" target="_blank">
                                <i class="bi bi-file-earmark-zip-fill me-2"></i> Uploaded on: <?= date('d F Y, H:i:s', strtotime($pdfFiles[$i]['create_at'])) ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">ประวัติการแก้ไขสำหรับ PO: <?= htmlspecialchars($poNo) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="historyModalBody">
                    <div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const historyModal = document.getElementById('historyModal');
        if (!historyModal) return;

        historyModal.addEventListener('show.bs.modal', function (event) {
            const modalBody = document.getElementById('historyModalBody');
            const poNo = '<?= addslashes(htmlspecialchars($poNo, ENT_QUOTES, 'UTF-8')) ?>';
            
            modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading history...</p></div>';
            
            fetch(`ajax_get_po_history.php?po_no=${encodeURIComponent(poNo)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`Network response was not ok, status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    let html = '';
                    if (data.length === 0) {
                        html = '<div class="alert alert-info text-center">ไม่พบประวัติการแก้ไขสำหรับใบสั่งซื้อนี้</div>';
                    } else {
                        html = `<div class="table-responsive"><table class="table table-sm table-bordered table-hover">
                                    <thead class="table-light"><tr><th>วัน-เวลาที่แก้ไข</th><th>รหัสสินค้า</th><th>ผู้แก้ไข</th><th>รายละเอียดการเปลี่ยนแปลง</th></tr></thead>
                                    <tbody>`;

                        data.forEach(record => {
                            let changesList = '<ul class="mb-0 ps-3">';
                            try {
                                const changedData = JSON.parse(record.changed_data);
                                if (changedData && typeof changedData === 'object' && Object.keys(changedData).length > 0) {
                                    for (const key in changedData) {
                                        const changeValue = changedData[key];
                                        const fieldName = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                        if (typeof changeValue === 'object' && changeValue !== null) {
                                            if ('from' in changeValue && 'to' in changeValue) {
                                                changesList += `<li><strong>${fieldName}:</strong> เปลี่ยนจาก "<em>${changeValue.from}</em>" เป็น "<em>${changeValue.to}</em>"</li>`;
                                            } else if ('old' in changeValue && 'new' in changeValue) {
                                                changesList += `<li><strong>${fieldName}:</strong> เปลี่ยนจาก "<em>${changeValue.old}</em>" เป็น "<em>${changeValue.new}</em>"</li>`;
                                            } else {
                                                changesList += `<li><strong>${fieldName}:</strong> <span class="badge bg-secondary">Updated to</span> <code>${JSON.stringify(changeValue)}</code></li>`;
                                            }
                                        } else {
                                            changesList += `<li><strong>${fieldName}:</strong> <span class="badge bg-secondary">Updated to</span> <code>${JSON.stringify(changeValue)}</code></li>`;
                                        }
                                    }
                                } else {
                                     changesList += '<li>ไม่มีข้อมูลการเปลี่ยนแปลง</li>';
                                }
                            } catch (e) {
                                changesList += `<li>ข้อมูลการเปลี่ยนแปลงไม่ถูกต้อง: <code>${record.changed_data}</code></li>`;
                            }
                            changesList += '</ul>';
                            
                            const changeDate = new Date(record.changed_at).toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'medium' });
                            html += `<tr><td>${changeDate}</td><td>${record.prod_code || 'N/A'}</td><td>${record.changed_by || 'N/A'}</td><td>${changesList}</td></tr>`;
                        });

                        html += `</tbody></table></div>`;
                    }
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    modalBody.innerHTML = `<div class="alert alert-danger"><strong>เกิดข้อผิดพลาด:</strong> ไม่สามารถโหลดข้อมูลประวัติได้<br><small>${error.message}</small></div>`;
                });
        });
    });
    </script>
</body>
</html>
