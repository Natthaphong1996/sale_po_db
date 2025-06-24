<?php
// Language: PHP
// File: sale_summary_view.php

// --- START: PHP Logic Section ---
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Bangkok');

include_once __DIR__ . '/config_db.php';

// --- Date Range Filter Logic ---
$default_start_date = date('Y-m-01');
$default_end_date = date('Y-m-t');

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// Validate date format
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $start_date) || !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $end_date)) {
    $start_date = $default_start_date;
    $end_date = $default_end_date;
}

// --- Data Fetching ---
$kpi_sql = "
    SELECT
        COALESCE(SUM(pi.qty * pi.price), 0) AS total_revenue,
        COALESCE(SUM(pi.qty), 0) AS total_units_sold,
        COUNT(DISTINCT pl.po_id) AS total_pos
    FROM po_list pl
    JOIN po_items pi ON pl.po_id = pi.po_id
    WHERE pl.po_date BETWEEN ? AND ?
";
$stmt = $conn->prepare($kpi_sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$kpi_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// On-Time Delivery KPI
$delivery_sql = "
    SELECT
        COUNT(pi.item_id) AS total_delivered,
        SUM(CASE WHEN pi.actual_delivery_date <= pi.delivery_date THEN 1 ELSE 0 END) AS on_time_count
    FROM po_items pi
    JOIN po_list pl ON pi.po_id = pi.po_id
    WHERE pi.actual_delivery_date IS NOT NULL
      AND pl.po_date BETWEEN ? AND ?
";
$stmt = $conn->prepare($delivery_sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$delivery_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$on_time_percentage = 0;
if ($delivery_data && $delivery_data['total_delivered'] > 0) {
    $on_time_percentage = ($delivery_data['on_time_count'] / $delivery_data['total_delivered']) * 100;
}

// Top 5 Best-Selling Products
$top_products_sql = "
    SELECT p.prod_code, p.prod_desc, SUM(pi.qty * pi.price) AS revenue
    FROM po_items pi
    JOIN product_list p ON pi.prod_id = p.prod_id
    JOIN po_list pl ON pi.po_id = pl.po_id
    WHERE pl.po_date BETWEEN ? AND ?
    GROUP BY p.prod_id ORDER BY revenue DESC LIMIT 5
";
$stmt = $conn->prepare($top_products_sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Top 5 Customers
$top_customers_sql = "
    SELECT c.cus_name, SUM(pi.qty * pi.price) AS revenue
    FROM po_items pi
    JOIN po_list pl ON pi.po_id = pl.po_id
    JOIN customer_list c ON pl.cus_id = c.cus_id
    WHERE pl.po_date BETWEEN ? AND ?
    GROUP BY c.cus_id ORDER BY revenue DESC LIMIT 5
";
$stmt = $conn->prepare($top_customers_sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$top_customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Sales by Product Type (for Chart)
$type_sales_sql = "
    SELECT COALESCE(pt.type_name, 'Uncategorized') as type_name, SUM(pi.qty * pi.price) AS revenue
    FROM po_items pi
    JOIN po_list pl ON pi.po_id = pl.po_id
    JOIN product_list p ON pi.prod_id = p.prod_id
    LEFT JOIN prod_type_list pt ON p.type_id = pt.type_id
    WHERE pl.po_date BETWEEN ? AND ?
    GROUP BY p.type_id ORDER BY revenue DESC
";
$stmt = $conn->prepare($type_sales_sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$type_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$chart_labels = json_encode(array_column($type_sales, 'type_name'));
$chart_data = json_encode(array_column($type_sales, 'revenue'));

$conn->close();
// --- END: PHP Logic Section ---
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Summary</title>
    <!-- CSS Libraries (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body { background-color: #f0f2f5; }
        .kpi-card { transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); }
        main { flex-grow: 1; } /* Allows main content to grow and push footer down */
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container-fluid mt-4">
        <!-- Header and Date Filter -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h2 class="mb-0"><i class="bi bi-bar-chart-line-fill me-2"></i>Sales Dashboard</h2>
            <div class="d-flex align-items-center">
                <i class="bi bi-calendar3 me-2 fs-5"></i>
                <form id="date-filter-form" class="d-flex">
                    <input type="text" id="daterange" name="daterange" class="form-control" style="min-width: 280px;">
                    <input type="hidden" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    <input type="hidden" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </form>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row">
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-primary border-4 kpi-card"><div class="card-body"><div class="row align-items-center"><div class="col"><h6 class="text-primary text-uppercase mb-2">ยอดขายรวม (Revenue)</h6><h4 class="fw-bold mb-0">฿<?= number_format($kpi_data['total_revenue'] ?? 0, 2) ?></h4></div><div class="col-auto"><i class="bi bi-currency-dollar fs-2 text-muted"></i></div></div></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-success border-4 kpi-card"><div class="card-body"><div class="row align-items-center"><div class="col"><h6 class="text-success text-uppercase mb-2">จำนวนใบสั่งซื้อ (POs)</h6><h4 class="fw-bold mb-0"><?= number_format($kpi_data['total_pos'] ?? 0) ?></h4></div><div class="col-auto"><i class="bi bi-receipt fs-2 text-muted"></i></div></div></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-info border-4 kpi-card"><div class="card-body"><div class="row align-items-center"><div class="col"><h6 class="text-info text-uppercase mb-2">จำนวนสินค้าที่ขาย (Units)</h6><h4 class="fw-bold mb-0"><?= number_format($kpi_data['total_units_sold'] ?? 0) ?></h4></div><div class="col-auto"><i class="bi bi-box-seam fs-2 text-muted"></i></div></div></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-warning border-4 kpi-card"><div class="card-body"><div class="row align-items-center"><div class="col"><h6 class="text-warning text-uppercase mb-2">การจัดส่งตรงเวลา</h6><h4 class="fw-bold mb-0"><?= number_format($on_time_percentage, 1) ?>%</h4></div><div class="col-auto"><i class="bi bi-truck fs-2 text-muted"></i></div></div></div></div></div>
        </div>

        <!-- Charts and Top Lists -->
        <div class="row">
            <div class="col-xl-7 mb-4"><div class="card shadow-sm h-100"><div class="card-header"><h5 class="mb-0">ยอดขายตามประเภทสินค้า</h5></div><div class="card-body" style="min-height: 400px;"><canvas id="salesByTypeChart"></canvas></div></div></div>
            <div class="col-xl-5 mb-4">
                <div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0">5 อันดับสินค้าขายดี</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead><tr><th>สินค้า</th><th class="text-end">ยอดขาย</th></tr></thead><tbody><?php foreach ($top_products as $p): ?><tr><td><?= htmlspecialchars($p['prod_code']) ?><br><small class="text-muted"><?= htmlspecialchars($p['prod_desc']) ?></small></td><td class="text-end">฿<?= number_format($p['revenue'], 2) ?></td></tr><?php endforeach; if(empty($top_products)) echo '<tr><td colspan="2" class="text-center text-muted">ไม่มีข้อมูล</td></tr>'; ?></tbody></table></div></div></div>
                <div class="card shadow-sm"><div class="card-header"><h5 class="mb-0">5 อันดับลูกค้ายอดเยี่ยม</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead><tr><th>ลูกค้า</th><th class="text-end">ยอดสั่งซื้อ</th></tr></thead><tbody><?php foreach ($top_customers as $c): ?><tr><td><?= htmlspecialchars($c['cus_name']) ?></td><td class="text-end">฿<?= number_format($c['revenue'], 2) ?></td></tr><?php endforeach; if(empty($top_customers)) echo '<tr><td colspan="2" class="text-center text-muted">ไม่มีข้อมูล</td></tr>'; ?></tbody></table></div></div></div>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/footer.php'; // เพิ่ม Footer ตรงนี้ ?>
    
    <!-- JS Libraries (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    
    <script>
    $(function() {
        const start = moment('<?= htmlspecialchars($start_date) ?>');
        const end = moment('<?= htmlspecialchars($end_date) ?>');

        $('#daterange').daterangepicker({
            startDate: start, endDate: end,
            ranges: {
               'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()], 'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            $('#daterange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
        });

        $('#daterange').on('apply.daterangepicker', function(ev, picker) {
            window.location.href = window.location.pathname + '?start_date=' + picker.startDate.format('YYYY-MM-DD') + '&end_date=' + picker.endDate.format('YYYY-MM-DD');
        });

        const ctx = document.getElementById('salesByTypeChart');
        new Chart(ctx, {
            type: 'bar', data: { labels: <?= $chart_labels ?>, datasets: [{ label: 'ยอดขาย', data: <?= $chart_data ?>,
            backgroundColor: ['rgba(54, 162, 235, 0.6)','rgba(255, 99, 132, 0.6)','rgba(75, 192, 192, 0.6)','rgba(255, 206, 86, 0.6)','rgba(153, 102, 255, 0.6)','rgba(255, 159, 64, 0.6)'],
            borderColor: ['rgba(54, 162, 235, 1)','rgba(255, 99, 132, 1)','rgba(75, 192, 192, 1)','rgba(255, 206, 86, 1)','rgba(153, 102, 255, 1)','rgba(255, 159, 64, 1)'], borderWidth: 1}]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true,
            ticks: { callback: function(value) { return '฿' + value.toLocaleString(); }}}},
            plugins: { legend: { display: false }}}
        });
    });
    </script>
</body>
</html>
