<?php
session_start(); // เริ่ม session

// ลบข้อมูลใน session ทั้งหมด
session_unset();

// ทำลาย session
session_destroy();

// รีไดเร็กต์ผู้ใช้กลับไปที่หน้า login
header("Location: index.php"); 
exit();
?>
