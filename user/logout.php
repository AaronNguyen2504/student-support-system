<?php
/**
 * =============================================================================
 * user/logout.php - Đăng xuất khỏi hệ thống
 * =============================================================================
 * Chỉ nhận POST kèm token CSRF (nút "Đăng xuất" ở header). Truy cập bằng GET hoặc
 * token sai thì không đăng xuất, chỉ chuyển về trang đăng nhập.
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout_user();   // Xóa $_SESSION, xóa cookie và hủy phiên

    // Mở phiên mới chỉ để mang theo thông báo tạm biệt
    session_start();
    set_flash('info', 'Bạn đã đăng xuất an toàn. Hẹn gặp lại!');
}

redirect('login.php');
