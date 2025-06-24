<?php
// Language: PHP
// File: customers_list.php

// --- START: PHP Logic Section ---
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/ellipsis_pagination.php';

// Search and Filter Logic
if (isset($_GET['clear_search'])) {
    unset($_SESSION['customer_search_name']);
    header('Location: customers_list.php');
    exit;
}
$searchName = $_SESSION['customer_search_name'] = trim($_GET['search_name'] ?? $_SESSION['customer_search_name'] ?? '');

// Pagination Logic
$perPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Dynamic Query
$whereSql = ''; $params = []; $types = '';
if ($searchName !== '') {
    $whereSql = 'WHERE cus_name LIKE ?';
    $params[] = "%{$searchName}%";
    $types .= 's';
}
$countSql = "SELECT COUNT(*) FROM customer_list $whereSql";
$stmtCount = $conn->prepare($countSql);
if ($whereSql !== '') $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_row()[0];
$totalPages = (int) ceil($totalRows / $perPage);
$stmtCount->close();

$dataSql = "SELECT cus_id, cus_name, status FROM customer_list $whereSql ORDER BY cus_name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($dataSql);
$currentParams = $params; $currentTypes = $types . 'ii';
$currentParams[] = $perPage; $currentParams[] = $offset;
if ($whereSql !== '') {
    $stmt->bind_param($currentTypes, ...$currentParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
// --- END: PHP Logic Section ---
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style> body { background-color: #f8f9fa; } .card { border: none; } </style>
</head>
<body>

<?php include_once __DIR__ . '/navbar.php'; ?>

<div class="container my-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary bg-gradient text-white"><h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>Customer Management</h4></div>
        <div class="card-body p-4">
            
            <?php if (isset($_SESSION['flash_success'])): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (isset($_SESSION['flash_error'])): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <form class="d-flex" method="get" action="customers_list.php">
                    <input type="text" name="search_name" class="form-control me-2" placeholder="Search by name..." value="<?= htmlspecialchars($searchName, ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i></button>
                    <a href="?clear_search=1" class="btn btn-outline-secondary" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                </form>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#customerModal" data-action="add"><i class="bi bi-plus-circle me-1"></i> Add Customer</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light text-center"><tr><th style="width: 50%;">Name</th><th style="width: 20%;">Status</th><th style="width: 30%;">Actions</th></tr></thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted">No customers found.</td></tr>
                        <?php else: while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['cus_name']) ?></td>
                                <td class="text-center"><span class="badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td class="text-center"><div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#customerModal" data-action="update" data-id="<?= $row['cus_id'] ?>" data-name="<?= htmlspecialchars($row['cus_name']) ?>"><i class="bi bi-pencil-fill"></i> Edit</button>
                                    <button type="button" class="btn <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> status-change-btn" data-bs-toggle="modal" data-bs-target="#statusChangeModal" data-id="<?= $row['cus_id'] ?>" data-name="<?= htmlspecialchars($row['cus_name']) ?>" data-action="<?= $row['status'] === 'active' ? 'deactivate' : 'activate' ?>"><i class="bi <?= $row['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i> <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </div></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <nav class="mt-4">
            <?php if ($totalPages > 1) renderEllipsisPagination(['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => basename(__FILE__), 'extraParams' => ['search_name' => $searchName]]); ?>
            </nav>
        </div>
    </div>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
  <div class="modal-dialog"><form id="customerForm" method="post" action="customer_actions.php">
    <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="customerModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="action" id="customerAction">
            <input type="hidden" name="cus_id" id="cus_id">
            <label for="cus_name" class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="cus_name" name="cus_name" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </div>
  </form></div>
</div>

<!-- Status Change Confirmation Modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="statusChangeModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="statusChangeModalBody"></div>
        <div class="modal-footer">
            <a href="#" id="statusChangeConfirmLink" class="btn">Confirm</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerModal = document.getElementById('customerModal');
    customerModal.addEventListener('show.bs.modal', function(e) {
        const action = e.relatedTarget.getAttribute('data-action');
        const form = document.getElementById('customerForm');
        document.getElementById('customerAction').value = action;
        if (action === 'update') {
            document.getElementById('customerModalLabel').textContent = 'Edit Customer';
            document.getElementById('cus_id').value = e.relatedTarget.getAttribute('data-id');
            document.getElementById('cus_name').value = e.relatedTarget.getAttribute('data-name');
        } else {
            document.getElementById('customerModalLabel').textContent = 'Add New Customer';
            form.reset();
        }
    });

    const statusChangeModal = document.getElementById('statusChangeModal');
    statusChangeModal.addEventListener('show.bs.modal', function(e) {
        const action = e.relatedTarget.getAttribute('data-action');
        const id = e.relatedTarget.getAttribute('data-id');
        const name = e.relatedTarget.getAttribute('data-name');
        const link = document.getElementById('statusChangeConfirmLink');
        const body = document.getElementById('statusChangeModalBody');
        document.getElementById('statusChangeModalLabel').textContent = `Confirm ${action.charAt(0).toUpperCase() + action.slice(1)}`;
        body.innerHTML = `Are you sure you want to <strong>${action}</strong> the customer "<strong>${name}</strong>"?`;
        link.href = `customer_actions.php?action=${action}&cus_id=${id}`;
        link.className = `btn ${action === 'deactivate' ? 'btn-danger' : 'btn-success'}`;
        link.textContent = `Yes, ${action.charAt(0).toUpperCase() + action.slice(1)}`;
    });
});
</script>

<?php
$conn->close();
include_once __DIR__ . '/footer.php';
?>
