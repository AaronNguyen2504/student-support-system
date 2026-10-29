<?php
/**
 * =============================================================================
 * admin/manage_staff.php - Quản lý tài khoản người dùng
 * =============================================================================
 * CHỈ Quản trị viên (role = admin) mới được vào trang này.
 *
 * Chức năng:
 *   - Xem danh sách tài khoản, lọc theo vai trò
 *   - Tạo tài khoản cán bộ hỗ trợ mới (mật khẩu được băm bcrypt)
 *   - Khóa / mở khóa tài khoản (đổi cột status)
 *   - Đặt lại mật khẩu cho một tài khoản
 *
 * Nguyên tắc an toàn: quản trị viên KHÔNG thể tự khóa tài khoản của chính mình,
 * tránh trường hợp không còn ai quản trị hệ thống.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_super_admin(); // Cổng bảo vệ nghiêm ngặt nhất

$currentUserId = (int)$_SESSION['user_id'];
$errors        = [];

/* -----------------------------------------------------------------------------
 * XỬ LÝ CÁC THAO TÁC
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die();
    $action = post('action');

    /* --- TẠO TÀI KHOẢN CÁN BỘ MỚI ------------------------------------------- */
    if ($action === 'create_staff') {

        $fullName = post('full_name');
        $email    = post('email');
        $phone    = post('phone');
        $role     = post('role');
        $password = $_POST['password'] ?? '';

        if ($fullName === '') {
            $errors[] = 'Vui lòng nhập họ và tên.';
        } elseif (mb_strlen($fullName) > 255) {
            $errors[] = 'Họ và tên không được vượt quá 255 ký tự.';
        }
        if (!is_valid_email($email)) {
            $errors[] = 'Địa chỉ email không đúng định dạng.';
        }
        if ($phone !== '' && !is_valid_phone($phone)) {
            $errors[] = 'Số điện thoại không hợp lệ.';
        }
        // Chỉ cho phép hai vai trò này, tránh việc sửa dữ liệu gửi lên để leo quyền
        if (!in_array($role, ['staff', 'admin'], true)) {
            $errors[] = 'Vai trò được chọn không hợp lệ.';
        }

        $passwordError = validate_password_strength($password);
        if ($passwordError !== '') {
            $errors[] = $passwordError;
        }

        if (empty($errors)) {
            $duplicate = db_scalar('SELECT COUNT(*) FROM tbl_users WHERE email = ?', [$email], 's');
            if ($duplicate) {
                $errors[] = 'Địa chỉ email này đã tồn tại trong hệ thống.';
            }
        }

        if (empty($errors)) {
            db_execute(
                'INSERT INTO tbl_users (full_name, email, phone, student_id, password, role)
                 VALUES (?, ?, ?, NULL, ?, ?)',
                [$fullName, $email, ($phone !== '' ? $phone : null),
                 password_hash($password, PASSWORD_BCRYPT), $role],
                'sssss'
            );
            set_flash('success', 'Đã tạo tài khoản ' . role_label($role) . ' cho ' . $fullName . '.');
            redirect('manage_staff.php');
        }
    }

    /* --- KHÓA / MỞ KHÓA TÀI KHOẢN ------------------------------------------- */
    if ($action === 'toggle_status') {

        $userId = (int)post('user_id');

        if ($userId === $currentUserId) {
            set_flash('error', 'Bạn không thể tự khóa tài khoản của chính mình.');
        } else {
            $target = db_select_one('SELECT * FROM tbl_users WHERE id = ? LIMIT 1', [$userId], 'i');

            if (!$target) {
                set_flash('error', 'Không tìm thấy tài khoản.');
            } else {
                $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
                db_execute(
                    'UPDATE tbl_users SET status = ? WHERE id = ?',
                    [$newStatus, $userId],
                    'si'
                );
                set_flash('success', ($newStatus === 'active' ? 'Đã mở khóa' : 'Đã khóa')
                    . ' tài khoản ' . $target['full_name'] . '.');
            }
        }
        redirect('manage_staff.php?' . http_build_query($_GET));
    }

    /* --- ĐẶT LẠI MẬT KHẨU --------------------------------------------------- */
    if ($action === 'reset_password') {

        $userId      = (int)post('user_id');
        $newPassword = $_POST['new_password'] ?? '';

        $passwordError = validate_password_strength($newPassword);
        if ($passwordError !== '') {
            set_flash('error', $passwordError);
        } else {
            db_execute(
                'UPDATE tbl_users SET password = ? WHERE id = ?',
                [password_hash($newPassword, PASSWORD_BCRYPT), $userId],
                'si'
            );
            set_flash('success', 'Đã đặt lại mật khẩu. Hãy thông báo mật khẩu mới cho người dùng.');
        }
        redirect('manage_staff.php?' . http_build_query($_GET));
    }
}

/* -----------------------------------------------------------------------------
 * LẤY DANH SÁCH TÀI KHOẢN (có lọc theo vai trò và phân trang)
 * -------------------------------------------------------------------------- */
$roleFilter = get('role');
$where      = '';
$params     = [];

if (in_array($roleFilter, ['student', 'staff', 'admin'], true)) {
    $where    = ' WHERE u.role = ?';
    $params[] = $roleFilter;
}

$totalItems = (int)db_scalar('SELECT COUNT(*) FROM tbl_users u' . $where, $params);
$pagination = paginate($totalItems, (int)get('page', 1));

$listParams   = $params;
$listParams[] = $pagination['per_page'];
$listParams[] = $pagination['offset'];

$users = db_select(
    'SELECT u.*, COUNT(r.id) AS request_count
       FROM tbl_users u
       LEFT JOIN tbl_requests r ON r.user_id = u.id' . $where . '
      GROUP BY u.id
      ORDER BY FIELD(u.role, \'admin\', \'staff\', \'student\'), u.id ASC
      LIMIT ? OFFSET ?',
    $listParams
);

$page_title = 'Quản lý tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Quản lý tài khoản người dùng</h1>
    <a class="btn btn-muted" href="index.php">&laquo; Bảng điều khiển</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong>Không thể tạo tài khoản:</strong>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- ===================== BỘ LỌC VAI TRÒ ===================== -->
<form class="filter-bar" method="get" action="manage_staff.php">
    <div class="form-group">
        <label for="role">Lọc theo vai trò</label>
        <select id="role" name="role" data-submit-on-change>
            <option value="">-- Tất cả vai trò --</option>
            <?php foreach (['admin', 'staff', 'student'] as $role): ?>
            <option value="<?php echo e($role); ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                <?php echo e(role_label($role)); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group flex-none">
        <button type="submit" class="btn">Lọc</button>
    </div>
</form>

<div class="detail-grid">

    <!-- ===================== DANH SÁCH TÀI KHOẢN ===================== -->
    <div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th class="text-center">Yêu cầu</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo (int)$user['id']; ?></td>
                    <td>
                        <strong><?php echo e($user['full_name']); ?></strong>
                        <?php if ($user['student_id']): ?>
                        <div class="text-muted fs-13"><?php echo e($user['student_id']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($user['email']); ?></td>
                    <td><?php echo e(role_label($user['role'])); ?></td>
                    <td class="text-center"><?php echo (int)$user['request_count']; ?></td>
                    <td>
                        <?php if ($user['status'] === 'active'): ?>
                            <span class="badge badge-completed">Hoạt động</span>
                        <?php else: ?>
                            <span class="badge badge-received">Đã khóa</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center nowrap">
                        <?php if ((int)$user['id'] === $currentUserId): ?>
                            <span class="text-muted">Tài khoản của bạn</span>
                        <?php else: ?>
                        <form method="post" action="manage_staff.php?<?php echo e(http_build_query($_GET)); ?>"
                              class="inline-form">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                            <button type="submit"
                                    class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                    data-confirm="Xác nhận thay đổi trạng thái tài khoản <?php echo e($user['full_name']); ?>?">
                                <?php echo $user['status'] === 'active' ? 'Khóa' : 'Mở khóa'; ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($pagination, 'manage_staff.php'); ?>
    </div>

    <!-- ===================== TẠO TÀI KHOẢN / ĐẶT LẠI MẬT KHẨU ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Tạo tài khoản cán bộ</div>

            <form method="post" action="manage_staff.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create_staff">

                <div class="form-group">
                    <label for="full_name">Họ và tên <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="email">Email công vụ <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="phone">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" pattern="0[0-9]{9,10}" title="Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0." maxlength="20">
                </div>

                <div class="form-group">
                    <label for="role">Vai trò <span class="required">*</span></label>
                    <select id="role" name="role" required>
                        <option value="staff">Cán bộ hỗ trợ</option>
                        <option value="admin">Quản trị viên</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu khởi tạo <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.">
                    <div class="form-hint">Tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.</div>
                </div>

                <button type="submit" class="btn btn-success btn-block">Tạo tài khoản</button>
            </form>
        </div>

        <div class="card">
            <div class="card-title">Đặt lại mật khẩu</div>

            <form method="post" action="manage_staff.php?<?php echo e(http_build_query($_GET)); ?>"
                 >
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label for="user_id">Chọn tài khoản <span class="required">*</span></label>
                    <select id="user_id" name="user_id" required>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo (int)$user['id']; ?>">
                            <?php echo e($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_password">Mật khẩu mới <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.">
                </div>

                <button type="submit" class="btn btn-warning btn-block"
                        data-confirm="Xác nhận đặt lại mật khẩu cho tài khoản đã chọn?">
                    Đặt lại mật khẩu
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
