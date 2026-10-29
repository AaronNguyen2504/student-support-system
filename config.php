<?php
/**
 * =============================================================================
 * config.php - Tệp cấu hình trung tâm của hệ thống
 * =============================================================================
 * Hệ thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên (Đề tài 63)
 * Trường Đại Học Lao Động - Xã Hội CS2
 *
 * Tệp này chịu trách nhiệm:
 *   1. Khai báo các hằng số cấu hình (CSDL, email, đường dẫn, giới hạn tệp)
 *   2. Mở kết nối MySQL bằng mysqli (bắt buộc dùng prepared statement về sau)
 *   3. Khởi tạo phiên làm việc (session) an toàn + kiểm tra hết hạn phiên
 *
 * LƯU Ý BẢO MẬT: mọi truy vấn tới CSDL phải dùng prepared statement,
 * tuyệt đối không nối chuỗi dữ liệu người dùng vào câu lệnh SQL.
 * =============================================================================
 */

// Cấu hình riêng của từng máy (mật khẩu DB, tài khoản SMTP, DEBUG_MODE...): tạo tệp config.local.php
// chỉ chứa các lệnh define('TEN_HANG', giá_trị) cho những hằng ở dưới. Tệp này nằm trong .gitignore
// và được nạp trước nên ghi đè giá trị mặc định. Không có tệp thì dùng mặc định bên dưới.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// -----------------------------------------------------------------------------
// 1. CẤU HÌNH CƠ SỞ DỮ LIỆU
// -----------------------------------------------------------------------------
defined('DB_HOST') || define('DB_HOST', 'localhost');      // Máy chủ CSDL
defined('DB_USER') || define('DB_USER', 'root');           // Tài khoản MySQL (XAMPP mặc định là root)
defined('DB_PASS') || define('DB_PASS', '');               // Mật khẩu MySQL (XAMPP mặc định để trống)
defined('DB_NAME') || define('DB_NAME', 'student_support'); // Tên cơ sở dữ liệu
define('DB_CHARSET', 'utf8mb4');     // Bảng mã hỗ trợ đầy đủ tiếng Việt

// -----------------------------------------------------------------------------
// 2. CẤU HÌNH CHUNG CỦA ỨNG DỤNG
// -----------------------------------------------------------------------------
define('APP_NAME', 'Hệ Thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên');
define('APP_SHORT_NAME', 'Hỗ Trợ Sinh Viên');
define('APP_DEPARTMENT', 'Phòng Công Tác Sinh Viên');
define('APP_UNIVERSITY', 'Trường Đại Học Lao Động - Xã Hội CS2');

// Đường dẫn gốc của ứng dụng trên web, tự suy ra từ DOCUMENT_ROOT nên đổi tên
// thư mục hoặc đặt ở vị trí khác trong htdocs vẫn chạy đúng.
// Nếu máy dùng junction/symlink làm cách tính sai, gán cứng lại ở dòng dưới.
$__docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$__appRoot = str_replace('\\', '/', (string) realpath(__DIR__));
define('BASE_URL', rtrim(
    ($__docRoot !== '' && stripos($__appRoot, $__docRoot) === 0)
        ? substr($__appRoot, strlen($__docRoot))
        : '/student-support-system',
    '/'
));

// Tệp logo hiển thị trên thanh đầu trang và làm biểu tượng của tab trình duyệt.
// Muốn đổi logo chỉ cần thay tệp ảnh trong assets/images/ rồi sửa dòng này.
define('APP_LOGO', BASE_URL . '/assets/images/logo-ulsa2.png');

// Đường dẫn tuyệt đối tới thư mục gốc mã nguồn trên ổ đĩa
define('BASE_PATH', __DIR__);

// -----------------------------------------------------------------------------
// 3. CẤU HÌNH TẢI TỆP ĐÍNH KÈM
// -----------------------------------------------------------------------------
define('UPLOAD_DIR', BASE_PATH . '/uploads/attachments/'); // Nơi lưu tệp
define('MAX_FILE_SIZE', 5 * 1024 * 1024);                  // Tối đa 5MB

// Danh sách phần mở rộng được phép tải lên (whitelist)
$ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];

// Kiểu MIME hợp lệ theo từng phần mở rộng (kiểm tra kép để chống giả mạo đuôi tệp).
// finfo trên một số bản PHP/XAMPP trả về zip / CDFV2 / octet-stream cho tệp Office,
// nên các kiểu đó được chấp nhận riêng cho doc/docx.
$ALLOWED_MIME_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
               'application/zip', 'application/octet-stream'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
];

// -----------------------------------------------------------------------------
// 4. CẤU HÌNH PHÂN TRANG
// -----------------------------------------------------------------------------
define('ITEMS_PER_PAGE', 10); // Số bản ghi hiển thị trên mỗi trang

// Yêu cầu vẫn ở trạng thái "Đã tiếp nhận" từ chừng này ngày trở lên bị đánh dấu "Quá hạn"
define('OVERDUE_DAYS', 3);

// -----------------------------------------------------------------------------
// 5. CẤU HÌNH THỜI GIAN CHỜ CỦA PHIÊN LÀM VIỆC (giây)
// -----------------------------------------------------------------------------
define('SESSION_TIMEOUT_USER', 30 * 60);  // Sinh viên: 30 phút
define('SESSION_TIMEOUT_ADMIN', 60 * 60); // Cán bộ / quản trị: 60 phút

// Chống dò mật khẩu: sai liên tiếp LOGIN_MAX_FAILS lần thì khóa đăng nhập tài khoản đó
define('LOGIN_MAX_FAILS', 5);
define('LOGIN_LOCK_MINUTES', 5);

// -----------------------------------------------------------------------------
// 6. CẤU HÌNH GỬI EMAIL (PHPMailer)
// -----------------------------------------------------------------------------
defined('MAIL_ENABLED') || define('MAIL_ENABLED', true);        // Đặt false để tắt hoàn toàn việc gửi email
defined('MAIL_HOST') || define('MAIL_HOST', 'smtp.gmail.com');
defined('MAIL_PORT') || define('MAIL_PORT', 587);
// CẢNH BÁO: hai hằng dưới đây chỉ là giá trị mẫu. Chỉ đặt MAIL_LOG_ONLY = false
// SAU KHI đã điền email và mật khẩu ứng dụng thật, nếu không việc gửi thư sẽ lỗi.
// Khi chấm/demo, để nguyên MAIL_LOG_ONLY = true — thư được ghi vào logs/email.log.
defined('MAIL_USERNAME') || define('MAIL_USERNAME', 'your-email@gmail.com'); // Thay bằng email thật khi chạy
defined('MAIL_PASSWORD') || define('MAIL_PASSWORD', 'your-app-password');    // Mật khẩu ứng dụng của Gmail
defined('MAIL_SECURE') || define('MAIL_SECURE', 'tls');                    // 'tls' cho cổng 587, 'ssl' cho 465
defined('MAIL_FROM') || define('MAIL_FROM', 'no-reply@ulsa.edu.vn');
define('MAIL_FROM_NAME', APP_DEPARTMENT);

// Ghi log email thay vì gửi thật (rất hữu ích khi demo/không có SMTP)
// Khi bật, nội dung email được ghi vào logs/email.log
defined('MAIL_LOG_ONLY') || define('MAIL_LOG_ONLY', true);

// -----------------------------------------------------------------------------
// 7. CHẾ ĐỘ GỠ LỖI
// -----------------------------------------------------------------------------
defined('DEBUG_MODE') || define('DEBUG_MODE', false); // true khi phát triển, false khi nộp bài/chạy thật

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');   // Không lộ lỗi hệ thống ra ngoài trình duyệt
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php_error.log');
}

// Đặt múi giờ Việt Nam để mọi mốc thời gian hiển thị đúng
date_default_timezone_set('Asia/Ho_Chi_Minh');

// -----------------------------------------------------------------------------
// 8. KHỞI TẠO PHIÊN LÀM VIỆC AN TOÀN
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Cookie phiên chỉ gửi qua HTTP (JavaScript không đọc được) -> chống XSS lấy cắp phiên
    // Chỉ chấp nhận ID phiên do máy chủ cấp, chỉ qua cookie -> chống session fixation
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // Chỉ gửi qua HTTPS khi có HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// -----------------------------------------------------------------------------
// 9. KẾT NỐI CƠ SỞ DỮ LIỆU
// -----------------------------------------------------------------------------
require_once __DIR__ . '/includes/error_page.php'; // Trang báo lỗi dùng chung, không phụ thuộc CSDL

// Bật chế độ ném ngoại lệ để lỗi SQL không bị bỏ qua âm thầm
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, DB_CHARSET); // Bắt buộc: bảo đảm tiếng Việt không lỗi font
} catch (mysqli_sql_exception $e) {
    // Ghi log chi tiết cho quản trị, chỉ hiển thị thông báo thân thiện cho người dùng
    error_log('Loi ket noi CSDL: ' . $e->getMessage());
    render_error_page(503, 'Không thể kết nối cơ sở dữ liệu',
        'Vui lòng kiểm tra lại thông tin trong tệp config.php (hoặc config.local.php) và bảo đảm dịch vụ MySQL đang chạy.');
}
