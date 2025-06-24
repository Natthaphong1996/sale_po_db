<?php
include 'config_db.php'; // เชื่อมต่อกับฐานข้อมูล

// เริ่มต้น session หากยังไม่ได้เริ่ม
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'];

// ฟังก์ชันสำหรับดึงรายการเมนูตามสิทธิ์ของผู้ใช้ 
function getMenuItems($user_department, $user_level)
{
    $menu = [];
    // สำหรับกลุ่ม IT
    if (in_array($user_department, ['IT'])) {
        $menu[] = [
            'title' => 'HOME',
            'url' => 'landing_page.php'
        ];
        $menu[] = [
            'title' => 'CUSTOMER',
            'submenu' => [
                ['title' => 'CUSTOMER LIST', 'url' => 'customers_list.php'],
            ]
        ];
        $menu[] = [
            'title' => 'PRODUCT',
            'submenu' => [
                ['title' => 'PRODUCT LIST', 'url' => 'products_list.php'],
                ['title' => 'PRODUCT TYPE LIST', 'url' => 'prod_type_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'PO',
            'submenu' => [
                ['title' => 'ALL PO LIST', 'url' => 'po_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'SUMMARY',
            'submenu' => [
                ['title' => 'SUMMARY', 'url' => 'sale_summary_view.php'],
            ]
        ];

    }

    // สำหรับกลุ่ม IT
    if (in_array($user_department, ['SALE'])) {
        $menu[] = [
            'title' => 'HOME',
            'url' => 'landing_page.php'
        ];
        $menu[] = [
            'title' => 'CUSTOMER',
            'submenu' => [
                ['title' => 'CUSTOMER LIST', 'url' => 'customers_list.php'],
            ]
        ];
        $menu[] = [
            'title' => 'PRODUCT',
            'submenu' => [
                ['title' => 'PRODUCT LIST', 'url' => 'products_list.php'],
                ['title' => 'PRODUCT TYPE LIST', 'url' => 'prod_type_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'PO',
            'submenu' => [
                ['title' => 'ALL PO LIST', 'url' => 'po_list.php'],
            ]
        ];

        $menu[] = [
            'title' => 'SUMMARY',
            'submenu' => [
                ['title' => 'SUMMARY', 'url' => 'sale_summary_view.php'],
            ]
        ];

    }
    return $menu;
}

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$sql = "SELECT department, level FROM prod_user WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_department, $user_level);
$stmt->fetch();
$stmt->close();

$menuItems = getMenuItems($user_department, $user_level);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <!-- แสดง Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="landing_page.php">Siam Kyohwa Seisakusho</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php foreach ($menuItems as $item): ?>
                        <?php if (isset($item['submenu'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <?= $item['title'] ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($item['submenu'] as $sub): ?>
                                        <li><a class="dropdown-item" href="<?= $sub['url'] ?>"><?= $sub['title'] ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $item['url'] ?>"><?= $item['title'] ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <!-- เมนูออกจากระบบ -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> LOGOUT
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ประกาศแจ้งเตือน
    <div class="alert alert-warning text-center" role="alert">
        <strong>Notification Announcement : </strong> Date 26/05/2025 The system will be taken offline for an update starting at 13:30, and will remain unavailable until 17:00 or until further notice.
    </div> -->
</body>

</html>