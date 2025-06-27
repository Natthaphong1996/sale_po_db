<?php
// Language: PHP
// File: products_list.php (Fully Updated Version)

// --- START: PHP Logic Section (MUST be before ANY HTML output) ---

date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/ellipsis_pagination.php'; // Pagination helper

// --- Search and Filter Logic ---
if (isset($_GET['clear_search'])) {
    unset($_SESSION['product_search_code'], $_SESSION['product_search_desc']);
    header('Location: products_list.php');
    exit;
}
$searchCode = $_SESSION['product_search_code'] = trim($_GET['search_code'] ?? $_SESSION['product_search_code'] ?? '');
$searchDesc = $_SESSION['product_search_desc'] = trim($_GET['search_desc'] ?? $_SESSION['product_search_desc'] ?? '');

// --- Pagination Logic ---
$perPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;

// --- Sorting Logic ---
$sortable_columns = ['prod_code', 'customer_code', 'type_name', 'price', 'status'];
$sort_by = $_GET['sort_by'] ?? 'prod_code';
if (!in_array($sort_by, $sortable_columns)) {
    $sort_by = 'prod_code';
}
$sort_order = strtoupper($_GET['sort_order'] ?? 'ASC');
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'ASC';
}
$orderBySql = "ORDER BY {$sort_by} {$sort_order}";

// --- Dynamic Query Building ---
$where = []; $params = []; $types = '';
if ($searchCode !== '') {
    $where[] = 'p.prod_code LIKE ?';
    $params[] = "%{$searchCode}%";
    $types .= 's';
}
if ($searchDesc !== '') {
    $where[] = 'p.prod_desc LIKE ?';
    $params[] = "%{$searchDesc}%";
    $types .= 's';
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// --- Performance-Optimized Data Fetching ---
$dataSql = "
    SELECT SQL_CALC_FOUND_ROWS
           p.*, t.type_name, CONCAT(p.thickness,'×',p.width,'×',p.length) AS dimension
    FROM product_list p
    LEFT JOIN prod_type_list t ON p.type_id = t.type_id
    $whereSql 
    $orderBySql
    LIMIT ? OFFSET ?";
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
$stmt->close();

// Fetch total rows count from the previous query
$totalRows = $conn->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPages = (int) ceil($totalRows / $perPage);

// Fetch product types for modals
$product_types = $conn->query("SELECT type_id, type_name FROM prod_type_list WHERE status = 'active' ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);

// --- END: PHP Logic Section ---
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; }
        .table th { white-space: nowrap; }
        .table th a { color: inherit; text-decoration: none; }
        .table th a:hover { color: #0d6efd; }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/navbar.php'; ?>

<div class="container my-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary bg-gradient text-white">
            <h4 class="mb-0"><i class="bi bi-box-seam-fill me-2"></i>Product Management</h4>
        </div>
        <div class="card-body p-4">

            <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <form class="d-flex" method="get" action="products_list.php">
                    <input type="text" name="search_code" class="form-control me-2" placeholder="Search code..." value="<?= htmlspecialchars($searchCode, ENT_QUOTES) ?>">
                    <input type="text" name="search_desc" class="form-control me-2" placeholder="Search description..." value="<?= htmlspecialchars($searchDesc, ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i></button>
                    <a href="?clear_search=1" class="btn btn-outline-secondary" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                </form>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#productModal" data-action="add">
                    <i class="bi bi-plus-circle me-1"></i> Add Product
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <?php
                            function sort_link($column, $display_name, $current_sort, $current_order) {
                                $order = ($current_sort == $column && $current_order == 'ASC') ? 'DESC' : 'ASC';
                                $icon = '';
                                if ($current_sort == $column) {
                                    $icon = ($current_order == 'ASC') ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>';
                                }
                                $search_params = http_build_query(['search_code' => $_GET['search_code'] ?? '', 'search_desc' => $_GET['search_desc'] ?? '']);
                                return "<th><a href=\"?sort_by={$column}&sort_order={$order}&{$search_params}\">{$display_name}{$icon}</a></th>";
                            }
                            ?>
                            <?= sort_link('prod_code', 'Code', $sort_by, $sort_order) ?>
                            <?= sort_link('customer_code', 'Part No.', $sort_by, $sort_order) ?>
                            <?= sort_link('type_name', 'Type', $sort_by, $sort_order) ?>
                            <th>Description</th>
                            <th>Dimension</th>
                            <?= sort_link('price', 'Price', $sort_by, $sort_order) ?>
                            <?= sort_link('status', 'Status', $sort_by, $sort_order) ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted">No products found.</td></tr>
                        <?php else: while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['prod_code']) ?></td>
                                <td><?= htmlspecialchars($row['customer_code'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['type_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['prod_desc']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['dimension']) ?></td>
                                <td class="text-end"><?= number_format($row['price'], 2) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($row['status']) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-info" title="Detail" data-bs-toggle="modal" data-bs-target="#detailProductModal" data-id="<?= $row['prod_id'] ?>"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-warning" title="Edit" data-bs-toggle="modal" data-bs-target="#productModal" data-action="edit" data-product='<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS) ?>'><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn <?= $row['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" title="<?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" data-bs-toggle="modal" data-bs-target="#statusChangeModal" data-id="<?= $row['prod_id'] ?>" data-code="<?= htmlspecialchars($row['prod_code']) ?>" data-action="<?= $row['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                            <i class="bi <?= $row['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <nav class="mt-4">
                <?php if ($totalPages > 1) renderEllipsisPagination(['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => basename(__FILE__), 'extraParams' => ['search_code' => $searchCode, 'search_desc' => $searchDesc, 'sort_by' => $sort_by, 'sort_order' => $sort_order]]); ?>
            </nav>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="productForm" method="post">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="productModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="prod_id" id="prod_id">
                    <div class="row g-3">
                        <div class="col-md-6"><label for="prod_code" class="form-label">Product Code</label><input type="text" class="form-control" name="prod_code" id="prod_code" required></div>
                        <div class="col-md-6"><label for="customer_code" class="form-label">Part No.</label><input type="text" class="form-control" name="customer_code" id="customer_code" placeholder="Optional Part No."></div>
                        <div class="col-md-6">
                            <label for="type_id" class="form-label">Product Type</label>
                            <select name="type_id" id="type_id" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach ($product_types as $type): ?>
                                    <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label for="prod_desc" class="form-label">Description</label><textarea class="form-control" name="prod_desc" id="prod_desc" rows="2"></textarea></div>
                        <div class="col-md-4"><label for="thickness" class="form-label">Thickness</label><input type="number" class="form-control" name="thickness" id="thickness" required></div>
                        <div class="col-md-4"><label for="width" class="form-label">Width</label><input type="number" class="form-control" name="width" id="width" required></div>
                        <div class="col-md-4"><label for="length" class="form-label">Length</label><input type="number" class="form-control" name="length" id="length" required></div>
                        <div class="col-md-6"><label for="price" class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" id="price" required></div>
                        <div class="col-md-6"><label for="status" class="form-label">Status</label><select name="status" id="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </div>
        </form>
    </div>
</div>
<!-- Detail Modal -->
<div class="modal fade" id="detailProductModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="detailModalLabel">Product Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="detailContent" class="text-center p-4"><div class="spinner-border"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>

<!-- *** MAJOR CHANGE: Simplified Status Change Modal *** -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="statusModalLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="statusChangeModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="statusChangeConfirmBtn" class="btn">Confirm</a>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Add/Edit Product Modal Logic ---
    const productModal = document.getElementById('productModal');
    productModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const action = button.getAttribute('data-action');
        const form = document.getElementById('productForm');
        const modalTitle = productModal.querySelector('.modal-title');
        
        const statusCol = form.querySelector('#status').closest('.col-md-6');

        if (action === 'add') {
            modalTitle.textContent = 'Add New Product';
            form.action = 'add_product.php';
            form.reset();
            form.querySelector('#prod_id').value = '';
            statusCol.style.display = 'none';
        } else { // edit
            modalTitle.textContent = 'Edit Product';
            form.action = 'update_product.php';
            const product = JSON.parse(button.getAttribute('data-product'));
            
            form.querySelector('#prod_id').value = product.prod_id;
            form.querySelector('#prod_code').value = product.prod_code;
            form.querySelector('#customer_code').value = product.customer_code || '';
            form.querySelector('#type_id').value = product.type_id;
            form.querySelector('#prod_desc').value = product.prod_desc;
            form.querySelector('#thickness').value = product.thickness;
            form.querySelector('#width').value = product.width;
            form.querySelector('#length').value = product.length;
            form.querySelector('#price').value = product.price;
            form.querySelector('#status').value = product.status;
            statusCol.style.display = 'block';
        }
    });

    // --- Status Change Modal Logic (Simplified) ---
    const statusChangeModal = document.getElementById('statusChangeModal');
    statusChangeModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const prodId = button.getAttribute('data-id');
        const prodCode = button.getAttribute('data-code');
        const action = button.getAttribute('data-action');
        
        const confirmBtn = document.getElementById('statusChangeConfirmBtn');
        const modalTitle = document.getElementById('statusModalLabel');
        const modalBody = document.getElementById('statusChangeModalBody');

        let targetFile, modalText, btnClass, btnText;

        if (action === 'deactivate') {
            targetFile = 'deactivate_product.php';
            modalTitle.textContent = 'Confirm Deactivation';
            modalText = `Are you sure you want to deactivate product "<strong>${prodCode}</strong>"?`;
            btnClass = 'btn-danger';
            btnText = 'Yes, Deactivate';
        } else {
            targetFile = 'activate_product.php';
            modalTitle.textContent = 'Confirm Activation';
            modalText = `Are you sure you want to activate product "<strong>${prodCode}</strong>"?`;
            btnClass = 'btn-success';
            btnText = 'Yes, Activate';
        }

        modalBody.innerHTML = modalText;
        confirmBtn.href = `${targetFile}?prod_id=${prodId}`;
        confirmBtn.className = `btn ${btnClass}`;
        confirmBtn.textContent = btnText;
    });

    // --- Detail Modal AJAX Logic ---
    const detailModal = document.getElementById('detailProductModal');
    detailModal.addEventListener('show.bs.modal', function(event) {
        const prodId = event.relatedTarget.getAttribute('data-id');
        const container = document.getElementById('detailContent');
        container.innerHTML = '<div class="d-flex justify-content-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        fetch(`product_detail_ajax.php?prod_id=${prodId}`)
            .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
            .then(data => {
                let historyHtml = '<p class="text-muted text-center">No price history found.</p>';
                if (data.history && data.history.length > 0) {
                    historyHtml = '<table class="table table-sm table-bordered table-striped"><thead><tr class="text-center"><th>From</th><th>To</th><th>Date</th><th>User</th></tr></thead><tbody>';
                    data.history.forEach(h => {
                        const userDisplay = h.thainame || (h.user_id ? `ID: ${h.user_id}` : '-');
                        const changeDate = new Date(h.change_date).toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                        historyHtml += `<tr><td class="text-end">${parseFloat(h.change_from).toFixed(2)}</td><td class="text-end">${parseFloat(h.change_to).toFixed(2)}</td><td class="text-center">${changeDate}</td><td>${userDisplay}</td></tr>`;
                    });
                    historyHtml += '</tbody></table>';
                }
                const escape = s => s === null || s === undefined ? '' : String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
                container.innerHTML = `<div class="text-start"><h5>${escape(data.prod_code)}</h5><div class="row"><div class="col-sm-6"><p class="mb-2"><strong>Part No.:</strong> ${escape(data.customer_code) || '-'}</p><p class="mb-2"><strong>Type:</strong> ${escape(data.type_name) || '-'}</p></div><div class="col-sm-6"><p class="mb-2"><strong>Dimension:</strong> ${escape(data.dimension)}</p><p class="mb-2"><strong>Current Price:</strong> ${parseFloat(data.price).toFixed(2)}</p></div></div><p class="mb-3"><strong>Description:</strong> ${escape(data.prod_desc) || '-'}</p><hr><h6><i class="bi bi-clock-history me-2"></i>Price History</h6>${historyHtml}</div>`;
            })
            .catch(err => { container.innerHTML = `<p class="text-danger fw-bold text-center p-4">${err.message || 'Error loading details. Please try again.'}</p>`; });
    });
});
</script>

<?php
$conn->close();
include_once __DIR__ . '/footer.php';
?>
