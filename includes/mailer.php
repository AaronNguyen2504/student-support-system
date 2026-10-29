<?php
/**
 * =============================================================================
 * includes/mailer.php - Gửi email thông báo bằng thư viện PHPMailer
 * =============================================================================
 * Thư viện PHPMailer được đặt sẵn trong thư mục lib/PHPMailer (không cần Composer).
 *
 * Hệ thống gửi email trong 3 tình huống:
 *   1. Sinh viên đăng ký tài khoản thành công      -> send_welcome_email()
 *   2. Sinh viên gửi yêu cầu hỗ trợ mới            -> send_request_created_email()
 *   3. Cán bộ cập nhật trạng thái / phản hồi       -> send_status_update_email()
 *
 * CHẾ ĐỘ THỬ NGHIỆM: khi hằng MAIL_LOG_ONLY = true trong config.php, nội dung
 * email được ghi vào logs/email.log thay vì gửi thật. Nhờ vậy có thể demo và
 * chấm bài mà không cần máy chủ SMTP. Đặt MAIL_LOG_ONLY = false và điền
 * MAIL_USERNAME / MAIL_PASSWORD để gửi email thật qua Gmail.
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

// Nạp 3 tệp nguồn của PHPMailer theo cách thủ công (không dùng Composer autoload)
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Hàm gửi email nền tảng: mọi email của hệ thống đều đi qua hàm này.
 *
 * @param  string $toEmail Địa chỉ người nhận
 * @param  string $toName  Tên người nhận
 * @param  string $subject Tiêu đề (tiếng Việt có dấu)
 * @param  string $bodyHtml Nội dung dạng HTML
 * @return bool             true nếu gửi (hoặc ghi log) thành công
 */
function send_email($toEmail, $toName, $subject, $bodyHtml)
{
    // Cho phép tắt hoàn toàn tính năng email từ config.php
    if (!MAIL_ENABLED) {
        return false;
    }

    // Không gửi tới địa chỉ sai định dạng
    if (!is_valid_email($toEmail)) {
        error_log('Email khong hop le: ' . $toEmail);
        return false;
    }

    // --- Chế độ ghi log (dùng khi demo, không có SMTP) --------------------
    if (MAIL_LOG_ONLY) {
        return log_email($toEmail, $toName, $subject, $bodyHtml);
    }

    // --- Chế độ gửi thật qua SMTP ----------------------------------------
    $mail = new PHPMailer(true); // true = ném ngoại lệ khi có lỗi

    try {
        // Cấu hình máy chủ gửi thư
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SECURE; // 'tls' hoặc 'ssl'
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';     // Bắt buộc để tiếng Việt không lỗi font
        $mail->Encoding   = 'base64';

        // Người gửi và người nhận
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Nội dung
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_template($subject, $bodyHtml);
        // Bản rút gọn dạng văn bản thuần cho phần mềm đọc thư không hỗ trợ HTML
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $bodyHtml)));

        $mail->send();
        return true;

    } catch (PHPMailerException $e) {
        // Lỗi gửi email không được phép làm hỏng nghiệp vụ chính (tạo/cập nhật yêu cầu)
        error_log('Loi gui email toi ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Ghi nội dung email ra tệp logs/email.log (chế độ thử nghiệm).
 */
function log_email($toEmail, $toName, $subject, $bodyHtml)
{
    $logDir = BASE_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $line = str_repeat('=', 78) . "\n"
          . 'Thời gian : ' . date('d/m/Y H:i:s') . "\n"
          . 'Người nhận: ' . $toName . ' <' . $toEmail . ">\n"
          . 'Tiêu đề   : ' . $subject . "\n"
          . str_repeat('-', 78) . "\n"
          . trim(strip_tags(str_replace(['</p>', '<br>', '</li>'], "\n", $bodyHtml))) . "\n\n";

    return (bool)file_put_contents($logDir . '/email.log', $line, FILE_APPEND);
}

/**
 * Khung HTML chung cho mọi email (tiêu đề, nội dung, chân thư).
 *
 * @param  string $title   Tiêu đề hiển thị trong thân thư
 * @param  string $content Nội dung HTML
 * @return string          Mã HTML hoàn chỉnh
 */
function email_template($title, $content)
{
    return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:20px;background:#ecf0f1;font-family:Segoe UI,Tahoma,sans-serif;color:#24303A;line-height:1.6">
  <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden">
    <div style="background:#0064A2;color:#ffffff;padding:20px">
      <h2 style="margin:0;font-size:18px">' . e(APP_DEPARTMENT) . '</h2>
      <p style="margin:4px 0 0;font-size:13px;color:#cfe3f0">' . e(APP_UNIVERSITY) . '</p>
    </div>
    <div style="padding:24px">
      <h3 style="margin-top:0;color:#0064A2">' . e($title) . '</h3>
      ' . $content . '
    </div>
    <div style="background:#ecf0f1;padding:16px 24px;font-size:12px;color:#7f8c8d">
      Đây là email tự động từ ' . e(APP_NAME) . '. Vui lòng không trả lời email này.<br>
      Mọi thắc mắc xin liên hệ Phòng Công Tác Sinh Viên: ctsv@ulsa.edu.vn
    </div>
  </div>
</body></html>';
}

/**
 * Email chào mừng sau khi sinh viên đăng ký tài khoản thành công.
 *
 * @param array $user Bản ghi người dùng vừa tạo
 */
function send_welcome_email($user)
{
    $subject = 'Đăng ký tài khoản thành công - ' . APP_SHORT_NAME;

    $body = '<p>Chào <strong>' . e($user['full_name']) . '</strong>,</p>
             <p>Tài khoản của bạn trên ' . e(APP_NAME) . ' đã được tạo thành công.</p>
             <ul>
               <li>Email đăng nhập: <strong>' . e($user['email']) . '</strong></li>
               <li>Mã sinh viên: <strong>' . e($user['student_id']) . '</strong></li>
             </ul>
             <p>Từ bây giờ bạn có thể gửi yêu cầu hỗ trợ và theo dõi tiến độ xử lý trực tuyến.</p>
             <p>Trân trọng,<br>' . e(APP_DEPARTMENT) . '</p>';

    return send_email($user['email'], $user['full_name'], $subject, $body);
}

/**
 * Email xác nhận đã tiếp nhận yêu cầu hỗ trợ mới.
 *
 * @param array $user    Sinh viên gửi yêu cầu
 * @param array $request Bản ghi yêu cầu (kèm tên danh mục)
 */
function send_request_created_email($user, $request)
{
    $subject = 'Đã tiếp nhận yêu cầu ' . request_code($request['id']);

    $body = '<p>Chào <strong>' . e($user['full_name']) . '</strong>,</p>
             <p>' . e(APP_DEPARTMENT) . ' đã tiếp nhận yêu cầu hỗ trợ của bạn với thông tin sau:</p>
             <table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">
               <tr><td style="background:#ecf0f1;width:35%">Mã yêu cầu</td>
                   <td><strong>' . e(request_code($request['id'])) . '</strong></td></tr>
               <tr><td style="background:#ecf0f1">Danh mục</td>
                   <td>' . e($request['category_name']) . '</td></tr>
               <tr><td style="background:#ecf0f1">Tiêu đề</td>
                   <td>' . e($request['title']) . '</td></tr>
               <tr><td style="background:#ecf0f1">Thời gian gửi</td>
                   <td>' . e(format_datetime($request['created_at'])) . '</td></tr>
               <tr><td style="background:#ecf0f1">Trạng thái</td>
                   <td>' . e(status_label($request['status'])) . '</td></tr>
             </table>
             <p style="margin-top:16px">Yêu cầu sẽ được xử lý trong vòng 3-5 ngày làm việc.
                Bạn có thể đăng nhập hệ thống để theo dõi tiến độ bất cứ lúc nào.</p>
             <p>Trân trọng,<br>' . e(APP_DEPARTMENT) . '</p>';

    return send_email($user['email'], $user['full_name'], $subject, $body);
}

/**
 * Email thông báo khi cán bộ cập nhật trạng thái hoặc gửi phản hồi.
 *
 * @param array  $request      Yêu cầu (đã JOIN thông tin sinh viên)
 * @param string $newStatus    Trạng thái mới
 * @param string $responseText Nội dung phản hồi (có thể để trống)
 */
function send_status_update_email($request, $newStatus, $responseText = '')
{
    $subject = 'Cập nhật yêu cầu ' . request_code($request['id'])
             . ' - ' . status_label($newStatus);

    $body = '<p>Chào <strong>' . e($request['full_name']) . '</strong>,</p>
             <p>Yêu cầu <strong>' . e(request_code($request['id'])) . '</strong> &ndash; "'
             . e($request['title']) . '" vừa được cập nhật.</p>
             <p style="font-size:15px">Trạng thái hiện tại:
                <strong style="color:#0064A2">' . e(status_label($newStatus)) . '</strong></p>';

    if ($responseText !== '') {
        $body .= '<div style="border-left:4px solid #1F6FA8;background:#f5fafd;padding:12px 16px;margin:16px 0">
                    <strong>Phản hồi từ ' . e(APP_DEPARTMENT) . ':</strong><br>'
                 . nl2br(e($responseText)) .
                 '</div>';
    }

    $body .= '<p>Vui lòng đăng nhập hệ thống để xem chi tiết đầy đủ.</p>
              <p>Trân trọng,<br>' . e(APP_DEPARTMENT) . '</p>';

    return send_email($request['email'], $request['full_name'], $subject, $body);
}
