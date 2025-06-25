<?php
// Language: PHP
// File: customer_summary.php

// 1. SETUP & INITIALIZATION
// =================================
// ตั้งค่า Timezone และเริ่ม Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Bangkok');

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include_once __DIR__ . '/config_db.php';

// 2. INPUT HANDLING & VALIDATION
// =================================
// รับค่า cus_id จาก URL และตรวจสอบความถูกต้อง
$cus_id = isset($_GET['cus_id']) ? (int)$_GET['cus_id'] : 0;
if ($cus_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid Customer ID.';
    header('Location: customer_summary_list.php');
    exit;
}

// จัดการการกรองข้อมูลตามช่วงวันที่
// ถ้าไม่มีการกำหนด ให้แสดงข้อมูลทั้งหมดในปีปัจจุบัน
$default_start_date = date('Y-01-01');
$default_end_date = date('Y-m-d');
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// 3. DATA FETCHING & PROCESSING
// =================================
$conn->begin_transaction();
try {
    // ดึงชื่อลูกค้า
    $stmt_cus = $conn->prepare("SELECT cus_name FROM customer_list WHERE cus_id = ?");
    $stmt_cus->bind_param('i', $cus_id);
    $stmt_cus->execute();
    $customer = $stmt_cus->get_result()->fetch_assoc();
    if (!$customer) {
        throw new Exception("Customer not found.");
    }
    $customer_name = $customer['cus_name'];
    $stmt_cus->close();

    // ดึงข้อมูล KPI (Key Performance Indicators) โดยใช้ Prepared Statements
    $kpi_sql = "
        SELECT
            COALESCE(SUM(pi.qty * pi.price), 0) AS total_revenue,
            COUNT(DISTINCT pl.po_id) AS total_pos,
            COUNT(DISTINCT pi.prod_id) AS unique_products
        FROM po_list pl
        JOIN po_items pi ON pl.po_id = pi.po_id
        WHERE pl.cus_id = ? AND pl.po_date BETWEEN ? AND ?
    ";
    $stmt_kpi = $conn->prepare($kpi_sql);
    $stmt_kpi->bind_param('iss', $cus_id, $start_date, $end_date);
    $stmt_kpi->execute();
    $kpi_data = $stmt_kpi->get_result()->fetch_assoc();
    $stmt_kpi->close();
    
    // คำนวณค่าเฉลี่ยต่อ PO
    $avg_order_value = ($kpi_data['total_pos'] > 0) ? $kpi_data['total_revenue'] / $kpi_data['total_pos'] : 0;

    // ดึงข้อมูล 5 อันดับสินค้าขายดีสำหรับลูกค้ารายนี้
    $top_prod_sql = "
        SELECT p.prod_code, p.prod_desc, SUM(pi.qty * pi.price) AS revenue
        FROM po_items pi
        JOIN product_list p ON pi.prod_id = p.prod_id
        JOIN po_list pl ON pi.po_id = pl.po_id
        WHERE pl.cus_id = ? AND pl.po_date BETWEEN ? AND ?
        GROUP BY p.prod_id ORDER BY revenue DESC LIMIT 5
    ";
    $stmt_top_prod = $conn->prepare($top_prod_sql);
    $stmt_top_prod->bind_param('iss', $cus_id, $start_date, $end_date);
    $stmt_top_prod->execute();
    $top_products = $stmt_top_prod->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_top_prod->close();

    // ดึงข้อมูล 5 PO ล่าสุด
    $recent_po_sql = "
        SELECT pl.po_id, pl.po_no, pl.po_date, SUM(pi.qty * pi.price) AS total_amount
        FROM po_list pl
        JOIN po_items pi ON pl.po_id = pl.po_id
        WHERE pl.cus_id = ? AND pl.po_date BETWEEN ? AND ?
        GROUP BY pl.po_id ORDER BY pl.po_date DESC, pl.po_id DESC LIMIT 5
    ";
    $stmt_recent_po = $conn->prepare($recent_po_sql);
    $stmt_recent_po->bind_param('iss', $cus_id, $start_date, $end_date);
    $stmt_recent_po->execute();
    $recent_pos = $stmt_recent_po->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_recent_po->close();

    // ดึงข้อมูลสำหรับกราฟ (ยอดขายตามประเภทสินค้า)
    $chart_sql = "
        SELECT COALESCE(pt.type_name, 'Uncategorized') as type_name, SUM(pi.qty * pi.price) AS revenue
        FROM po_items pi
        JOIN po_list pl ON pi.po_id = pl.po_id
        JOIN product_list p ON pi.prod_id = p.prod_id
        LEFT JOIN prod_type_list pt ON p.type_id = pt.type_id
        WHERE pl.cus_id = ? AND pl.po_date BETWEEN ? AND ?
        GROUP BY pt.type_name ORDER BY revenue DESC
    ";
    $stmt_chart = $conn->prepare($chart_sql);
    $stmt_chart->bind_param('iss', $cus_id, $start_date, $end_date);
    $stmt_chart->execute();
    $type_sales = $stmt_chart->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_chart->close();

    // เตรียมข้อมูลสำหรับ Chart.js
    $chart_labels = json_encode(array_column($type_sales, 'type_name'));
    $chart_data = json_encode(array_column($type_sales, 'revenue'));
    
    // ยืนยัน Transaction
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage()); // บันทึก error ลง log
    $_SESSION['flash_error'] = 'An error occurred while fetching customer data. Please try again.';
    header('Location: customers_list.php');
    exit;
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary for <?= htmlspecialchars($customer_name) ?></title>
    <!-- Section: CSS Libraries (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        body { background-color: #f0f2f5; }
        .kpi-card { transition: transform 0.2s, box-shadow 0.2s; }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .table-responsive { max-height: 400px; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="container-fluid mt-4">
        <!-- Header and Date Filter -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <a href="customer_summary_list.php" class="btn btn-outline-secondary mb-2"><i class="bi bi-arrow-left"></i> Back to List</a>
                <h2 class="mb-0 d-inline-block">
                    <i class="bi bi-person-badge me-2"></i>Customer Summary: <strong><?= htmlspecialchars($customer_name) ?></strong>
                </h2>
            </div>
            <div class="d-flex align-items-center">
                <i class="bi bi-calendar3 me-2 fs-5"></i>
                <form id="date-filter-form" class="d-flex" method="get">
                    <input type="hidden" name="cus_id" value="<?= $cus_id ?>">
                    <input type="text" id="daterange" class="form-control" style="min-width: 280px;">
                    <input type="hidden" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    <input type="hidden" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </form>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row">
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-primary border-4 kpi-card"><div class="card-body"><h6 class="text-primary text-uppercase mb-2">ยอดขายรวม</h6><h4 class="fw-bold mb-0">฿<?= number_format($kpi_data['total_revenue'], 2) ?></h4></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-success border-4 kpi-card"><div class="card-body"><h6 class="text-success text-uppercase mb-2">จำนวนใบสั่งซื้อ</h6><h4 class="fw-bold mb-0"><?= number_format($kpi_data['total_pos']) ?></h4></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-info border-4 kpi-card"><div class="card-body"><h6 class="text-info text-uppercase mb-2">ยอดสั่งซื้อเฉลี่ย</h6><h4 class="fw-bold mb-0">฿<?= number_format($avg_order_value, 2) ?></h4></div></div></div>
            <div class="col-md-6 col-xl-3 mb-4"><div class="card shadow-sm border-start border-warning border-4 kpi-card"><div class="card-body"><h6 class="text-warning text-uppercase mb-2">จำนวนสินค้า (Unique)</h6><h4 class="fw-bold mb-0"><?= number_format($kpi_data['unique_products']) ?></h4></div></div></div>
        </div>

        <!-- Charts and Top Lists -->
        <div class="row">
            <div class="col-xl-7 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>ยอดขายตามประเภทสินค้า</h5></div>
                    <div class="card-body" style="min-height: 400px;">
                        <canvas id="salesByTypeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-star-fill text-warning me-2"></i>5 อันดับสินค้าขายดี</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light"><tr><th>สินค้า</th><th class="text-end">ยอดขาย</th></tr></thead>
                                <tbody>
                                    <?php foreach ($top_products as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['prod_code']) ?><br><small class="text-muted"><?= htmlspecialchars($p['prod_desc']) ?></small></td>
                                        <td class="text-end">฿<?= number_format($p['revenue'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; if(empty($top_products)) echo '<tr><td colspan="2" class="text-center text-muted p-3">ไม่มีข้อมูลในข่วงเวลานี้</td></tr>'; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>5 ใบสั่งซื้อล่าสุด</h5></div>
                     <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light"><tr><th>PO Number</th><th>วันที่</th><th class="text-end">ยอดสั่งซื้อ</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_pos as $po): ?>
                                    <tr>
                                        <td><a href="po_detail.php?po_id=<?= $po['po_id'] ?>"><?= htmlspecialchars($po['po_no']) ?></a></td>
                                        <td><?= date('d M Y', strtotime($po['po_date'])) ?></td>
                                        <td class="text-end">฿<?= number_format($po['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; if(empty($recent_pos)) echo '<tr><td colspan="3" class="text-center text-muted p-3">ไม่มีข้อมูลในข่วงเวลานี้</td></tr>'; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include_once __DIR__ . '/footer.php'; ?>
    
    <!-- Section: JS Libraries (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    
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
               'Last 90 Days': [moment().subtract(89, 'days'), moment()],
            }
        });

        // เมื่อมีการกด Apply ใน daterangepicker ให้ทำการส่ง form เพื่อรีเฟรชหน้า
        $('#daterange').on('apply.daterangepicker', function(ev, picker) {
            $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
            $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
            $('#date-filter-form').submit();
        });

        // --- Chart.js Initialization ---
        const ctx = document.getElementById('salesByTypeChart');
        if (ctx) {
            const chartData = <?= $chart_data ?>;
            if (chartData.length > 0) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= $chart_labels ?>,
                        datasets: [{
                            label: 'ยอดขาย',
                            data: chartData,
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)',
                                'rgba(75, 192, 192, 0.6)', 'rgba(255, 206, 86, 0.6)',
                                'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)', 'rgba(255, 99, 132, 1)',
                                'rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)',
                                'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { callback: function(value) { return '฿' + value.toLocaleString(); }}
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                 callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed.x !== null) {
                                            label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed.x);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                // แสดงข้อความเมื่อไม่มีข้อมูลสำหรับกราฟ
                ctx.parentElement.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">ไม่มีข้อมูลยอดขายตามประเภทสินค้าในช่วงเวลานี้</div>';
            }
        }
    });
    </script>
</body>
</html>
