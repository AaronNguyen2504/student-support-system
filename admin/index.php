<?php
/**
 * =============================================================================
 * admin/index.php - Bảng điều khiển của Phòng Công Tác Sinh Viên
 * =============================================================================
 * Hiển thị:
 *   1. Bốn ô thống kê: tổng số, đã tiếp nhận, đang xử lý, hoàn thành
 *   2. Biểu đồ cột số yêu cầu theo danh mục (vẽ bằng CSS, không cần thư viện)
 *   3. Số yêu cầu mới trong 7 ngày gần nhất và mức độ hài lòng trung bình
 *   4. Bảng 5 yêu cầu mới nhất kèm liên kết xử lý nhanh
 *
 * Tất cả số liệu đều lấy bằng câu lệnh tổng hợp (COUNT/AVG) để trang tải nhanh,
 * không nạp toàn bộ bản ghi rồi mới đếm trong PHP.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login(); // Chỉ cán bộ và quản trị viên được vào

/* -----------------------------------------------------------------------------
 * 1. THỐNG KÊ THEO TRẠNG THÁI
 * -------------------------------------------------------------------------- */
// Một truy vấn gộp lấy luôn số yêu cầu 7 ngày gần đây và số liệu đánh giá hài lòng
$counts      = ['received' => 0, 'processing' => 0, 'completed' => 0];
$last7Days   = 0;
$ratedCount  = 0;
$ratingTotal = 0;
$overdueCount = 0;   // Yêu cầu Đã tiếp nhận quá OVERDUE_DAYS ngày mà chưa ai xử lý
$statusRows  = db_select(
    'SELECT status,
            COUNT(*) AS total,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS last7,
            SUM(created_at < ?) AS overdue,
            COUNT(rating) AS rated,
            COALESCE(SUM(rating), 0) AS rating_total
       FROM tbl_requests GROUP BY status',
    [overdue_cutoff()]
);
foreach ($statusRows as $row) {
    $counts[$row['status']] = (int)$row['total'];
    $last7Days   += (int)$row['last7'];
    if ($row['status'] === 'received') {
        $overdueCount = (int)$row['overdue'];
    }
    $ratedCount  += (int)$row['rated'];
    $ratingTotal += (int)$row['rating_total'];
}
$avgRating     = $ratedCount > 0 ? number_format($ratingTotal / $ratedCount, 1, '.', '') : null;
$totalRequests = array_sum($counts);

/* -----------------------------------------------------------------------------
 * 2. SỐ YÊU CẦU THEO DANH MỤC (dùng LEFT JOIN để danh mục chưa có yêu cầu vẫn hiện)
 * -------------------------------------------------------------------------- */
$byCategory = db_select(
    'SELECT c.category_name, COUNT(r.id) AS total
       FROM tbl_categories c
       LEFT JOIN tbl_requests r ON r.category_id = c.id
      GROUP BY c.id, c.category_name
      ORDER BY c.sort_order ASC'
);
// Giá trị lớn nhất dùng để quy đổi chiều dài cột biểu đồ
$maxCategory = 0;
foreach ($byCategory as $row) {
    $maxCategory = max($maxCategory, (int)$row['total']);
}

/* -----------------------------------------------------------------------------
 * 3. CHỈ SỐ KHÁC
 * -------------------------------------------------------------------------- */
$totalUsers = (int)db_scalar('SELECT COUNT(*) FROM tbl_users WHERE role = ?', ['student'], 's');

/* -----------------------------------------------------------------------------
 * 4. NĂM YÊU CẦU MỚI NHẤT
 * -------------------------------------------------------------------------- */
$recentRequests = get_requests([], 5, 0);

$page_title = 'Bảng điều khiển';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Bảng điều khiển - <?php echo e(APP_DEPARTMENT); ?></h1>
    <div>
        <a class="btn" href="manage_requests.php">Quản lý yêu cầu</a>
        <a class="btn btn-primary" href="reports.php">Xem báo cáo</a>
    </div>
</div>

<!-- ===================== Ô THỐNG KÊ ===================== -->
<section class="stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-value"><?php echo $totalRequests; ?></div>
        <div class="stat-label">Tổng số yêu cầu</div>
    </div>
    <div class="stat-card stat-received">
        <div class="stat-value"><?php echo $counts['received']; ?></div>
        <div class="stat-label">Đã tiếp nhận (chờ xử lý)</div>
    </div>
    <a class="stat-card stat-overdue" href="manage_requests.php?<?php echo e(http_build_query(['status' => 'received', 'to_date' => overdue_to_date()])); ?>">
        <div class="stat-value"><?php echo $overdueCount; ?></div>
        <div class="stat-label">Quá hạn (chờ từ <?php echo OVERDUE_DAYS; ?> ngày)</div>
    </a>
    <div class="stat-card stat-processing">
        <div class="stat-value"><?php echo $counts['processing']; ?></div>
        <div class="stat-label">Đang xử lý</div>
    </div>
    <div class="stat-card stat-completed">
        <div class="stat-value"><?php echo $counts['completed']; ?></div>
        <div class="stat-label">Hoàn thành</div>
    </div>
</section>

<div class="detail-grid">

    <!-- ===================== BIỂU ĐỒ THEO DANH MỤC ===================== -->
    <div class="card">
        <div class="card-title">Số yêu cầu theo danh mục</div>

        <?php if ($totalRequests === 0): ?>
            <p class="text-muted mb-0">Chưa có dữ liệu để thống kê.</p>
        <?php else: ?>
            <?php foreach ($byCategory as $row): ?>
            <?php
                // Chiều dài cột tính theo tỷ lệ với danh mục nhiều yêu cầu nhất
                $percent = $maxCategory > 0 ? round((int)$row['total'] / $maxCategory * 100) : 0;
            ?>
            <div class="chart-row">
                <div class="chart-label"><?php echo e($row['category_name']); ?></div>
                <div class="chart-track">
                    <div class="chart-fill" style="width: <?php echo $percent; ?>%"></div>
                </div>
                <div class="chart-value"><?php echo (int)$row['total']; ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===================== CHỈ SỐ NHANH ===================== -->
    <div class="card">
        <div class="card-title">Chỉ số nhanh</div>
        <table class="info-table">
            <tr><th>Yêu cầu mới (7 ngày)</th><td><strong><?php echo $last7Days; ?></strong></td></tr>
            <tr><th>Sinh viên đã đăng ký</th><td><?php echo $totalUsers; ?></td></tr>
            <tr>
                <th>Điểm hài lòng trung bình</th>
                <td>
                    <?php if ($ratedCount > 0): ?>
                        <strong><?php echo e($avgRating); ?>/5</strong>
                        <span class="text-muted">(<?php echo $ratedCount; ?> lượt đánh giá)</span>
                    <?php else: ?>
                        <span class="text-muted">Chưa có đánh giá</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Tỷ lệ hoàn thành</th>
                <td>
                    <?php echo $totalRequests > 0
                        ? round($counts['completed'] / $totalRequests * 100) . '%'
                        : '0%'; ?>
                </td>
            </tr>
        </table>

        <div class="mt-12">
            <a class="btn btn-sm" href="manage_categories.php">Quản lý danh mục</a>
            <?php if (is_super_admin()): ?>
            <a class="btn btn-sm btn-muted" href="manage_staff.php">Quản lý tài khoản</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================== YÊU CẦU MỚI NHẤT ===================== -->
<h2>Yêu cầu mới nhất</h2>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Mã</th>
                <th>Sinh viên</th>
                <th>MSSV</th>
                <th>Danh mục</th>
                <th>Tiêu đề</th>
                <th>Ngày gửi</th>
                <th>Trạng thái</th>
                <th class="text-center">Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($recentRequests)): ?>
            <tr><td colspan="8" class="table-empty">Chưa có yêu cầu nào trong hệ thống.</td></tr>
        <?php else: ?>
            <?php foreach ($recentRequests as $request): ?>
            <tr>
                <td class="nowrap"><strong><?php echo e(request_code($request['id'])); ?></strong></td>
                <td><?php echo e($request['full_name']); ?></td>
                <td><?php echo e($request['student_id']); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td><?php echo e(mb_strimwidth($request['title'], 0, 50, '…')); ?></td>
                <td class="nowrap"><?php echo e(format_datetime($request['created_at'])); ?></td>
                <td><?php echo status_badges($request); ?></td>
                <td class="text-center nowrap">
                    <a class="btn btn-sm" href="view_request.php?id=<?php echo (int)$request['id']; ?>">Xem</a>
                    <a class="btn btn-sm btn-success" href="process_request.php?id=<?php echo (int)$request['id']; ?>">Xử lý</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<p class="text-center mt-16">
    <a class="btn btn-muted" href="manage_requests.php">Xem toàn bộ yêu cầu &raquo;</a>
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
