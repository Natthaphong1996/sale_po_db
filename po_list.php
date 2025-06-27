<?php
// Language: PHP
// File: po_list.php

/**
 * -------------------------------------------------------------------------
 * PHP LOGIC SECTION
 * -------------------------------------------------------------------------
 * All PHP logic, especially session handling and redirects, must be placed
 * at the very top of the file before any HTML is outputted. This prevents
 * the "headers already sent" error.
 */

// Set timezone and start session if not already started.
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Search Clear Redirect Logic ---
if (isset($_GET['clear_search'])) {
    unset(
        $_SESSION['po_list_search_po'],
        $_SESSION['po_list_search_customer'],
        $_SESSION['po_list_search_size'],
        $_SESSION['po_list_daterange']
    );
    header('Location: po_list.php');
    exit;
}

// --- Include necessary files ---
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/ellipsis_pagination.php';

// --- Helper function for search parameters ---
function get_search_param($key, $session_key, $default = '') {
    if (isset($_GET[$key])) {
        $_SESSION[$session_key] = trim($_GET[$key]);
    }
    return $_SESSION[$session_key] ?? $default;
}

// --- Retrieve all search parameters ---
$searchPo = get_search_param('search_po', 'po_list_search_po');
$searchCustomer = get_search_param('search_customer', 'po_list_search_customer');
$searchSize = get_search_param('search_size', 'po_list_search_size');
$searchDate = get_search_param('daterange', 'po_list_daterange', date('Y-m-01') . ' - ' . date('Y-m-t'));

// --- Pagination Logic ---
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// --- Dynamic SQL Query Building ---
$whereClauses = [];
$params = [];
$types = '';

if ($searchPo !== '') { $whereClauses[] = 'p.po_no LIKE ?'; $params[] = "%{$searchPo}%"; $types .= 's'; }
if ($searchCustomer !== '') { $whereClauses[] = 'c.cus_name LIKE ?'; $params[] = "%{$searchCustomer}%"; $types .= 's'; }
if ($searchDate !== '' && strpos($searchDate, ' - ') !== false) {
    list($startDate, $endDate) = explode(' - ', $searchDate, 2);
    $whereClauses[] = 'p.created_at BETWEEN ? AND ?';
    $params[] = $startDate . ' 00:00:00';
    $params[] = $endDate . ' 23:59:59';
    $types .= 'ss';
}
if ($searchSize !== '') {
    $whereClauses[] = "p.po_id IN (SELECT pi.po_id FROM po_items pi JOIN product_list pr ON pi.prod_id = pr.prod_id WHERE CONCAT(pr.thickness,'×',pr.width,'×',pr.length) LIKE ?)";
    $params[] = "%{$searchSize}%";
    $types .= 's';
}
$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

// --- Data Fetching ---
// 1. Count total items for pagination.
$countSql = "SELECT COUNT(DISTINCT p.po_id) FROM po_list p JOIN customer_list c ON p.cus_id = c.cus_id {$whereSql}";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$totalItems = $countStmt->get_result()->fetch_row()[0];
$totalPages = (int) ceil($totalItems / $itemsPerPage);
$countStmt->close();

// 2. Fetch data for the current page, including the 'status' column.
$dataSql = "SELECT DISTINCT p.po_no, c.cus_name, p.created_at, p.status FROM po_list p JOIN customer_list c ON p.cus_id = c.cus_id {$whereSql} ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$dataStmt = $conn->prepare($dataSql);
$currentParams = $params;
$currentTypes = $types . 'ii';
$currentParams[] = $itemsPerPage;
$currentParams[] = $offset;
if (!empty($whereClauses)) { $dataStmt->bind_param($currentTypes, ...$currentParams); } 
else { $dataStmt->bind_param('ii', $itemsPerPage, $offset); }
$dataStmt->execute();
$result = $dataStmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style> body { background-color: #f8f9fa; } .card { border: none; } .search-card { background-color: #f8f9fa; border: 1px solid #dee2e6; } </style>
</head>
<body>
    <?php include_once __DIR__ . '/navbar.php'; ?>
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary bg-gradient text-white"><h4 class="mb-0"><i class="bi bi-list-ul me-2"></i>Purchase Order List</h4></div>
            <div class="card-body p-4">
                
                <!-- Flash Messages for user feedback -->
                <?php if (isset($_SESSION['flash_success'])): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if (isset($_SESSION['flash_error'])): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <!-- Search Form -->
                <div class="card search-card mb-4"><div class="card-body"><form class="row g-3 align-items-end" method="get"><div class="col-md-3"><label for="search_po" class="form-label">PO No.</label><input type="text" id="search_po" name="search_po" class="form-control" placeholder="Search PO No." value="<?= htmlspecialchars($searchPo, ENT_QUOTES) ?>"></div><div class="col-md-3"><label for="search_customer" class="form-label">Customer</label><input type="text" id="search_customer" name="search_customer" class="form-control" placeholder="Search Customer" value="<?= htmlspecialchars($searchCustomer, ENT_QUOTES) ?>"></div><div class="col-md-2"><label for="search_size" class="form-label">Size (T×W×L)</label><input type="text" id="search_size" name="search_size" class="form-control" placeholder="e.g., 10×20×30" value="<?= htmlspecialchars($searchSize, ENT_QUOTES) ?>"></div><div class="col-md-2"><label for="daterange" class="form-label">Register Date</label><input type="text" id="daterange" name="daterange" class="form-control" value="<?= htmlspecialchars($searchDate, ENT_QUOTES) ?>"></div><div class="col-md-2 d-flex"><button type="submit" class="btn btn-primary w-100 me-2"><i class="bi bi-search"></i> Search</button><a href="?clear_search=1" class="btn btn-outline-secondary" title="Clear Search"><i class="bi bi-x-lg"></i></a></div></form></div></div>
                <div class="d-flex justify-content-end mb-3"><a href="register_new_po_view.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Register New PO</a></div>

                <!-- Results Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center"><tr><th>PO No.</th><th>Customer</th><th>Date Registered</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($row['po_no'], ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($row['cus_name'], ENT_QUOTES) ?></td>
                                    <td class="text-center"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                                    <td class="text-center">
                                        <?php
                                            $status = htmlspecialchars($row['status'] ?? 'unknown', ENT_QUOTES);
                                            $badge_class = 'bg-dark'; // Default badge color
                                            if ($status === 'active') { $badge_class = 'bg-success'; } 
                                            elseif ($status === 'completed') { $badge_class = 'bg-primary'; } 
                                            elseif ($status === 'deactivated') { $badge_class = 'bg-secondary'; }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="po_detail.php?po_no=<?= urlencode($row['po_no']) ?>" class="btn btn-info" title="Detail"><i class="bi bi-eye"></i></a>
                                            <a href="edit_po_view.php?po_no=<?= urlencode($row['po_no']) ?>" class="btn btn-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                            
                                            <!-- Conditionally show Cancel button -->
                                            <?php if ($row['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-secondary cancel-btn" data-bs-toggle="modal" data-bs-target="#cancelConfirmationModal" data-po-no="<?= htmlspecialchars($row['po_no'], ENT_QUOTES) ?>" title="ยกเลิก PO">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal" data-po-no="<?= htmlspecialchars($row['po_no'], ENT_QUOTES) ?>" title="ลบ">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No purchase orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav class="mt-4">
                <?php if ($totalPages > 1) { renderEllipsisPagination(['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => basename(__FILE__), 'extraParams' => ['search_po' => $searchPo, 'search_customer' => $searchCustomer, 'search_size' => $searchSize, 'daterange' => $searchDate], ]); } ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelConfirmationModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="cancelModalLabel"><i class="bi bi-exclamation-circle-fill text-warning me-2"></i>ยืนยันการยกเลิก</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">คุณแน่ใจหรือไม่ว่าต้องการยกเลิกใบสั่งซื้อ #<strong id="po-no-to-cancel"></strong>? การดำเนินการนี้จะเปลี่ยนสถานะเป็น 'Deactivated'</div>
                <div class="modal-footer">
                    <form id="cancel-form" action="deactivate_po.php" method="post">
                        <input type="hidden" name="po_no" id="po-no-in-cancel-form">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-warning">ใช่, ยกเลิก PO</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>ยืนยันการลบ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">คุณแน่ใจหรือไม่ว่าต้องการลบใบสั่งซื้อ #<strong id="po-no-to-delete"></strong>? การดำเนินการนี้ไม่สามารถย้อนกลับได้</div>
                <div class="modal-footer">
                    <form id="delete-form" action="delete_po.php" method="post">
                        <input type="hidden" name="po_no" id="po-no-in-delete-form">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger">ใช่, ลบ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script>
    $(function() {
        // Init DateRangePicker
        $('#daterange').daterangepicker({ opens: 'left', locale: { format: 'YYYY-MM-DD' }, ranges: { 'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')], 'Last 7 Days': [moment().subtract(6, 'days'), moment()], 'Last 30 Days': [moment().subtract(29, 'days'), moment()], 'This Month': [moment().startOf('month'), moment().endOf('month')], 'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')] } });

        // Event listener for Delete Modal
        const deleteModal = document.getElementById('deleteConfirmationModal');
        if(deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const poNo = button.getAttribute('data-po-no');
                deleteModal.querySelector('#po-no-to-delete').textContent = poNo;
                deleteModal.querySelector('#po-no-in-delete-form').value = poNo;
            });
        }
        
        // Event listener for Cancel Modal
        const cancelModal = document.getElementById('cancelConfirmationModal');
        if(cancelModal) {
            cancelModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const poNo = button.getAttribute('data-po-no');
                cancelModal.querySelector('#po-no-to-cancel').textContent = poNo;
                cancelModal.querySelector('#po-no-in-cancel-form').value = poNo;
            });
        }
    });
    </script>
</body>
</html>
<?php
// Close DB connections
if(isset($dataStmt)) $dataStmt->close();
if(isset($conn)) $conn->close();
include_once __DIR__ . '/footer.php';
?>
