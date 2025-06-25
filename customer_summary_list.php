<?php
// Language: PHP
// File: customer_summary_list.php

// 1. INITIALIZATION
// =================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Bangkok');
include_once __DIR__ . '/config_db.php';
include_once __DIR__ . '/ellipsis_pagination.php';

// 2. FILTER & PAGINATION LOGIC
// =================================
// --- Date Range Filter ---
$default_start_date = date('Y-01-01');
$default_end_date = date('Y-m-d');
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// --- Search Filter ---
if (isset($_GET['clear_search'])) {
    unset($_SESSION['summary_search_name']);
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?') . "?start_date=$start_date&end_date=$end_date";
    header("Location: $redirect_url");
    exit;
}
$searchName = $_SESSION['summary_search_name'] = trim($_GET['search_name'] ?? $_SESSION['summary_search_name'] ?? '');

// --- Pagination ---
$perPage = 9; // 3 cards per row, 3 rows
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;


// 3. DYNAMIC QUERY CONSTRUCTION (Cyber Security: Prepared Statements)
// =================================
$params = [];
$types = '';
$base_sql = "
    FROM customer_list cl
    LEFT JOIN po_list pl ON cl.cus_id = pl.cus_id AND pl.po_date BETWEEN ? AND ?
    LEFT JOIN po_items pi ON pl.po_id = pi.po_id
";
$params = [$start_date, $end_date];
$types = 'ss';

$whereSql = "WHERE cl.status = 'active'";
if ($searchName !== '') {
    $whereSql .= ' AND cl.cus_name LIKE ?';
    $params[] = "%{$searchName}%";
    $types .= 's';
}

// --- Count Query for Pagination ---
$countSql = "SELECT COUNT(DISTINCT cl.cus_id) as total " . $base_sql . $whereSql;
$stmtCount = $conn->prepare($countSql);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPages = (int) ceil($totalRows / $perPage);
$stmtCount->close();

// --- Main Data Query ---
$dataSql = "
    SELECT
        cl.cus_id,
        cl.cus_name,
        COALESCE(SUM(pi.qty * pi.price), 0) AS total_revenue,
        COUNT(DISTINCT pl.po_id) AS total_pos
    " . $base_sql . $whereSql . "
    GROUP BY cl.cus_id, cl.cus_name
    ORDER BY total_revenue DESC
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($dataSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Summaries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link href="custom.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<?php include_once __DIR__ . '/navbar.php'; ?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <h2 class="mb-0"><i class="bi bi-collection-fill me-2"></i>Customer Summary List</h2>
        <form id="filterForm" class="d-flex align-items-center flex-wrap gap-2" method="get">
            <!-- Date Range Picker -->
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                <input type="text" id="daterange" class="form-control" style="min-width: 240px;">
                <input type="hidden" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <!-- Search Input -->
            <div class="input-group">
                <input type="text" name="search_name" class="form-control" placeholder="Search by name..." value="<?= htmlspecialchars($searchName, ENT_QUOTES) ?>">
                <button type="submit" class="btn btn-primary" title="Search"><i class="bi bi-search"></i></button>
                <?php if(!empty($searchName)): ?>
                    <a href="?clear_search=1&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="btn btn-outline-secondary" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <?php if ($result->num_rows === 0): ?>
            <div class="col-12">
                <div class="alert alert-info text-center" role="alert">
                    No customer data found for the selected criteria.
                </div>
            </div>
        <?php else: while ($row = $result->fetch_assoc()):
            $avg_order_value = ($row['total_pos'] > 0) ? $row['total_revenue'] / $row['total_pos'] : 0;
        ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm kpi-card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0 text-truncate" title="<?= htmlspecialchars($row['cus_name']) ?>">
                            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($row['cus_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-7"><i class="bi bi-cash-coin text-success"></i> ยอดขายรวม</dt>
                            <dd class="col-5 text-end fw-bold">฿<?= number_format($row['total_revenue'], 2) ?></dd>
                            
                            <dt class="col-7"><i class="bi bi-receipt text-primary"></i> จำนวนใบสั่งซื้อ</dt>
                            <dd class="col-5 text-end fw-bold"><?= number_format($row['total_pos']) ?></dd>

                            <dt class="col-7"><i class="bi bi-calculator text-info"></i> ยอดสั่งซื้อเฉลี่ย</dt>
                            <dd class="col-5 text-end fw-bold">฿<?= number_format($avg_order_value, 2) ?></dd>
                        </dl>
                    </div>
                    <div class="card-footer text-center">
                        <a href="customer_summary.php?cus_id=<?= $row['cus_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-bar-chart-line-fill me-1"></i> ดูรายละเอียดเชิงลึก
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- Pagination Controls -->
    <nav class="mt-4 d-flex justify-content-center">
        <?php
        if ($totalPages > 1) {
            renderEllipsisPagination([
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'baseUrl' => basename(__FILE__),
                'extraParams' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'search_name' => $searchName
                ]
            ]);
        }
        ?>
    </nav>
</main>

<?php include_once __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
$(function() {
    // --- Date Range Picker Initialization ---
    const start = moment('<?= htmlspecialchars($start_date, ENT_QUOTES) ?>');
    const end = moment('<?= htmlspecialchars($end_date, ENT_QUOTES) ?>');

    $('#daterange').daterangepicker({
        startDate: start,
        endDate: end,
        locale: { format: 'YYYY-MM-DD' },
        ranges: {
           'Today': [moment(), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'This Year': [moment().startOf('year'), moment().endOf('year')],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
        }
    });

    // When a new date range is applied, update hidden fields and submit the form
    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
        $('#filterForm').submit();
    });
});
</script>

</body>
</html>
<?php
// --- Close database connections ---
if (isset($stmt)) {
    // $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>
