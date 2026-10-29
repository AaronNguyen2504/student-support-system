<?php
/**
 * =============================================================================
 * functions.php - Thư viện hàm dùng chung cho toàn hệ thống
 * =============================================================================
 * Mọi tệp PHP khác đều nạp tệp này để tái sử dụng các hàm xử lý:
 *   - Bảo mật: chống XSS, chống CSRF, làm sạch dữ liệu đầu vào
 *   - Truy vấn CSDL an toàn bằng prepared statement
 *   - Xác thực & phân quyền người dùng
 *   - Tải tệp đính kèm, phân trang, định dạng ngày giờ, nhãn trạng thái
 *   - Thông báo nhanh (flash message) giữa các lần chuyển trang
 *
 * Quy ước đặt tên: hàm và biến dùng tiếng Anh, nội dung hiển thị dùng tiếng Việt.
 * =============================================================================
 */

require_once __DIR__ . '/config.php';

/* =============================================================================
 * NHÓM 1: BẢO MẬT ĐẦU VÀO / ĐẦU RA
 * ========================================================================== */

/**
 * Escape dữ liệu trước khi in ra HTML -> chống tấn công XSS.
 * Luôn dùng hàm này thay cho việc echo trực tiếp biến của người dùng.
 *
 * @param  string|null $value Chuỗi cần hiển thị
 * @return string             Chuỗi đã được mã hóa an toàn
 */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Làm sạch dữ liệu nhập: bỏ khoảng trắng thừa và ký tự điều khiển vô hình.
 *
 * @param  string|null $value Dữ liệu thô từ $_POST/$_GET
 * @return string             Dữ liệu đã làm sạch
 */
function clean_input($value)
{
    $value = is_string($value) ? $value : '';
    $value = trim($value);

    // Dữ liệu gửi lên có thể chứa byte không hợp lệ (do lỗi mã hóa hoặc do
    // bị cố ý chèn vào). Phải loại bỏ trước, vì các hàm preg_* với cờ /u sẽ
    // trả về null khi gặp chuỗi UTF-8 hỏng - khi đó mọi kiểm tra phía sau
    // đều sai lệch.
    if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    // Loại bỏ ký tự điều khiển (trừ xuống dòng và tab) để tránh dữ liệu rác
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

    // Bảo đảm luôn trả về chuỗi, không bao giờ trả về null
    return $cleaned === null ? '' : $cleaned;
}

/**
 * Lấy một giá trị từ mảng $_POST đã được làm sạch.
 */
function post($key, $default = '')
{
    return isset($_POST[$key]) ? clean_input($_POST[$key]) : $default;
}

/**
 * Lấy một giá trị từ mảng $_GET đã được làm sạch.
 */
function get($key, $default = '')
{
    return isset($_GET[$key]) ? clean_input($_GET[$key]) : $default;
}

/* =============================================================================
 * NHÓM 2: CHỐNG GIẢ MẠO YÊU CẦU (CSRF)
 * ========================================================================== */

/**
 * Sinh (hoặc lấy lại) token CSRF của phiên hiện tại.
 * Token giữ nguyên trong suốt phiên để người dùng mở nhiều tab vẫn hoạt động.
 *
 * @return string Token dạng chuỗi hex 64 ký tự
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * In ra thẻ input ẩn chứa token CSRF. Gọi hàm này trong mọi biểu mẫu POST.
 */
function csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Token CSRF trong $_POST có khớp với token của phiên hay không (không dừng chương trình).
 */
function csrf_valid()
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Kiểm tra token CSRF gửi lên có khớp với token trong phiên, dừng chương trình
 * nếu không hợp lệ. Dùng ở đầu mỗi khối xử lý POST.
 * hash_equals() so sánh an toàn trước tấn công đo thời gian.
 */
function csrf_check_or_die()
{
    if (!csrf_valid()) {
        render_error_page(419, 'Phiên làm việc không hợp lệ',
            'Mã bảo mật (CSRF token) không đúng hoặc đã hết hạn. Hãy quay lại, tải lại trang và thử lại.');
    }
}

/* =============================================================================
 * NHÓM 3: TRUY VẤN CƠ SỞ DỮ LIỆU AN TOÀN (PREPARED STATEMENT)
 * ========================================================================== */

/**
 * Thực thi một câu lệnh SQL có tham số bằng prepared statement.
 * Đây là hàm nền tảng: mọi truy vấn trong hệ thống đều đi qua đây,
 * nhờ vậy dữ liệu người dùng không bao giờ được nối trực tiếp vào SQL.
 *
 * @param  string $sql    Câu lệnh SQL có dấu ? làm chỗ giữ tham số
 * @param  array  $params Mảng giá trị truyền vào theo đúng thứ tự
 * @param  string $types  Chuỗi kiểu dữ liệu (s=chuỗi, i=số nguyên, d=số thực).
 *                        Bỏ trống thì hệ thống tự suy ra kiểu.
 * @return mysqli_stmt    Đối tượng statement đã thực thi
 */
function db_query($sql, $params = [], $types = '')
{
    global $conn;

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        error_log('Loi prepare SQL: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        render_error_page(500, 'Đã xảy ra lỗi hệ thống', 'Không thể xử lý yêu cầu lúc này. Vui lòng thử lại sau.');
    }

    if (!empty($params)) {
        // Tự suy ra kiểu dữ liệu nếu người gọi không truyền vào
        if ($types === '') {
            foreach ($params as $p) {
                if (is_int($p)) {
                    $types .= 'i';
                } elseif (is_float($p)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    return $stmt;
}

/**
 * Lấy TẤT CẢ bản ghi của một truy vấn dưới dạng mảng kết hợp.
 */
function db_select($sql, $params = [], $types = '')
{
    $stmt   = db_query($sql, $params, $types);
    $result = mysqli_stmt_get_result($stmt);
    $rows   = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Lấy MỘT bản ghi đầu tiên, trả về null nếu không tìm thấy.
 */
function db_select_one($sql, $params = [], $types = '')
{
    $stmt   = db_query($sql, $params, $types);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Lấy một giá trị đơn (ví dụ COUNT(*)) từ cột đầu tiên của dòng đầu tiên.
 */
function db_scalar($sql, $params = [], $types = '')
{
    $row = db_select_one($sql, $params, $types);
    return $row ? reset($row) : null;
}

/**
 * Thực thi lệnh INSERT / UPDATE / DELETE.
 *
 * Lưu ý quan trọng: mysqli_insert_id() chỉ có ý nghĩa với lệnh INSERT.
 * Sau một lệnh UPDATE/DELETE nó vẫn giữ ID của lần INSERT gần nhất trên cùng
 * kết nối, nên phải kiểm tra loại câu lệnh trước khi dùng, nếu không hàm sẽ
 * trả về một ID cũ thay vì số dòng bị ảnh hưởng.
 *
 * @return int ID vừa chèn (với INSERT) hoặc số dòng bị ảnh hưởng (UPDATE/DELETE)
 */
function db_execute($sql, $params = [], $types = '')
{
    global $conn;

    $stmt     = db_query($sql, $params, $types);
    $affected = mysqli_stmt_affected_rows($stmt);

    // Chỉ lấy insert_id khi câu lệnh thực sự bắt đầu bằng INSERT
    $laInsert = stripos(ltrim($sql), 'INSERT') === 0;
    $insertId = $laInsert ? mysqli_insert_id($conn) : 0;

    mysqli_stmt_close($stmt);

    return ($laInsert && $insertId > 0) ? $insertId : $affected;
}

/* =============================================================================
 * NHÓM 4: XÁC THỰC & PHÂN QUYỀN
 * ========================================================================== */

/**
 * Kiểm tra người dùng đã đăng nhập hay chưa.
 */
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

/**
 * Lấy vai trò của người dùng hiện tại: student | staff | admin.
 */
function current_role()
{
    return $_SESSION['role'] ?? '';
}

/**
 * Kiểm tra người dùng hiện tại có quyền quản trị (cán bộ hoặc quản trị viên).
 */
function is_admin()
{
    return in_array(current_role(), ['staff', 'admin'], true);
}

/**
 * Kiểm tra người dùng hiện tại là quản trị viên cấp cao nhất.
 * Chỉ vai trò này mới được xóa yêu cầu và quản lý tài khoản cán bộ.
 */
function is_super_admin()
{
    return current_role() === 'admin';
}

/**
 * Lấy toàn bộ thông tin người dùng đang đăng nhập từ CSDL.
 */
function current_user()
{
    if (!is_logged_in()) {
        return null;
    }
    return db_select_one(
        'SELECT * FROM tbl_users WHERE id = ? LIMIT 1',
        [(int)$_SESSION['user_id']],
        'i'
    );
}

/**
 * Ghi thông tin đăng nhập vào phiên làm việc.
 * Tái tạo ID phiên để chống tấn công cố định phiên (session fixation).
 */
function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
}

/**
 * Hủy toàn bộ phiên làm việc khi người dùng đăng xuất.
 */
function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* =============================================================================
 * NHÓM 5: THÔNG BÁO NHANH (FLASH MESSAGE)
 * ========================================================================== */

/**
 * Lưu một thông báo hiển thị đúng một lần ở trang kế tiếp.
 *
 * @param string $type    success | error | warning | info
 * @param string $message Nội dung tiếng Việt
 */
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * In ra thông báo nhanh (nếu có) rồi xóa khỏi phiên để chỉ hiện đúng một lần.
 */
function render_flash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
}

/**
 * Chuyển hướng trình duyệt và dừng chương trình.
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/* =============================================================================
 * NHÓM 6: NHÃN HIỂN THỊ TIẾNG VIỆT
 * ========================================================================== */

/**
 * Bảng ánh xạ mã trạng thái trong CSDL sang nhãn tiếng Việt.
 */
function status_list()
{
    return [
        'received'   => 'Đã tiếp nhận',
        'processing' => 'Đang xử lý',
        'completed'  => 'Hoàn thành',
    ];
}

/**
 * Đổi mã trạng thái thành nhãn tiếng Việt để hiển thị.
 */
function status_label($status)
{
    $list = status_list();
    return $list[$status] ?? 'Không xác định';
}

/**
 * In ra "viên thuốc" màu tương ứng trạng thái (dùng trong bảng danh sách).
 */
function status_badge($status)
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

/**
 * Mốc thời gian để xét quá hạn: yêu cầu "Đã tiếp nhận" tạo TRƯỚC mốc này là quá hạn
 * (tức là được gửi từ OVERDUE_DAYS ngày trước trở về trước, tính theo ngày lịch).
 */
function overdue_cutoff()
{
    return date('Y-m-d', strtotime('-' . (OVERDUE_DAYS - 1) . ' days')) . ' 00:00:00';
}

/**
 * Ngày lớn nhất (Y-m-d) của yêu cầu quá hạn, dùng làm tham số to_date của bộ lọc
 * để danh sách lọc khớp đúng với số đếm quá hạn.
 */
function overdue_to_date()
{
    return date('Y-m-d', strtotime('-' . OVERDUE_DAYS . ' days'));
}

/**
 * Yêu cầu có quá hạn xử lý không (chỉ xét yêu cầu còn ở trạng thái Đã tiếp nhận).
 *
 * @param array $request Dòng yêu cầu có status và created_at
 */
function is_overdue($request)
{
    return $request['status'] === 'received' && $request['created_at'] < overdue_cutoff();
}

/**
 * Nhãn trạng thái kèm nhãn "Quá hạn" khi cần (dùng trong bảng danh sách của cán bộ).
 */
function status_badges($request)
{
    $html = status_badge($request['status']);
    if (is_overdue($request)) {
        $html .= ' <span class="badge badge-overdue">Quá hạn</span>';
    }
    return $html;
}

/**
 * Bảng ánh xạ vai trò sang tiếng Việt.
 */
function role_label($role)
{
    $list = [
        'student' => 'Sinh viên',
        'staff'   => 'Cán bộ hỗ trợ',
        'admin'   => 'Quản trị viên',
    ];
    return $list[$role] ?? $role;
}

/**
 * Định dạng ngày giờ kiểu Việt Nam: dd/mm/yyyy HH:ii.
 */
function format_datetime($datetime, $withTime = true)
{
    if (empty($datetime)) {
        return '';
    }
    $ts = strtotime($datetime);
    return $withTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

/* =============================================================================
 * NHÓM 7: KIỂM TRA DỮ LIỆU ĐẦU VÀO
 * ========================================================================== */

/**
 * Kiểm tra định dạng email.
 */
function is_valid_email($email)
{
    // Giới hạn 255 ký tự cho khớp cột email VARCHAR(255)
    return strlen($email) <= 255 && (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Kiểm tra số điện thoại Việt Nam (10-11 chữ số, bắt đầu bằng 0).
 */
function is_valid_phone($phone)
{
    return (bool)preg_match('/^0\d{9,10}$/', $phone);
}

/**
 * Kiểm tra mã sinh viên: 6-20 ký tự chữ hoặc số.
 */
function is_valid_student_id($studentId)
{
    return (bool)preg_match('/^[A-Za-z0-9]{6,20}$/', $studentId);
}

/**
 * Kiểm tra độ mạnh mật khẩu: tối thiểu 8 ký tự, có chữ hoa, chữ thường và chữ số.
 *
 * @return string Chuỗi rỗng nếu hợp lệ, ngược lại là thông báo lỗi tiếng Việt
 */
function validate_password_strength($password)
{
    if (mb_strlen($password) < 8) {
        return 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    // bcrypt chỉ dùng 72 byte đầu: phần dài hơn sẽ bị bỏ qua mà người dùng không biết
    if (strlen($password) > 72) {
        return 'Mật khẩu không được dài quá 72 ký tự.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Mật khẩu phải có ít nhất một chữ cái in hoa.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Mật khẩu phải có ít nhất một chữ cái thường.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Mật khẩu phải có ít nhất một chữ số.';
    }
    return '';
}

/* =============================================================================
 * NHÓM 8: XỬ LÝ TỆP ĐÍNH KÈM
 * ========================================================================== */

/**
 * Đường dẫn tới tệp tĩnh kèm ?v=<thời điểm sửa tệp>: .htaccess cho trình duyệt cache lâu,
 * sửa tệp thì URL đổi nên người dùng luôn nhận bản mới.
 *
 * @param string $path Đường dẫn từ thư mục gốc ứng dụng, ví dụ '/css/style.css'
 */
function asset($path)
{
    $mtime = @filemtime(BASE_PATH . $path);
    return BASE_URL . $path . ($mtime ? '?v=' . $mtime : '');
}

/**
 * Đường dẫn tải tệp đính kèm. Tệp không truy cập trực tiếp được (uploads/attachments/.htaccess
 * chặn), mọi lượt tải đều đi qua download.php để kiểm tra quyền.
 *
 * @param int      $requestId  ID yêu cầu
 * @param int|null $responseId ID phản hồi (null = tệp của sinh viên đính kèm yêu cầu)
 */
function attachment_url($requestId, $responseId = null)
{
    $url = BASE_URL . '/download.php?id=' . (int)$requestId;
    return $responseId === null ? $url : $url . '&response=' . (int)$responseId;
}

/**
 * Kiểm tra và lưu tệp người dùng tải lên.
 *
 * Các bước kiểm tra bắt buộc (theo yêu cầu bảo mật):
 *   1. Mã lỗi trả về từ PHP
 *   2. Kích thước tệp (tối đa 5MB)
 *   3. Phần mở rộng nằm trong danh sách trắng
 *   4. Kiểu MIME thật của tệp (đọc nội dung, không tin phần mở rộng)
 *   5. Đổi tên tệp thành chuỗi ngẫu nhiên + mốc thời gian để chống ghi đè
 *      và chống thực thi mã độc qua tên tệp
 *
 * @param  array  $file   Một phần tử của mảng $_FILES
 * @param  string &$error Biến nhận thông báo lỗi tiếng Việt (tham chiếu)
 * @return string|null    Tên tệp đã lưu, hoặc null nếu không có tệp / có lỗi
 */
function handle_file_upload($file, &$error)
{
    global $ALLOWED_EXTENSIONS, $ALLOWED_MIME_TYPES;

    $error = '';

    // Không chọn tệp -> hợp lệ, vì tệp đính kèm là tùy chọn
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Bước 1: kiểm tra mã lỗi tải lên của PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Tải tệp lên thất bại (mã lỗi ' . (int)$file['error'] . '). Vui lòng thử lại.';
        return null;
    }

    // Bước 2: kiểm tra kích thước
    if ($file['size'] > MAX_FILE_SIZE) {
        $error = 'Dung lượng tệp vượt quá 5MB. Vui lòng chọn tệp nhỏ hơn.';
        return null;
    }
    if ($file['size'] <= 0) {
        $error = 'Tệp rỗng, không thể tải lên.';
        return null;
    }

    // Bước 3: kiểm tra phần mở rộng theo danh sách trắng
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $ALLOWED_EXTENSIONS, true)) {
        $error = 'Định dạng tệp không được hỗ trợ. Chỉ chấp nhận: '
               . implode(', ', $ALLOWED_EXTENSIONS) . '.';
        return null;
    }

    // Bước 4: kiểm tra kiểu MIME thật (đọc nội dung tệp tạm)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $ALLOWED_MIME_TYPES[$extension] ?? [], true)) {
        $error = 'Nội dung tệp không khớp với định dạng cho phép.';
        return null;
    }

    // Bước 5: sinh tên tệp mới an toàn, không dùng lại tên gốc của người dùng
    $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
        $error = 'Không thể lưu tệp lên máy chủ. Vui lòng kiểm tra quyền ghi thư mục uploads.';
        return null;
    }

    return $newName;
}

/* =============================================================================
 * NHÓM 9: PHÂN TRANG
 * ========================================================================== */

/**
 * Tính toán thông tin phân trang.
 *
 * @param  int $totalItems  Tổng số bản ghi
 * @param  int $currentPage Trang hiện tại (bắt đầu từ 1)
 * @param  int $perPage     Số bản ghi mỗi trang
 * @return array            Mảng gồm total_pages, current_page, offset, per_page
 */
function paginate($totalItems, $currentPage, $perPage = ITEMS_PER_PAGE)
{
    $totalPages  = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = max(1, min((int)$currentPage, $totalPages));
    return [
        'total_items'  => (int)$totalItems,
        'total_pages'  => $totalPages,
        'current_page' => $currentPage,
        'per_page'     => $perPage,
        'offset'       => ($currentPage - 1) * $perPage,
    ];
}

/**
 * In ra thanh phân trang, giữ nguyên các tham số lọc đang có trên URL.
 *
 * @param array  $pagination Kết quả của hàm paginate()
 * @param string $baseUrl    Tên tệp đích, ví dụ 'index.php'
 */
function render_pagination($pagination, $baseUrl)
{
    if ($pagination['total_pages'] <= 1) {
        return;
    }

    // Giữ lại toàn bộ tham số lọc hiện tại, chỉ thay đổi tham số 'page'
    $query = $_GET;
    $link  = function ($page) use ($query, $baseUrl) {
        $query['page'] = $page;
        return e($baseUrl . '?' . http_build_query($query));
    };

    $current = $pagination['current_page'];
    $total   = $pagination['total_pages'];

    echo '<nav class="pagination" aria-label="Phân trang">';

    if ($current > 1) {
        echo '<a href="' . $link($current - 1) . '">&laquo; Trước</a>';
    }

    // Chỉ hiển thị tối đa 5 số trang xung quanh trang hiện tại cho gọn
    $start = max(1, $current - 2);
    $end   = min($total, $start + 4);
    $start = max(1, $end - 4);

    if ($start > 1) {
        echo '<a href="' . $link(1) . '">1</a>';
        if ($start > 2) {
            echo '<span class="dots">&hellip;</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current) {
            echo '<span class="current">' . $i . '</span>';
        } else {
            echo '<a href="' . $link($i) . '">' . $i . '</a>';
        }
    }

    if ($end < $total) {
        if ($end < $total - 1) {
            echo '<span class="dots">&hellip;</span>';
        }
        echo '<a href="' . $link($total) . '">' . $total . '</a>';
    }

    if ($current < $total) {
        echo '<a href="' . $link($current + 1) . '">Sau &raquo;</a>';
    }

    echo '</nav>';
}

/* =============================================================================
 * NHÓM 10: NGHIỆP VỤ YÊU CẦU HỖ TRỢ
 * ========================================================================== */

/**
 * Lấy danh sách toàn bộ danh mục yêu cầu, sắp xếp theo thứ tự hiển thị.
 */
function get_categories()
{
    return db_select('SELECT * FROM tbl_categories ORDER BY sort_order ASC, id ASC');
}

/**
 * Ghi nhật ký thay đổi trạng thái của một yêu cầu.
 *
 * @param int    $requestId ID yêu cầu
 * @param string $oldStatus Trạng thái cũ (null nếu là lần tạo mới)
 * @param string $newStatus Trạng thái mới
 * @param int    $changedBy ID người thực hiện thay đổi
 */
function log_status_change($requestId, $oldStatus, $newStatus, $changedBy)
{
    db_execute(
        'INSERT INTO tbl_status_log (request_id, old_status, new_status, changed_by)
         VALUES (?, ?, ?, ?)',
        [(int)$requestId, $oldStatus, $newStatus, (int)$changedBy],
        'issi'
    );
}

/**
 * Chuẩn hóa from_date/to_date: bỏ giá trị không phải ngày Y-m-d hợp lệ,
 * và đổi chỗ nếu from_date > to_date. Gọi lại nhiều lần vẫn cho cùng kết quả.
 */
function normalize_date_filters($filters)
{
    foreach (['from_date', 'to_date'] as $key) {
        $value = $filters[$key] ?? '';
        $date  = DateTime::createFromFormat('!Y-m-d', (string)$value);
        $filters[$key] = ($date && $date->format('Y-m-d') === $value) ? $value : '';
    }
    if ($filters['from_date'] !== '' && $filters['to_date'] !== ''
        && $filters['from_date'] > $filters['to_date']) {
        [$filters['from_date'], $filters['to_date']] = [$filters['to_date'], $filters['from_date']];
    }
    return $filters;
}

/**
 * Xây dựng mệnh đề WHERE động cho danh sách yêu cầu.
 * Trả về mảng [chuỗi điều kiện, mảng tham số] để ghép vào prepared statement.
 * Lưu ý: chỉ tên cột do lập trình viên viết mới xuất hiện trong SQL,
 * toàn bộ giá trị của người dùng đều đi qua dấu ? nên không thể tiêm SQL.
 *
 * @param array $filters Các bộ lọc: status, category_id, keyword, from_date, to_date, user_id
 */
function build_request_filters($filters)
{
    $filters = normalize_date_filters($filters);
    $where   = [];
    $params  = [];

    if (!empty($filters['user_id'])) {
        $where[]  = 'r.user_id = ?';
        $params[] = (int)$filters['user_id'];
    }
    if (!empty($filters['status']) && array_key_exists($filters['status'], status_list())) {
        $where[]  = 'r.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['category_id'])) {
        $where[]  = 'r.category_id = ?';
        $params[] = (int)$filters['category_id'];
    }
    if (!empty($filters['keyword'])) {
        // Tìm theo tiêu đề yêu cầu, họ tên hoặc mã sinh viên.
        // Escape % và _ để người dùng gõ ký tự đó không khớp mọi dòng.
        $where[]  = "(r.title LIKE ? ESCAPE '!' OR u.full_name LIKE ? ESCAPE '!' OR u.student_id LIKE ? ESCAPE '!')";
        $like     = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['keyword']) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['from_date'])) {
        // So sánh trực tiếp trên cột (không bọc DATE()) để dùng được index created_at
        $where[]  = 'r.created_at >= ?';
        $params[] = $filters['from_date'];
    }
    if (!empty($filters['to_date'])) {
        $where[]  = 'r.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $params[] = $filters['to_date'];
    }

    $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    return [$clause, $params];
}

/**
 * Đếm tổng số yêu cầu khớp bộ lọc (phục vụ phân trang).
 */
function count_requests($filters)
{
    list($clause, $params) = build_request_filters($filters);
    $sql = 'SELECT COUNT(*) AS total
            FROM tbl_requests r
            JOIN tbl_users u ON u.id = r.user_id' . $clause;
    return (int)db_scalar($sql, $params);
}

/**
 * Lấy danh sách yêu cầu theo bộ lọc, có phân trang.
 *
 * @param array    $filters Bộ lọc (xem build_request_filters)
 * @param int|null $limit   Số bản ghi tối đa (null = lấy tất cả, dùng khi xuất báo cáo)
 * @param int      $offset  Vị trí bắt đầu
 */
function get_requests($filters, $limit = ITEMS_PER_PAGE, $offset = 0)
{
    list($clause, $params) = build_request_filters($filters);

    $sql = 'SELECT r.*, u.full_name, u.student_id, u.email, c.category_name
            FROM tbl_requests r
            JOIN tbl_users u ON u.id = r.user_id
            JOIN tbl_categories c ON c.id = r.category_id'
         . $clause . ' ORDER BY r.created_at DESC, r.id DESC';

    if ($limit !== null) {
        // LIMIT/OFFSET vẫn truyền qua tham số ràng buộc, không nối chuỗi thô
        $sql     .= ' LIMIT ? OFFSET ?';
        $params[] = (int)$limit;
        $params[] = (int)$offset;
    }

    return db_select($sql, $params);
}

/**
 * Lấy chi tiết một yêu cầu kèm thông tin sinh viên và danh mục.
 */
function get_request_detail($requestId)
{
    return db_select_one(
        'SELECT r.*, u.full_name, u.student_id, u.email, u.phone, c.category_name
         FROM tbl_requests r
         JOIN tbl_users u ON u.id = r.user_id
         JOIN tbl_categories c ON c.id = r.category_id
         WHERE r.id = ? LIMIT 1',
        [(int)$requestId],
        'i'
    );
}

/**
 * Lấy toàn bộ phản hồi của cán bộ cho một yêu cầu.
 */
function get_responses($requestId)
{
    return db_select(
        'SELECT rs.*, u.full_name AS staff_name
         FROM tbl_responses rs
         JOIN tbl_users u ON u.id = rs.staff_id
         WHERE rs.request_id = ?
         ORDER BY rs.response_date ASC',
        [(int)$requestId],
        'i'
    );
}

/**
 * Lấy nhật ký trạng thái của một yêu cầu (dùng để vẽ dòng thời gian).
 */
function get_status_logs($requestId)
{
    return db_select(
        'SELECT sl.*, u.full_name AS changed_by_name
         FROM tbl_status_log sl
         JOIN tbl_users u ON u.id = sl.changed_by
         WHERE sl.request_id = ?
         ORDER BY sl.changed_at ASC, sl.id ASC',
        [(int)$requestId],
        'i'
    );
}

/**
 * In ra danh sách phản hồi của cán bộ (dùng chung cho trang chi tiết của
 * sinh viên, trang chi tiết và trang xử lý của cán bộ).
 *
 * @param array $responses Kết quả của get_responses()
 */
function render_responses($responses)
{
    foreach ($responses as $response) {
        echo '<div class="response-item">'
           . '<div class="response-meta"><strong>' . e($response['staff_name']) . '</strong> - '
           . e(format_datetime($response['response_date'])) . '</div>'
           . '<div class="response-text">' . e($response['response_text']) . '</div>';
        if (!empty($response['attachment_path'])) {
            echo '<div class="mt-8"><a href="' . e(attachment_url($response['request_id'], $response['id']))
               . '" target="_blank" rel="noopener">Tệp đính kèm của cán bộ</a></div>';
        }
        echo '</div>';
    }
}

/**
 * Xóa yêu cầu CHƯA được xử lý (còn "Đã tiếp nhận" và chưa có phản hồi nào).
 * Xóa yêu cầu kéo theo mất phản hồi và nhật ký (CASCADE), nên yêu cầu đã qua xử lý
 * là bằng chứng của quy trình và phải giữ lại.
 *
 * @param  int      $requestId ID yêu cầu
 * @param  int|null $ownerId   Nếu có: chỉ xóa khi yêu cầu thuộc người này (sinh viên tự hủy)
 * @return string              'ok' | 'notfound' | 'processed'
 */
function delete_unprocessed_request($requestId, $ownerId = null)
{
    $target = db_select_one(
        'SELECT user_id, attachment_path FROM tbl_requests WHERE id = ?',
        [(int)$requestId],
        'i'
    );
    if (!$target || ($ownerId !== null && (int)$target['user_id'] !== (int)$ownerId)) {
        return 'notfound';
    }

    // Điều kiện nằm ngay trong câu DELETE để không bị chạy đua với cán bộ đang xử lý
    $deleted = db_execute(
        'DELETE FROM tbl_requests
          WHERE id = ? AND status = ?
            AND NOT EXISTS (SELECT 1 FROM tbl_responses WHERE request_id = ?)',
        [(int)$requestId, 'received', (int)$requestId],
        'isi'
    );
    if (!$deleted) {
        return 'processed';
    }

    if (!empty($target['attachment_path'])) {
        @unlink(UPLOAD_DIR . basename($target['attachment_path']));
    }
    return 'ok';
}

/**
 * Sinh mã yêu cầu dạng YC-000045 để hiển thị cho thân thiện.
 */
function request_code($id)
{
    return 'YC-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

/* =============================================================================
 * NHÓM 11: CHUẨN BỊ DỮ LIỆU XUẤT BÁO CÁO (dùng chung cho Excel và PDF)
 * ========================================================================== */

/**
 * Đọc bộ lọc báo cáo từ tham số trên URL.
 * Nhờ dùng chung hàm này, tệp Excel và tệp PDF luôn xuất ra cùng một tập dữ liệu
 * với điều kiện lọc mà cán bộ đang xem trên màn hình.
 */
function export_filters()
{
    return normalize_date_filters([
        'status'      => get('status'),
        'category_id' => (int)get('category_id'),
        'keyword'     => get('keyword'),
        'from_date'   => get('from_date'),
        'to_date'     => get('to_date'),
    ]);
}

/**
 * Lấy toàn bộ dữ liệu cần thiết cho một bản báo cáo.
 *
 * @param  array $filters Bộ lọc từ export_filters()
 * @return array          Mảng gồm: headers (tiêu đề cột), rows (dữ liệu),
 *                        subLines (các dòng mô tả điều kiện lọc)
 */
function export_dataset($filters)
{
    // limit = null -> lấy tất cả bản ghi khớp bộ lọc, không phân trang
    $requests = get_requests($filters, null);

    $headers = [
        'Mã yêu cầu', 'Họ và tên', 'Mã sinh viên', 'Danh mục',
        'Tiêu đề', 'Trạng thái', 'Ngày gửi', 'Cập nhật cuối',
    ];

    $rows = [];
    foreach ($requests as $request) {
        $rows[] = [
            request_code($request['id']),
            $request['full_name'],
            $request['student_id'],
            $request['category_name'],
            $request['title'],
            status_label($request['status']),
            format_datetime($request['created_at']),
            format_datetime($request['updated_at']),
        ];
    }

    // Mô tả lại điều kiện lọc để người đọc báo cáo biết dữ liệu được lấy thế nào
    $conditions = [];
    if (!empty($filters['status'])) {
        $conditions[] = 'Trạng thái: ' . status_label($filters['status']);
    }
    if (!empty($filters['category_id'])) {
        $categoryName = db_scalar(
            'SELECT category_name FROM tbl_categories WHERE id = ?',
            [(int)$filters['category_id']],
            'i'
        );
        if ($categoryName) {
            $conditions[] = 'Danh mục: ' . $categoryName;
        }
    }
    if (!empty($filters['keyword'])) {
        $conditions[] = 'Từ khóa: "' . $filters['keyword'] . '"';
    }
    if (!empty($filters['from_date'])) {
        $conditions[] = 'Từ ngày: ' . format_datetime($filters['from_date'], false);
    }
    if (!empty($filters['to_date'])) {
        $conditions[] = 'Đến ngày: ' . format_datetime($filters['to_date'], false);
    }

    $subLines = [
        'Tổng số yêu cầu: ' . count($rows),
        empty($conditions) ? 'Phạm vi: toàn bộ dữ liệu' : 'Điều kiện lọc: ' . implode('; ', $conditions),
    ];

    return ['headers' => $headers, 'rows' => $rows, 'subLines' => $subLines];
}

