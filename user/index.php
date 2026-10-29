<?php
/**
 * =============================================================================
 * user/index.php - Trang chính của sinh viên: danh sách yêu cầu đã gửi
 * =============================================================================
 * Chức năng:
 *   - Thống kê nhanh số yêu cầu theo từng trạng thái
 *   - Lọc theo trạng thái, danh mục và khoảng thời gian
 *   - Tìm kiếm theo tiêu đề
 *   - Phân trang 10 bản ghi mỗi trang
 *
 * Bảo mật: bộ lọc user_id luôn được gán bằng ID của người đang đăng nhập,
 * nên sinh viên chỉ nhìn thấy yêu cầu của chính mình.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_user_login(); // Bắt buộc đăng nhập, tự kiểm tra hết hạn phiên

$userId = (int)$_SESSION['user_id'];

// --- Đọc tham số lọc từ URL ---
$filters = [
    'user_id'     => $userId,                 // Khóa cứng: chỉ xem dữ liệu của mình
    'status'      => get('status'),
    'category_id' => (int)get('category_id'),
    'keyword'     => get('keyword'),
    'from_date'   => get('from_date'),
    'to_date'     => get('to_date'),
];

// --- Phân trang ---
$totalItems = count_requests($filters);
$pagination = paginate($totalItems, (int)get('page', 1));
$requests   = get_requests($filters, $pagination['per_page'], $pagination['offset']);

// --- Thống kê nhanh theo trạng thái của riêng sinh viên này ---
$counts = ['received' => 0, 'processing' => 0, 'completed' => 0];
$rows   = db_select(
    'SELECT status, COUNT(*) AS total FROM tbl_requests WHERE user_id = ? GROUP BY status',
    [$userId],
    'i'
);
foreach ($rows as $row) {
    $counts[$row['status']] = (int)$row['total'];
}
$totalAll = array_sum($counts);

$categories = get_categories();

$page_title = 'Yêu cầu của tôi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Yêu cầu hỗ trợ của tôi</h1>
    <a class="btn btn-success" href="submit_request.php">+ Gửi yêu cầu mới</a>
</div>

<!-- ===================== THỐNG KÊ NHANH ===================== -->
<section class="stats-grid">
    <div class="stat-card stat-total">
        <div class="stat-value"><?php echo $totalAll; ?></div>
        <div class="stat-label">Tổng số yêu cầu</div>
    </div>
    <div class="stat-card stat-received">
        <div class="stat-value"><?php echo $counts['received']; ?></div>
        <div class="stat-label">Đã tiếp nhận</div>
    </div>
    <div class="stat-card stat-processing">
        <div class="stat-value"><?php echo $counts['processing']; ?></div>
        <div class="stat-label">Đang xử lý</div>
    </div>
    <div class="stat-card stat-completed">
        <div class="stat-value"><?php echo $counts['completed']; ?></div>
        <div class="stat-label">Hoàn thành</div>
    </div>
</section>

<!-- ===================== BỘ LỌC ===================== -->
<form class="filter-bar" method="get" action="index.php">
    <div class="form-group">
        <label for="keyword">Từ khóa</label>
        <input type="text" id="keyword" name="keyword" placeholder="Tiêu đề yêu cầu"
               value="<?php echo e($filters['keyword']); ?>">
    </div>

    <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
            <option value="">-- Tất cả --</option>
            <?php foreach (status_list() as $code => $label): ?>
            <option value="<?php echo e($code); ?>"
                <?php echo $filters['status'] === $code ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
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
        <label for="from_date">Từ ngày</label>
        <input type="date" id="from_date" name="from_date" value="<?php echo e($filters['from_date']); ?>">
    </div>

    <div class="form-group">
        <label for="to_date">Đến ngày</label>
        <input type="date" id="to_date" name="to_date" value="<?php echo e($filters['to_date']); ?>">
    </div>

    <div class="form-group flex-none">
        <button type="submit" class="btn">Lọc</button>
        <a class="btn btn-muted" href="index.php">Xóa lọc</a>
    </div>
</form>

<!-- ===================== DANH SÁCH YÊU CẦU ===================== -->
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Mã yêu cầu</th>
                <th>Tiêu đề</th>
                <th>Danh mục</th>
                <th>Ngày gửi</th>
                <th>Cập nhật</th>
                <th>Trạng thái</th>
                <th class="text-center">Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($requests)): ?>
            <tr>
                <td colspan="7" class="table-empty">
                    <?php if ($totalAll === 0): ?>
                        Bạn chưa gửi yêu cầu hỗ trợ nào.
                        <a href="submit_request.php">Gửi yêu cầu đầu tiên</a>.
                    <?php else: ?>
                        Không tìm thấy yêu cầu nào khớp với điều kiện lọc.
                    <?php endif; ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
            <tr>
                <td class="nowrap"><strong><?php echo e(request_code($request['id'])); ?></strong></td>
                <td><?php echo e(mb_strimwidth($request['title'], 0, 70, '…')); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td class="nowrap"><?php echo e(format_datetime($request['created_at'])); ?></td>
                <td class="nowrap"><?php echo e(format_datetime($request['updated_at'])); ?></td>
                <td><?php echo status_badge($request['status']); ?></td>
                <td class="text-center">
                    <a class="btn btn-sm" href="view_request.php?id=<?php echo (int)$request['id']; ?>">
                        Xem chi tiết
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// Thanh phân trang giữ nguyên các tham số lọc hiện tại
render_pagination($pagination, 'index.php');
?>

<p class="text-muted text-center">
    Hiển thị <?php echo count($requests); ?> / <?php echo $pagination['total_items']; ?> yêu cầu
    (trang <?php echo $pagination['current_page']; ?>/<?php echo $pagination['total_pages']; ?>)
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
