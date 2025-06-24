<?php
// Language: PHP
// File: landing_page.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user is not logged in
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('Asia/Bangkok');
include_once __DIR__ . '/config_db.php';

// --- START: Data Fetching for Dashboard ---

$username = $_SESSION['username'];
$user_display_name = $_SESSION['thainame'] ?? $username;

// Fetch KPIs for today
$kpi_today_sql = "
    SELECT
        (SELECT COUNT(po_id) FROM po_list WHERE DATE(created_at) = CURDATE()) AS new_pos_today,
        (SELECT COALESCE(SUM(pi.qty * pi.price), 0) FROM po_items pi JOIN po_list pl ON pi.po_id = pl.po_id WHERE DATE(pl.created_at) = CURDATE()) AS revenue_today
";
$kpi_result = $conn->query($kpi_today_sql);
$kpi_today = $kpi_result->fetch_assoc();


// Fetch 5 recent POs
$recent_pos_sql = "
    SELECT pl.po_no, c.cus_name, pl.created_at
    FROM po_list pl
    JOIN customer_list c ON pl.cus_id = c.cus_id
    ORDER BY pl.po_id DESC
    LIMIT 5
";
$recent_pos_result = $conn->query($recent_pos_sql);

$conn->close();
// --- END: Data Fetching ---
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PO Management</title>
    <!-- CSS Libraries (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6; /* A slightly different light gray */
        }
        .main-content {
            flex-grow: 1;
        }
        .quick-access-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .quick-access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .kpi-value {
            font-size: 2.25rem;
            font-weight: 700;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include 'navbar.php'; ?>

    <!-- Main Content Area -->
    <main class="container-fluid mt-4 main-content">
        
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h4 class="mb-0">สวัสดีตอนเช้า, คุณ <?= htmlspecialchars($user_display_name) ?>!</h4>
                            <p class="text-muted mb-0">ยินดีต้อนรับเข้าสู่ระบบจัดการใบสั่งซื้อ</p>
                        </div>
                        <div class="text-end">
                            <h5 class="fw-bold mb-0" id="current-time"></h5>
                            <p class="text-muted mb-0"><?= date('l, F j, Y') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access Menu -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-6">
                <a href="po_list.php" class="card text-center quick-access-card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-list-ul text-primary" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3">จัดการใบสั่งซื้อ</h5>
                        <p class="card-text text-muted small">ดู, แก้ไข, และค้นหา PO ทั้งหมด</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="register_new_po_view.php" class="card text-center quick-access-card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-journal-plus text-success" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3">ลงทะเบียน PO ใหม่</h5>
                        <p class="card-text text-muted small">สร้างใบสั่งซื้อใหม่จากลูกค้า</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="customers_list.php" class="card text-center quick-access-card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-people-fill text-warning" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3">จัดการข้อมูลลูกค้า</h5>
                        <p class="card-text text-muted small">เพิ่ม, แก้ไขข้อมูลลูกค้า</p>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-6">
                <a href="sale_summary_view.php" class="card text-center quick-access-card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-bar-chart-line-fill text-danger" style="font-size: 3rem;"></i>
                        <h5 class="card-title mt-3">ดูสรุปยอดขาย</h5>
                        <p class="card-text text-muted small">ภาพรวมและแดชบอร์ดยอดขาย</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- At a Glance Section -->
        <div class="row g-4">
            <!-- KPIs Today -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>ภาพรวมวันนี้</h5>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="text-center mb-3">
                            <p class="text-muted mb-1">ใบสั่งซื้อใหม่วันนี้</p>
                            <p class="kpi-value text-info"><?= number_format($kpi_today['new_pos_today']) ?></p>
                        </div>
                         <hr>
                        <div class="text-center mt-3">
                            <p class="text-muted mb-1">ยอดขายวันนี้</p>
                            <p class="kpi-value text-success">฿<?= number_format($kpi_today['revenue_today'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>รายการล่าสุด</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">PO Number</th>
                                        <th>Customer</th>
                                        <th class="text-end pe-3">Date Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_pos_result && $recent_pos_result->num_rows > 0): ?>
                                        <?php while($row = $recent_pos_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3"><a href="po_detail.php?po_no=<?= urlencode($row['po_no']) ?>"><?= htmlspecialchars($row['po_no']) ?></a></td>
                                            <td><?= htmlspecialchars($row['cus_name']) ?></td>
                                            <td class="text-end pe-3 text-muted small"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center p-4 text-muted">No recent activity.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update the time every second
        function updateTime() {
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                const now = new Date();
                // Formatting to HH:MM:SS
                const timeString = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                timeElement.textContent = timeString;
            }
        }

        // Update time immediately on load, then every second
        updateTime();
        setInterval(updateTime, 1000);
    </script>
</body>
</html>
