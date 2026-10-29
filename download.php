<?php
/**
 * =============================================================================
 * download.php - Tải tệp đính kèm có kiểm tra quyền
 * =============================================================================
 * Tệp trong uploads/attachments/ bị .htaccess chặn truy cập trực tiếp. Mọi lượt tải
 * đi qua đây:
 *   download.php?id=<mã yêu cầu>                     -> tệp sinh viên đính kèm yêu cầu
 *   download.php?id=<mã yêu cầu>&response=<mã phản hồi> -> tệp cán bộ đính kèm phản hồi
 * Chỉ chủ yêu cầu, cán bộ và quản trị viên được tải.
 * =============================================================================
 */

require_once __DIR__ . '/includes/session_check.php';

$loginUrl = BASE_URL . '/user/login.php';
check_session_timeout(is_admin() ? SESSION_TIMEOUT_ADMIN : SESSION_TIMEOUT_USER, $loginUrl);
sync_session_user($loginUrl);
if (!is_logged_in()) {
    set_flash('error', 'Vui lòng đăng nhập để tải tệp.');
    redirect($loginUrl);
}

$request = get_request_detail((int)get('id'));
if (!$request || (!is_admin() && (int)$request['user_id'] !== (int)$_SESSION['user_id'])) {
    render_error_page(404, 'Không tìm thấy tệp', 'Tệp không tồn tại hoặc bạn không có quyền tải.');
}

if (get('response') !== '') {
    $name = db_scalar(
        'SELECT attachment_path FROM tbl_responses WHERE id = ? AND request_id = ?',
        [(int)get('response'), (int)$request['id']],
        'ii'
    );
} else {
    $name = $request['attachment_path'];
}

// basename() chặn đường dẫn kiểu ../ dù tên tệp do chính hệ thống sinh ra
$path = $name ? UPLOAD_DIR . basename($name) : '';
if ($path === '' || !is_file($path)) {
    render_error_page(404, 'Không tìm thấy tệp', 'Tệp không tồn tại hoặc bạn không có quyền tải.');
}

// Hiển thị trực tiếp ảnh và PDF, các định dạng còn lại tải về
$types = [
    'pdf'  => ['application/pdf', 'inline'],
    'jpg'  => ['image/jpeg', 'inline'],
    'jpeg' => ['image/jpeg', 'inline'],
    'png'  => ['image/png', 'inline'],
    'gif'  => ['image/gif', 'inline'],
    'doc'  => ['application/msword', 'attachment'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'attachment'],
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
[$mime, $disposition] = $types[$ext] ?? ['application/octet-stream', 'attachment'];

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
