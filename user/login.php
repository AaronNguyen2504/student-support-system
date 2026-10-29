<?php
/**
 * =============================================================================
 * user/login.php - Trang đăng nhập dùng chung cho sinh viên và cán bộ
 * =============================================================================
 * Cơ chế xác thực:
 *   1. Tìm người dùng theo email bằng prepared statement
 *   2. So khớp mật khẩu bằng password_verify() với chuỗi băm trong CSDL
 *   3. Kiểm tra tài khoản còn hoạt động (status = 'active')
 *   4. Tạo phiên làm việc, tái tạo ID phiên để chống chiếm phiên
 *   5. Chuyển tới khu vực đúng với vai trò
 *
 * Tệp admin/login.php đặt cờ $ADMIN_LOGIN = true rồi nạp lại chính tệp này.
 * Khi có cờ đó, tài khoản sinh viên bị từ chối dù nhập đúng mật khẩu.
 *
 * Mọi đường dẫn chuyển hướng đều là đường dẫn tuyệt đối (BASE_URL), vì tệp này
 * chạy được từ cả hai thư mục /user và /admin.
 *
 * Lưu ý bảo mật: khi sai thông tin, hệ thống chỉ báo chung chung
 * "Email hoặc mật khẩu không đúng" để kẻ tấn công không dò được email nào tồn tại.
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

$adminLogin = !empty($ADMIN_LOGIN);

// Trang đích sau khi đăng nhập, tùy theo vai trò
$homeFor = function () {
    return BASE_URL . (is_admin() ? '/admin/index.php' : '/user/index.php');
};

// Đã đăng nhập thì chuyển thẳng tới khu vực tương ứng với vai trò
if (is_logged_in() && (!$adminLogin || is_admin())) {
    redirect($homeFor());
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die(); // Chống giả mạo yêu cầu

    $email    = post('email');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        // Truy vấn có tham số -> dữ liệu người dùng không thể chèn mã SQL
        // lock_left: số giây còn bị khóa (tính bằng đồng hồ của MySQL, cùng nguồn với lúc ghi)
        $user = db_select_one(
            'SELECT *, IF(locked_until > NOW(), TIMESTAMPDIFF(SECOND, NOW(), locked_until), 0) AS lock_left
               FROM tbl_users WHERE email = ? LIMIT 1',
            [$email],
            's'
        );

        if ($user && (int)$user['lock_left'] > 0) {
            // Đang bị khóa: không kiểm tra mật khẩu, kể cả khi nhập đúng
            $error = 'Tài khoản tạm khóa do đăng nhập sai nhiều lần. Vui lòng thử lại sau '
                   . (int)ceil($user['lock_left'] / 60) . ' phút.';
        } elseif (!$user || !password_verify($password, $user['password'])) {
            // Thông báo chung cho cả hai trường hợp sai email và sai mật khẩu
            $error = 'Email hoặc mật khẩu không đúng.';
            if ($user) {
                // Đủ số lần sai thì đặt mốc khóa và đếm lại từ 0 (vế locked_until đọc failed_logins cũ)
                db_execute(
                    'UPDATE tbl_users
                        SET locked_until  = IF(failed_logins + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until),
                            failed_logins = IF(failed_logins + 1 >= ?, 0, failed_logins + 1)
                      WHERE id = ?',
                    [LOGIN_MAX_FAILS, LOGIN_LOCK_MINUTES, LOGIN_MAX_FAILS, (int)$user['id']],
                    'iiii'
                );
            }
        } elseif ($adminLogin && !in_array($user['role'], ['staff', 'admin'], true)) {
            // Đúng mật khẩu nhưng không đủ quyền vào khu vực quản trị -> vẫn từ chối
            $error = 'Tài khoản này không có quyền truy cập khu vực quản trị.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ Phòng Công Tác Sinh Viên.';
        } else {
            // Đăng nhập đúng: xóa bộ đếm sai, và nâng cấp chuỗi băm nếu thuật toán mặc định đã đổi
            db_execute(
                'UPDATE tbl_users SET failed_logins = 0, locked_until = NULL WHERE id = ?',
                [(int)$user['id']],
                'i'
            );
            if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                db_execute(
                    'UPDATE tbl_users SET password = ? WHERE id = ?',
                    [password_hash($password, PASSWORD_BCRYPT), (int)$user['id']],
                    'si'
                );
            }

            login_user($user); // Ghi phiên + tái tạo ID phiên
            set_flash('success', 'Xin chào ' . $user['full_name'] . '!');
            redirect($homeFor());
        }
    }
}

$page_title = $adminLogin ? 'Đăng nhập quản trị' : 'Đăng nhập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-box">
    <div class="card">
        <div class="card-title">
            <?php echo $adminLogin ? 'Đăng nhập - ' . e(APP_DEPARTMENT) : 'Đăng nhập hệ thống'; ?>
        </div>

        <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- action để trống: biểu mẫu gửi về đúng trang đang mở (user hoặc admin) -->
        <form method="post" action="">
            <?php csrf_field(); ?>

            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?php echo e($email); ?>"
                       placeholder="<?php echo $adminLogin ? 'canbo@ulsa.edu.vn' : 'ten.sinhvien@sv.ulsa.edu.vn'; ?>">
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu <span class="required">*</span></label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-block <?php echo $adminLogin ? 'btn-primary' : ''; ?>">Đăng nhập</button>
            </div>
        </form>

        <p class="text-center mb-0 mt-16">
            <?php if ($adminLogin): ?>
                <a href="<?php echo BASE_URL; ?>/user/login.php">Đăng nhập dành cho sinh viên</a>
            <?php else: ?>
                Chưa có tài khoản? <a href="<?php echo BASE_URL; ?>/user/register.php">Đăng ký ngay</a><br>
                <a href="<?php echo BASE_URL; ?>/admin/login.php">Đăng nhập dành cho cán bộ</a>
            <?php endif; ?>
        </p>

        <?php if ($adminLogin): ?>
        <div class="alert alert-info mt-16 mb-0 fs-13">
            Phiên làm việc của khu vực quản trị tự động kết thúc sau 60 phút không thao tác.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
