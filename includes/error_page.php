<?php
/**
 * =============================================================================
 * includes/error_page.php - Trang báo lỗi dùng chung (403, 404, 419, 500...)
 * =============================================================================
 * Độc lập với CSDL và với functions.php: được nạp từ config.php nên vẫn dùng được
 * khi kết nối CSDL thất bại. Chỉ cần các hằng BASE_URL, APP_SHORT_NAME.
 * =============================================================================
 */

/**
 * In trang lỗi có bố cục thống nhất rồi dừng chương trình.
 *
 * @param int    $code      Mã HTTP (403, 404, 419, 500...)
 * @param string $title     Tiêu đề ngắn
 * @param string $message   Giải thích và cách khắc phục (văn bản thường, tự được escape)
 * @param string $linkUrl   Liên kết quay lại (bỏ trống = về trang chủ)
 * @param string $linkLabel Nhãn của liên kết
 */
function render_error_page($code, $title, $message, $linkUrl = '', $linkLabel = 'Về trang chủ')
{
    $h = function ($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    if ($linkUrl === '') {
        $linkUrl = BASE_URL . '/index.php';
    }

    if (!headers_sent()) {
        http_response_code((int)$code);
    }
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $h($title); ?> - <?php echo $h(APP_SHORT_NAME); ?></title>
    <link rel="icon" type="image/png" href="<?php echo $h(APP_LOGO); ?>">
    <link rel="stylesheet" href="<?php echo $h(BASE_URL); ?>/css/style.css">
</head>
<body>
<main class="error-page">
    <p class="error-code"><?php echo (int)$code; ?></p>
    <h1><?php echo $h($title); ?></h1>
    <p><?php echo $h($message); ?></p>
    <a class="btn btn-primary" href="<?php echo $h($linkUrl); ?>"><?php echo $h($linkLabel); ?></a>
</main>
</body>
</html>
<?php
    exit;
}
