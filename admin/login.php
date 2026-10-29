<?php
/**
 * =============================================================================
 * admin/login.php - Đăng nhập khu vực quản trị (cán bộ và quản trị viên)
 * =============================================================================
 * Dùng chung toàn bộ xử lý với user/login.php. Cờ $ADMIN_LOGIN bật thêm bước
 * kiểm tra vai trò: chỉ 'staff' hoặc 'admin' mới đăng nhập được ở đây.
 * =============================================================================
 */

$ADMIN_LOGIN = true;
require __DIR__ . '/../user/login.php';
