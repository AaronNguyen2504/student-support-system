<?php
/**
 * =============================================================================
 * includes/session_check.php - Kiểm tra phiên làm việc và phân quyền
 * =============================================================================
 * Tệp này cung cấp hai "cổng bảo vệ" được gọi ở đầu mỗi trang cần đăng nhập:
 *
 *   require_user_login()   -> dành cho khu vực sinh viên  (thư mục /user)
 *   require_admin_login()  -> dành cho khu vực quản trị   (thư mục /admin)
 *
 * Cả hai đều thực hiện 3 việc:
 *   1. Kiểm tra đã đăng nhập hay chưa
 *   2. Kiểm tra phiên có hết hạn không (30 phút / 60 phút)
 *   3. Kiểm tra vai trò có đúng với khu vực đang truy cập không
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

/**
 * Kiểm tra phiên làm việc đã quá hạn chưa và tự động đăng xuất nếu quá hạn.
 *
 * Nguyên tắc: mỗi lần người dùng tải một trang, mốc thời gian hoạt động cuối
 * (last_activity) được cập nhật. Nếu khoảng cách giữa hai lần tải trang vượt
 * quá thời gian cho phép thì phiên bị hủy.
 *
 * @param int    $timeout     Số giây được phép không hoạt động
 * @param string $redirectTo  Trang đăng nhập tương ứng
 */
function check_session_timeout($timeout, $redirectTo)
{
    if (!is_logged_in()) {
        return;
    }

    $last = $_SESSION['last_activity'] ?? time();

    if ((time() - $last) > $timeout) {
        // Xóa sạch dữ liệu đăng nhập và cấp ID phiên mới.
        // Lưu ý: KHÔNG dùng logout_user() ở đây, vì hàm đó xóa luôn cookie phiên,
        // khiến thông báo "hết hạn" bên dưới không còn chỗ lưu để hiển thị.
        $_SESSION = [];
        session_regenerate_id(true);

        set_flash('warning', 'Phiên làm việc đã hết hạn do không hoạt động. Vui lòng đăng nhập lại.');
        redirect($redirectTo);
    }

    // Còn hạn -> ghi nhận lại mốc hoạt động mới nhất
    $_SESSION['last_activity'] = time();
}

/**
 * Đối chiếu phiên với CSDL ở mỗi request: tài khoản bị khóa hoặc đã bị xóa thì hủy phiên
 * ngay, vai trò luôn lấy theo CSDL (không tin giá trị cũ trong phiên).
 */
function sync_session_user($redirectTo)
{
    if (!is_logged_in()) {
        return;
    }

    $row = db_select_one(
        'SELECT status, role FROM tbl_users WHERE id = ? LIMIT 1',
        [(int)$_SESSION['user_id']],
        'i'
    );

    if (!$row || $row['status'] !== 'active') {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('error', 'Tài khoản đã bị khóa hoặc không còn tồn tại. Vui lòng liên hệ Phòng Công Tác Sinh Viên.');
        redirect($redirectTo);
    }

    $_SESSION['role'] = $row['role'];
}

/**
 * Cổng bảo vệ khu vực sinh viên.
 * Cán bộ/quản trị viên vẫn xem được (để hỗ trợ kiểm thử), nhưng khách thì không.
 */
function require_user_login()
{
    check_session_timeout(SESSION_TIMEOUT_USER, 'login.php');
    sync_session_user('login.php');

    if (!is_logged_in()) {
        set_flash('error', 'Vui lòng đăng nhập để sử dụng chức năng này.');
        redirect('login.php');
    }
}

/**
 * Cổng bảo vệ khu vực quản trị.
 * Chỉ chấp nhận vai trò 'staff' hoặc 'admin'; sinh viên bị từ chối với mã 403.
 */
function require_admin_login()
{
    check_session_timeout(SESSION_TIMEOUT_ADMIN, 'login.php');
    sync_session_user('login.php');

    if (!is_logged_in()) {
        set_flash('error', 'Vui lòng đăng nhập bằng tài khoản cán bộ.');
        redirect('login.php');
    }

    if (!is_admin()) {
        render_error_page(403, 'Không có quyền truy cập',
            'Tài khoản của bạn không được phép vào khu vực quản trị.',
            BASE_URL . '/user/index.php', 'Quay lại trang sinh viên');
    }
}

/**
 * Chỉ cho phép quản trị viên cấp cao (role = admin).
 * Dùng cho các thao tác nhạy cảm: xóa yêu cầu, quản lý tài khoản cán bộ.
 */
function require_super_admin()
{
    require_admin_login();

    if (!is_super_admin()) {
        render_error_page(403, 'Không có quyền',
            'Chức năng này chỉ dành cho Quản trị viên.',
            BASE_URL . '/admin/index.php', 'Quay lại bảng điều khiển');
    }
}
