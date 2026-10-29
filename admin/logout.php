<?php
/**
 * =============================================================================
 * admin/logout.php - Đăng xuất khỏi khu vực quản trị
 * =============================================================================
 * Chỉ nhận POST kèm token CSRF; xem user/logout.php.
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout_user();

    session_start();
    set_flash('info', 'Bạn đã đăng xuất khỏi khu vực quản trị.');
}

redirect('login.php');
