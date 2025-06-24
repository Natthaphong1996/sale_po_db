<?php
// Language: PHP
// File: prod_type_list.php

// --- START: PHP Logic Section (MUST be before ANY HTML output) ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/ellipsis_pagination.php'; // Pagination helper

// Search and Filter Logic
if (isset($_GET['clear_search'])) {
    unset($_SESSION['prod_type_search_type']);
    header('Location: prod_type_list.php');
    exit;
}
$searchType = $_SESSION['prod_type_search_type'] = trim($_GET['search_type'] ?? $_SESSION['prod_type_search_type'] ?? '');

// Pagination Logic
$perPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Dynamic Query Building
$whereSql = ''; $params = []; $types = '';
if ($searchType !== '') {
    $whereSql = 'WHERE type_name LIKE ?';
    $params[] = "%{$searchType}%";
    $types .= 's';
}

// Count total rows
$countSql = "SELECT COUNT(*) FROM prod_type_list $whereSql";
$stmtCount = $conn->prepare($countSql);
if ($whereSql) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_row()[0];
$totalPages = (int) ceil($totalRows / $perPage);
$stmtCount->close();

// Fetch data for the current page
$dataSql = "SELECT type_id, type_name, status FROM prod_type_list $whereSql ORDER BY type_name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($dataSql);
$currentParams = $params;
$currentTypes = $types . 'ii';
$currentParams[] = $perPage;
$currentParams[] = $offset;
if ($whereSql) {
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
    <title>Product Type List</title>
    <!-- Section: CSS Libraries (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/navbar.php'; ?>

<div class="container my-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary bg-gradient text-white">
            <h4 class="mb-0"><i class="bi bi-tags-fill me-2"></i>Product Type Management</h4>
        </div>
        <div class="card-body p-4">
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Search and Add Section -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <form class="d-flex" method="get" action="prod_type_list.php">
                    <input type="text" name="search_type" class="form-control me-2" placeholder="Search by type name..." value="<?= htmlspecialchars($searchType, ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i></button>
                    <a href="?clear_search=1" class="btn btn-outline-secondary" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                </form>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#typeModal" data-action="add">
                    <i class="bi bi-plus-circle me-1"></i> Add Product Type
                </button>
            </div>

            <!-- Product Types Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 60%;">Type Name</th>
                            <th style="width: 20%;">Status</th>
                            <th style="width: 20%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted">No product types found.</td></tr>
                        <?php else: while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['type_name'], ENT_QUOTES) ?></td>
                                <td class="text-center"><span class="badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#typeModal" data-action="edit" data-id="<?= $row['type_id'] ?>" data-name="<?= htmlspecialchars($row['type_name'], ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil-fill"></i> Edit
                                        </button>
                                        <button type="button" class="btn <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" data-bs-toggle="modal" data-bs-target="#statusChangeModal" data-id="<?= $row['type_id'] ?>" data-name="<?= htmlspecialchars($row['type_name'], ENT_QUOTES) ?>" data-action="<?= $row['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                            <i class="bi <?= $row['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i> <?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav class="mt-4">
            <?php
                if ($totalPages > 1) {
                    renderEllipsisPagination(['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => basename(__FILE__), 'extraParams' => ['search_type' => $searchType]]);
                }
            ?>
            </nav>
        </div>
    </div>
</div>

<!-- Add/Edit Product Type Modal -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="typeForm" method="post">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="typeModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="type_id" id="type_id">
            <label for="type_name" class="form-label">Type Name</label>
            <input type="text" class="form-control" id="type_name" name="type_name" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </div>
    </form>
  </div>
</div>

<!-- Status Change Confirmation Modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="statusModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="statusChangeModalBody"></div>
            <div class="modal-footer">
                <form id="statusChangeForm" method="get">
                    <input type="hidden" name="type_id" id="statusChangeTypeId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="statusChangeConfirmBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Add/Edit Modal Logic ---
    const typeModal = document.getElementById('typeModal');
    if (typeModal) {
        typeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const action = button.getAttribute('data-action');
            const form = document.getElementById('typeForm');
            const modalTitle = typeModal.querySelector('.modal-title');
            
            if (action === 'add') {
                modalTitle.textContent = 'Add New Product Type';
                form.action = 'add_type.php';
                form.reset();
            } else { // edit
                modalTitle.textContent = 'Edit Product Type';
                form.action = 'update_type.php';
                const typeId = button.getAttribute('data-id');
                const typeName = button.getAttribute('data-name');
                typeModal.querySelector('#type_id').value = typeId;
                typeModal.querySelector('#type_name').value = typeName;
            }
        });
    }

    // --- Status Change Modal Logic ---
    const statusChangeModal = document.getElementById('statusChangeModal');
    if (statusChangeModal) {
        statusChangeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const typeId = button.getAttribute('data-id');
            const typeName = button.getAttribute('data-name');
            const action = button.getAttribute('data-action');

            const modalTitle = document.getElementById('statusModalLabel');
            const modalBody = document.getElementById('statusChangeModalBody');
            const form = document.getElementById('statusChangeForm');
            const confirmBtn = document.getElementById('statusChangeConfirmBtn');
            
            document.getElementById('statusChangeTypeId').value = typeId;

            if (action === 'deactivate') {
                modalTitle.textContent = 'Confirm Deactivation';
                modalBody.innerHTML = `Are you sure you want to <strong>deactivate</strong> the type "<strong>${typeName}</strong>"?`;
                form.action = 'deactivate_type.php';
                confirmBtn.className = 'btn btn-danger';
                confirmBtn.textContent = 'Yes, Deactivate';
            } else { // activate
                modalTitle.textContent = 'Confirm Activation';
                modalBody.innerHTML = `Are you sure you want to <strong>activate</strong> the type "<strong>${typeName}</strong>"?`;
                form.action = 'activate_type.php';
                confirmBtn.className = 'btn btn-success';
                confirmBtn.textContent = 'Yes, Activate';
            }
        });
    }
});
</script>

<?php
$conn->close();
include_once __DIR__ . '/footer.php';
?>
