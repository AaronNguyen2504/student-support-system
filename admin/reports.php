<?php
/**
 * =============================================================================
 * admin/reports.php - Báo cáo thống kê và kết xuất dữ liệu
 * =============================================================================
 * Trang tổng hợp số liệu phục vụ công tác quản lý:
 *   1. Bộ lọc theo khoảng thời gian, danh mục, trạng thái
 *   2. Bảng tổng hợp theo trạng thái (kèm tỷ lệ phần trăm)
 *   3. Bảng tổng hợp theo danh mục
 *   4. Thống kê theo từng tháng trong 12 tháng gần nhất
 *   5. Mức độ hài lòng trung bình
 *   6. Nút xuất Excel / PDF giữ nguyên bộ lọc đang áp dụng
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login();

// Dùng lại đúng bộ lọc của chức năng xuất báo cáo để số liệu luôn khớp nhau
$filters    = export_filters();
$categories = get_categories();

// Mệnh đề WHERE dùng chung cho các truy vấn tổng hợp bên dưới
list($whereClause, $whereParams) = build_request_filters($filters);

// Chỉ JOIN bảng người dùng khi lọc theo từ khóa (từ khóa tìm cả họ tên và mã sinh viên);
// các trường hợp còn lại bảng yêu cầu là đủ, đỡ tốn một phép nối.
$joinUsers = $filters['keyword'] !== '' ? ' JOIN tbl_users u ON u.id = r.user_id' : '';
$baseFrom  = ' FROM tbl_requests r' . $joinUsers . $whereClause;

/* -----------------------------------------------------------------------------
 * 1. TỔNG HỢP THEO TRẠNG THÁI (cùng truy vấn lấy luôn số liệu đánh giá hài lòng)
 * -------------------------------------------------------------------------- */
$statusRows = db_select(
    'SELECT r.status, COUNT(*) AS total, COUNT(r.rating) AS rated, COALESCE(SUM(r.rating), 0) AS rating_total'
    . $baseFrom . ' GROUP BY r.status',
    $whereParams
);
$statusStats = ['received' => 0, 'processing' => 0, 'completed' => 0];
$ratedCount  = 0;
$ratingTotal = 0;
foreach ($statusRows as $row) {
    $statusStats[$row['status']] = (int)$row['total'];
    $ratedCount  += (int)$row['rated'];
    $ratingTotal += (int)$row['rating_total'];
}
$totalFiltered = array_sum($statusStats);
$avgRating     = $ratedCount > 0 ? number_format($ratingTotal / $ratedCount, 1, '.', '') : null;

/* -----------------------------------------------------------------------------
 * 2. TỔNG HỢP THEO DANH MỤC
 * -------------------------------------------------------------------------- */
$categoryRows = db_select(
    'SELECT c.category_name,
            COUNT(*) AS total,
            SUM(CASE WHEN r.status = \'completed\' THEN 1 ELSE 0 END) AS completed'
    . ' FROM tbl_requests r' . $joinUsers
    . ' JOIN tbl_categories c ON c.id = r.category_id' . $whereClause
    . ' GROUP BY c.id, c.category_name ORDER BY total DESC',
    $whereParams
);

/* -----------------------------------------------------------------------------
 * 3. THỐNG KÊ THEO THÁNG (12 tháng gần nhất)
 * -------------------------------------------------------------------------- */
$monthlyRows = db_select(
    'SELECT DATE_FORMAT(r.created_at, \'%m/%Y\') AS period,
            DATE_FORMAT(r.created_at, \'%Y%m\')  AS sort_key,
            COUNT(*) AS total'
    . $baseFrom . ' GROUP BY period, sort_key ORDER BY sort_key DESC LIMIT 12',
    $whereParams
);
$maxMonthly = 0;
foreach ($monthlyRows as $row) {
    $maxMonthly = max($maxMonthly, (int)$row['total']);
}

// Chuỗi tham số để nút xuất báo cáo mang theo đúng bộ lọc hiện tại
$exportQuery = http_build_query(array_filter($filters));

$page_title = 'Báo cáo thống kê';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Báo cáo thống kê</h1>
    <div>
        <a class="btn btn-success" href="export_excel.php?<?php echo e($exportQuery); ?>">Xuất Excel</a>
        <a class="btn btn-danger"  href="export_pdf.php?<?php echo e($exportQuery); ?>">Xuất PDF</a>
    </div>
</div>

<!-- ===================== BỘ LỌC BÁO CÁO ===================== -->
<form class="filter-bar" method="get" action="reports.php">
    <div class="form-group">
        <label for="from_date">Từ ngày</label>
        <input type="date" id="from_date" name="from_date" value="<?php echo e($filters['from_date']); ?>">
    </div>

    <div class="form-group">
        <label for="to_date">Đến ngày</label>
        <input type="date" id="to_date" name="to_date" value="<?php echo e($filters['to_date']); ?>">
    </div>

    <div class="form-group">
        <label for="category_id">Danh mục</label>
        <select id="category_id" name="category_id">
            <option value="">-- Tất cả --</option>
            <?php foreach ($categories as $category): ?>
            <option value="<?php echo (int)$category['id']; ?>"
                <?php echo $filters['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>>
                <?php echo e($category['category_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="">-- Tất cả --</option>
            <?php foreach (status_list() as $code => $label): ?>
            <option value="<?php echo e($code); ?>" <?php echo $filters['status'] === $code ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group flex-none">
        <button type="submit" class="btn">Áp dụng</button>
        <a class="btn btn-muted" href="reports.php">Đặt lại</a>
    </div>
</form>

<!-- ===================== SỐ LIỆU TỔNG QUAN ===================== -->
<section class="stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-value"><?php echo $totalFiltered; ?></div>
        <div class="stat-label">Yêu cầu trong phạm vi lọc</div>
    </div>
    <div class="stat-card stat-received">
        <div class="stat-value"><?php echo $statusStats['received']; ?></div>
        <div class="stat-label">Đã tiếp nhận</div>
    </div>
    <div class="stat-card stat-processing">
        <div class="stat-value"><?php echo $statusStats['processing']; ?></div>
        <div class="stat-label">Đang xử lý</div>
    </div>
    <div class="stat-card stat-completed">
        <div class="stat-value"><?php echo $statusStats['completed']; ?></div>
        <div class="stat-label">Hoàn thành</div>
    </div>
</section>

<div class="detail-grid">

    <!-- ===================== THEO DANH MỤC ===================== -->
    <div class="card">
        <div class="card-title">Tổng hợp theo danh mục</div>

        <?php if (empty($categoryRows)): ?>
            <p class="text-muted mb-0">Không có dữ liệu trong phạm vi lọc.</p>
        <?php else: ?>
        <table class="data-table table-auto">
            <thead>
                <tr>
                    <th>Danh mục</th>
                    <th class="text-center">Tổng</th>
                    <th class="text-center">Hoàn thành</th>
                    <th class="text-center">Tỷ lệ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryRows as $row): ?>
                <tr>
                    <td><?php echo e($row['category_name']); ?></td>
                    <td class="text-center"><?php echo (int)$row['total']; ?></td>
                    <td class="text-center"><?php echo (int)$row['completed']; ?></td>
                    <td class="text-center">
                        <?php echo (int)$row['total'] > 0
                            ? round((int)$row['completed'] / (int)$row['total'] * 100) . '%'
                            : '0%'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ===================== THEO TRẠNG THÁI ===================== -->
    <div class="card">
        <div class="card-title">Tỷ lệ theo trạng thái</div>

        <?php foreach (status_list() as $code => $label): ?>
        <?php $percent = $totalFiltered > 0 ? round($statusStats[$code] / $totalFiltered * 100) : 0; ?>
        <div class="chart-row">
            <div class="chart-label"><?php echo e($label); ?></div>
            <div class="chart-track">
                <div class="chart-fill" style="width: <?php echo $percent; ?>%"></div>
            </div>
            <div class="chart-value"><?php echo $statusStats[$code]; ?> (<?php echo $percent; ?>%)</div>
        </div>
        <?php endforeach; ?>

        <hr class="divider">

        <table class="info-table">
            <tr>
                <th>Điểm hài lòng trung bình</th>
                <td>
                    <?php if ($ratedCount > 0): ?>
                        <strong><?php echo e($avgRating); ?>/5</strong>
                        <span class="text-muted">(<?php echo $ratedCount; ?> lượt đánh giá)</span>
                    <?php else: ?>
                        <span class="text-muted">Chưa có đánh giá nào</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Tỷ lệ hoàn thành</th>
                <td>
                    <?php echo $totalFiltered > 0
                        ? round($statusStats['completed'] / $totalFiltered * 100) . '%'
                        : '0%'; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- ===================== THEO THÁNG ===================== -->
<div class="card">
    <div class="card-title">Số yêu cầu theo tháng (12 tháng gần nhất)</div>

    <?php if (empty($monthlyRows)): ?>
        <p class="text-muted mb-0">Không có dữ liệu trong phạm vi lọc.</p>
    <?php else: ?>
        <?php foreach ($monthlyRows as $row): ?>
        <?php $percent = $maxMonthly > 0 ? round((int)$row['total'] / $maxMonthly * 100) : 0; ?>
        <div class="chart-row">
            <div class="chart-label">Tháng <?php echo e($row['period']); ?></div>
            <div class="chart-track">
                <div class="chart-fill" style="width: <?php echo $percent; ?>%"></div>
            </div>
            <div class="chart-value"><?php echo (int)$row['total']; ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <strong>Mẹo:</strong> đặt bộ lọc mong muốn rồi bấm <em>Xuất Excel</em> hoặc <em>Xuất PDF</em>.
    Tệp kết xuất chứa đúng tập dữ liệu đang được thống kê trên màn hình này.
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
