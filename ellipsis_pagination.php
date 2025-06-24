    <?php
    // ภาษา: PHP
    // ชื่อไฟล์: ellipsis_pagination.php

    /**
     * แสดง Ellipsis (Windowed) Pagination โดยรับการตั้งค่าผ่านอาร์เรย์เดียว
     *
     * @param array $config  การตั้งค่า pagination
     *   - int    currentPage   : หน้าปัจจุบัน (1-based)
     *   - int    totalPages    : จำนวนหน้าทั้งหมด
     *   - string baseUrl       : URL พื้นฐานก่อน ?page=
     *   - int    adjacents     : จำนวนหน้าข้างเคียงที่ต้องการแสดง (default: 2)
     *   - string pageParam     : ชื่อพารามิเตอร์สำหรับหมายเลขหน้า (default: 'page')
     *   - array  extraParams   : พารามิเตอร์อื่นๆ ที่ต้องการต่อท้าย URL (associative array)
     *   - string ulClass       : คลาสของ &lt;ul&gt; (default: 'pagination justify-content-center')
     * }
     */
    function renderEllipsisPagination(array $config): void {
        // กำหนดค่าเริ่มต้น
        $currentPage = max(1, (int)($config['currentPage'] ?? 1));
        $totalPages  = max(1, (int)($config['totalPages']  ?? 1));
        $baseUrl     = rtrim($config['baseUrl'] ?? '', '?&');
        $adjacents   = isset($config['adjacents'])   ? (int)$config['adjacents']   : 2;
        $pageParam   = $config['pageParam']          ?? 'page';
        $extraParams = $config['extraParams']        ?? [];
        $ulClass     = $config['ulClass']            ?? 'pagination justify-content-center';

        // ไม่แสดงถ้ามีหน้าเดียว
        if ($totalPages <= 1) {
            return;
        }

        // สร้าง query string ของพารามิเตอร์อื่นๆ (นอกจาก page)
        $qs = http_build_query($extraParams);
        $qs = $qs ? '&' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') : '';

        echo '<nav aria-label="Page navigation"><ul class="' . htmlspecialchars($ulClass, ENT_QUOTES, 'UTF-8') . '">';

        $start = max(1, $currentPage - $adjacents);
        $end   = min($totalPages, $currentPage + $adjacents);

        // ลิงก์หน้าแรก + Ellipsis ก่อน
        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="'
            . htmlspecialchars("{$baseUrl}?{$pageParam}=1{$qs}", ENT_QUOTES, 'UTF-8')
            . '">1</a></li>';
            if ($start > 2) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        // ลูปเลขหน้า
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            echo '<li class="page-item' . $active . '">'
            . '<a class="page-link" href="'
            . htmlspecialchars("{$baseUrl}?{$pageParam}={$i}{$qs}", ENT_QUOTES, 'UTF-8')
            . "\">{$i}</a></li>";
        }

        // Ellipsis หลัง + ลิงก์หน้าสุดท้าย
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="'
            . htmlspecialchars("{$baseUrl}?{$pageParam}={$totalPages}{$qs}", ENT_QUOTES, 'UTF-8')
            . "\">{$totalPages}</a></li>";
        }

        echo '</ul></nav>';
    }
