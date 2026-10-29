<?php
/**
 * =============================================================================
 * user/profile.php - Hồ sơ cá nhân của sinh viên
 * =============================================================================
 * Cho phép người dùng:
 *   1. Cập nhật họ tên, số điện thoại và email liên hệ
 *   2. Đổi mật khẩu (bắt buộc nhập đúng mật khẩu hiện tại)
 *
 * Hai biểu mẫu nằm trên cùng một trang, phân biệt nhau bằng trường ẩn "action".
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_user_login();

$userId = (int)$_SESSION['user_id'];
$user   = current_user();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die();
    $action = post('action');

    /* -------------------------------------------------------------------------
     * TRƯỜNG HỢP 1: CẬP NHẬT THÔNG TIN CÁ NHÂN
     * ---------------------------------------------------------------------- */
    if ($action === 'update_profile') {

        $fullName = post('full_name');
        $email    = post('email');
        $phone    = post('phone');

        if ($fullName === '') {
            $errors[] = 'Vui lòng nhập họ và tên.';
        } elseif (mb_strlen($fullName) > 255) {
            $errors[] = 'Họ và tên không được vượt quá 255 ký tự.';
        }
        if (!is_valid_email($email)) {
            $errors[] = 'Địa chỉ email không đúng định dạng.';
        } elseif ($email !== $user['email']
            && !password_verify($_POST['email_password'] ?? '', $user['password'])) {
            // Email là định danh đăng nhập và nơi nhận thông báo: đổi email phải xác minh mật khẩu
            $errors[] = 'Đổi email cần nhập đúng mật khẩu hiện tại.';
        }
        if ($phone !== '' && !is_valid_phone($phone)) {
            $errors[] = 'Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0.';
        }

        // Email mới không được trùng với tài khoản khác
        if (empty($errors)) {
            $duplicate = db_scalar(
                'SELECT COUNT(*) FROM tbl_users WHERE email = ? AND id <> ?',
                [$email, $userId],
                'si'
            );
            if ($duplicate) {
                $errors[] = 'Địa chỉ email này đã được tài khoản khác sử dụng.';
            }
        }

        if (empty($errors)) {
            db_execute(
                'UPDATE tbl_users SET full_name = ?, email = ?, phone = ? WHERE id = ?',
                [$fullName, $email, ($phone !== '' ? $phone : null), $userId],
                'sssi'
            );

            // Cập nhật lại phiên để thanh điều hướng hiển thị tên mới ngay lập tức
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email']     = $email;

            set_flash('success', 'Cập nhật thông tin cá nhân thành công.');
            redirect('profile.php');
        }
    }

    /* -------------------------------------------------------------------------
     * TRƯỜNG HỢP 2: ĐỔI MẬT KHẨU
     * ---------------------------------------------------------------------- */
    if ($action === 'change_password') {

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['password'] ?? '';
        $confirm         = $_POST['password_confirm'] ?? '';

        // Bắt buộc xác minh mật khẩu hiện tại: chống việc ai đó dùng máy bỏ quên
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        }

        $passwordError = validate_password_strength($newPassword);
        if ($passwordError !== '') {
            $errors[] = $passwordError;
        } elseif ($newPassword !== $confirm) {
            $errors[] = 'Mật khẩu mới nhập lại không khớp.';
        } elseif ($newPassword === $currentPassword) {
            $errors[] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        }

        if (empty($errors)) {
            db_execute(
                'UPDATE tbl_users SET password = ? WHERE id = ?',
                [password_hash($newPassword, PASSWORD_BCRYPT), $userId],
                'si'
            );
            session_regenerate_id(true); // Đổi ID phiên sau khi đổi thông tin xác thực
            set_flash('success', 'Đổi mật khẩu thành công. Hãy dùng mật khẩu mới cho lần đăng nhập sau.');
            redirect('profile.php');
        }
    }

    // Có lỗi -> nạp lại dữ liệu hiện tại để hiển thị
    $user = current_user();
}

// --- Vài số liệu tóm tắt của tài khoản ---
$totalRequests = (int)db_scalar('SELECT COUNT(*) FROM tbl_requests WHERE user_id = ?', [$userId], 'i');
$completed     = (int)db_scalar(
    'SELECT COUNT(*) FROM tbl_requests WHERE user_id = ? AND status = ?',
    [$userId, 'completed'],
    'is'
);

$page_title = 'Hồ sơ cá nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Hồ sơ cá nhân</h1>
    <a class="btn btn-muted" href="index.php">&laquo; Yêu cầu của tôi</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong>Vui lòng kiểm tra lại:</strong>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="detail-grid">
    <div>
        <!-- ===================== THÔNG TIN CÁ NHÂN ===================== -->
        <div class="card">
            <div class="card-title">Thông tin cá nhân</div>

            <form method="post" action="profile.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="student_id">Mã sinh viên</label>
                    <!-- Mã sinh viên do nhà trường cấp nên không cho tự sửa -->
                    <input type="text" id="student_id" value="<?php echo e($user['student_id']); ?>" disabled>
                    <div class="form-hint">Liên hệ Phòng Công Tác Sinh Viên nếu cần đính chính mã sinh viên.</div>
                </div>

                <div class="form-group">
                    <label for="full_name">Họ và tên <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" required maxlength="255"
                           value="<?php echo e($user['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="255"
                           value="<?php echo e($user['email']); ?>">
                </div>

                <div class="form-group">
                    <label for="email_password">Mật khẩu hiện tại</label>
                    <input type="password" id="email_password" name="email_password" autocomplete="current-password">
                    <div class="form-hint">Chỉ cần nhập khi bạn đổi địa chỉ email.</div>
                </div>

                <div class="form-group">
                    <label for="phone">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" pattern="0[0-9]{9,10}" title="Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0." maxlength="20"
                           value="<?php echo e($user['phone']); ?>">
                </div>

                <button type="submit" class="btn">Lưu thay đổi</button>
            </form>
        </div>

        <!-- ===================== ĐỔI MẬT KHẨU ===================== -->
        <div class="card">
            <div class="card-title">Đổi mật khẩu</div>

            <form method="post" action="profile.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu mới <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.">
                    <div class="form-hint">Tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Nhập lại mật khẩu mới <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>

                <button type="submit" class="btn btn-warning">Đổi mật khẩu</button>
            </form>
        </div>
    </div>

    <!-- ===================== TÓM TẮT TÀI KHOẢN ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Tóm tắt tài khoản</div>
            <table class="info-table">
                <tr><th>Vai trò</th><td><?php echo e(role_label($user['role'])); ?></td></tr>
                <tr><th>Trạng thái</th>
                    <td><?php echo $user['status'] === 'active' ? 'Đang hoạt động' : 'Đã khóa'; ?></td></tr>
                <tr><th>Ngày tạo</th><td><?php echo e(format_datetime($user['created_at'])); ?></td></tr>
                <tr><th>Tổng số yêu cầu</th><td><?php echo $totalRequests; ?></td></tr>
                <tr><th>Đã hoàn thành</th><td><?php echo $completed; ?></td></tr>
            </table>
        </div>

        <div class="alert alert-info">
            <strong>Bảo mật:</strong> hệ thống chỉ lưu mật khẩu ở dạng đã băm (bcrypt),
            không ai - kể cả quản trị viên - đọc được mật khẩu gốc của bạn.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
