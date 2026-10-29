<?php
/**
 * =============================================================================
 * admin/export_excel.php - Xuất danh sách yêu cầu ra tệp mở bằng Excel
 * =============================================================================
 * Dùng hàm chuẩn fputcsv() của PHP, không cần thư viện ngoài.
 *
 * Định dạng tệp: phân tách bằng ký tự Tab, mã hóa UTF-16LE có BOM.
 * Đây là cách duy nhất chỉ dùng hàm chuẩn mà Excel mở đúng ở mọi máy:
 *   - Tiếng Việt không lỗi font (Excel nhận ra UTF-16LE nhờ BOM)
 *   - Các cột tách đúng bất kể cài đặt vùng miền của Windows. CSV dùng dấu phẩy
 *     sẽ bị dồn vào một cột trên máy đặt vùng Việt Nam, vì Excel khi đó lấy
 *     dấu chấm phẩy làm ký tự phân cách.
 *
 * Mọi điều kiện lọc đang hiển thị trên màn hình được truyền qua URL và áp dụng
 * y hệt cho tệp xuất ra.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login(); // Chỉ cán bộ và quản trị viên được xuất báo cáo

$data = export_dataset(export_filters());

// Không có dữ liệu thì quay lại kèm thông báo, tránh xuất ra tệp rỗng
if (empty($data['rows'])) {
    set_flash('warning', 'Không có dữ liệu nào khớp điều kiện lọc để xuất ra Excel.');
    redirect('manage_requests.php?' . http_build_query($_GET));
}

// Chống formula injection: ô văn bản bắt đầu bằng = + - @ (hoặc tab/CR) sẽ bị Excel
// hiểu là công thức. Thêm dấu ' phía trước để Excel coi là văn bản thuần.
$safe = function ($cell) {
    return (is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell)) ? "'" . $cell : $cell;
};

// Ghi dữ liệu vào bộ nhớ tạm, thêm cột số thứ tự ở đầu mỗi dòng
$buffer = fopen('php://temp', 'r+');
fputcsv($buffer, array_merge(['STT'], $data['headers']), "\t", '"', '');
foreach ($data['rows'] as $index => $row) {
    fputcsv($buffer, array_merge([$index + 1], array_map($safe, $row)), "\t", '"', '');
}
rewind($buffer);
$content = "\xFF\xFE" . mb_convert_encoding(stream_get_contents($buffer), 'UTF-16LE', 'UTF-8');
fclose($buffer);

header('Content-Type: text/csv; charset=UTF-16LE');
header('Content-Disposition: attachment; filename="bao_cao_yeu_cau_' . date('Ymd_His') . '.csv"');
header('Content-Length: ' . strlen($content));
echo $content;
