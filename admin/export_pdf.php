<?php
/**
 * =============================================================================
 * admin/export_pdf.php - Xuất danh sách yêu cầu ra PDF bằng chức năng in của trình duyệt
 * =============================================================================
 * Trang này dựng một bản báo cáo gọn, định dạng sẵn cho khổ A4 nằm ngang, rồi
 * tự mở hộp thoại in. Người dùng chọn máy in "Lưu thành PDF" (Chrome, Edge)
 * hoặc "Microsoft Print to PDF" để lưu tệp.
 *
 * Trình duyệt tự dùng phông chữ của hệ điều hành nên tiếng Việt luôn đủ dấu,
 * không cần thư viện tạo PDF hay nhúng phông chữ ở phía máy chủ.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login();

$data = export_dataset(export_filters());

if (empty($data['rows'])) {
    set_flash('warning', 'Không có dữ liệu nào khớp điều kiện lọc để xuất ra PDF.');
    redirect('manage_requests.php?' . http_build_query($_GET));
}

$title = 'BÁO CÁO DANH SÁCH YÊU CẦU HỖ TRỢ SINH VIÊN';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <!-- Tiêu đề trang được trình duyệt dùng làm tên tệp PDF gợi ý khi lưu -->
    <title>bao_cao_yeu_cau_<?php echo date('Ymd_His'); ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        /* Nền trắng tường minh: nếu để trống, trình duyệt ở chế độ tối sẽ tô nền đen */
        body  { font-family: "Segoe UI", Tahoma, sans-serif; font-size: 12px; color: #24303A; background: #fff; margin: 24px; }
        h1    { font-size: 18px; text-align: center; margin-bottom: 4px; color: #0064A2; }
        .sub  { text-align: center; color: #566573; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th    { background: #0064A2; color: #fff; padding: 8px; text-align: left; font-size: 11px; }
        td    { border-bottom: 1px solid #dfe4e6; padding: 7px 8px; vertical-align: top; }
        tr:nth-child(even) td { background: #f7f9f9; }
        thead { display: table-header-group; } /* Lặp lại dòng tiêu đề bảng ở mỗi trang in */
        tr    { page-break-inside: avoid; }
        /* Giữ màu nền dòng tiêu đề khi in */
        th, tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .foot-note { text-align: center; color: #7f8c8d; margin-top: 18px; }
        .note { background: #fef5e7; border-left: 4px solid #C87F0A; padding: 10px 14px; margin-bottom: 16px; }
        .note button { margin-left: 8px; padding: 4px 12px; cursor: pointer; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body data-autoprint>

<div class="note no-print">
    Hộp thoại in sẽ tự mở. Chọn máy in <strong>"Lưu thành PDF"</strong> hoặc
    <strong>"Microsoft Print to PDF"</strong> để lưu báo cáo thành tệp PDF.
    <button type="button" data-print>Mở lại hộp thoại in</button>
</div>

<h1><?php echo e($title); ?></h1>
<div class="sub">
    <?php echo e(APP_UNIVERSITY); ?> &ndash; <?php echo e(APP_DEPARTMENT); ?><br>
    <?php foreach ($data['subLines'] as $line): ?>
        <?php echo e($line); ?><br>
    <?php endforeach; ?>
</div>

<table>
    <thead>
        <tr>
            <?php foreach ($data['headers'] as $header): ?>
            <th><?php echo e($header); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['rows'] as $row): ?>
        <tr>
            <?php foreach ($row as $cell): ?>
            <td><?php echo e($cell); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p class="foot-note">
    Kết xuất lúc <?php echo date('d/m/Y H:i'); ?> &ndash; <?php echo e(APP_NAME); ?>
</p>

<script src="<?php echo asset('/js/script.js'); ?>"></script>

</body>
</html>
