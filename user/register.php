<?php
/**
 * =============================================================================
 * user/register.php - Đăng ký tài khoản sinh viên
 * =============================================================================
 * Luồng xử lý:
 *   1. Hiển thị biểu mẫu đăng ký (có token CSRF)
 *   2. Khi gửi lên: kiểm tra CSRF -> kiểm tra dữ liệu -> kiểm tra trùng lặp
 *   3. Băm mật khẩu bằng password_hash(PASSWORD_BCRYPT) rồi lưu vào CSDL
 *   4. Gửi email chào mừng, chuyển sang trang đăng nhập
 *
 * Lưu ý bảo mật: KHÔNG cho phép người dùng tự chọn vai trò. Mọi tài khoản đăng
 * ký qua trang này đều là 'student'; tài khoản cán bộ do quản trị viên tạo.
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// Đã đăng nhập rồi thì không cần đăng ký nữa
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];   // Danh sách lỗi hiển thị lại cho người dùng
$old    = [];   // Giữ lại dữ liệu đã nhập để người dùng không phải gõ lại

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Bước 1: chống giả mạo yêu cầu ---
    csrf_check_or_die();

    // --- Bước 2: lấy và làm sạch dữ liệu ---
    $fullName  = post('full_name');
    $email     = post('email');
    $phone     = post('phone');
    $studentId = post('student_id');
    $password  = $_POST['password'] ?? '';          // Mật khẩu giữ nguyên, không trim
    $confirm   = $_POST['password_confirm'] ?? '';

    $old = compact('fullName', 'email', 'phone', 'studentId');

    // --- Bước 3: kiểm tra từng trường ---
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    } elseif (mb_strlen($fullName) > 255) {
        $errors[] = 'Họ và tên không được vượt quá 255 ký tự.';
    }

    if ($email === '') {
        $errors[] = 'Vui lòng nhập địa chỉ email.';
    } elseif (!is_valid_email($email)) {
        $errors[] = 'Địa chỉ email không đúng định dạng.';
    }

    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors[] = 'Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0.';
    }

    if ($studentId === '') {
        $errors[] = 'Vui lòng nhập mã sinh viên.';
    } elseif (!is_valid_student_id($studentId)) {
        $errors[] = 'Mã sinh viên chỉ gồm chữ và số, độ dài từ 6 đến 20 ký tự.';
    }

    $passwordError = validate_password_strength($password);
    if ($passwordError !== '') {
        $errors[] = $passwordError;
    } elseif ($password !== $confirm) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    // --- Bước 4: kiểm tra trùng email / mã sinh viên (dùng prepared statement) ---
    if (empty($errors)) {
        $existed = db_select_one(
            'SELECT id, email, student_id FROM tbl_users WHERE email = ? OR student_id = ? LIMIT 1',
            [$email, $studentId],
            'ss'
        );
        if ($existed) {
            if (strcasecmp($existed['email'], $email) === 0) {
                $errors[] = 'Địa chỉ email này đã được đăng ký. Vui lòng dùng email khác.';
            } else {
                $errors[] = 'Mã sinh viên này đã tồn tại trong hệ thống.';
            }
        }
    }

    // --- Bước 5: lưu tài khoản mới ---
    if (empty($errors)) {
        // Băm mật khẩu: CSDL chỉ lưu chuỗi băm, không bao giờ lưu mật khẩu gốc
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $newId = db_execute(
            'INSERT INTO tbl_users (full_name, email, phone, student_id, password, role)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$fullName, $email, ($phone !== '' ? $phone : null), $studentId, $hashedPassword, 'student'],
            'ssssss'
        );

        // Gửi email chào mừng (lỗi gửi email không ảnh hưởng kết quả đăng ký)
        send_welcome_email([
            'full_name'  => $fullName,
            'email'      => $email,
            'student_id' => $studentId,
        ]);

        set_flash('success', 'Đăng ký thành công! Bạn có thể đăng nhập bằng email và mật khẩu vừa tạo.');
        redirect('login.php');
    }
}

$page_title = 'Đăng ký tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-box">
    <div class="card">
        <div class="card-title">Đăng ký tài khoản sinh viên</div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Không thể đăng ký:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <?php csrf_field(); // Thẻ ẩn chứa token chống CSRF ?>

            <div class="form-group">
                <label for="full_name">Họ và tên <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" required
                       maxlength="255" placeholder="Ví dụ: Trần Văn An"
                       value="<?php echo e($old['fullName'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="student_id">Mã sinh viên <span class="required">*</span></label>
                <input type="text" id="student_id" name="student_id" required
                       maxlength="20" placeholder="Ví dụ: SV202001"
                       value="<?php echo e($old['studentId'] ?? ''); ?>">
                <div class="form-hint">Chỉ gồm chữ cái và chữ số, từ 6 đến 20 ký tự.</div>
            </div>

            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" required
                       maxlength="255" placeholder="ten.sinhvien@sv.ulsa.edu.vn"
                       value="<?php echo e($old['email'] ?? ''); ?>">
                <div class="form-hint">Email này dùng để đăng nhập và nhận thông báo xử lý.</div>
            </div>

            <div class="form-group">
                <label for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" pattern="0[0-9]{9,10}" title="Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0."
                       maxlength="20" placeholder="0901234567"
                       value="<?php echo e($old['phone'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu <span class="required">*</span></label>
                <input type="password" id="password" name="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.">
                <div class="form-hint">Tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số.</div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Nhập lại mật khẩu <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success btn-block">Đăng ký</button>
            </div>
        </form>

        <p class="text-center mb-0 mt-16">
            Đã có tài khoản? <a href="login.php">Đăng nhập tại đây</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
