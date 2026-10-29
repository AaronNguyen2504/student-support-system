<?php
/**
 * =============================================================================
 * admin/manage_requests.php - Danh sách và quản lý toàn bộ yêu cầu
 * =============================================================================
 * Chức năng:
 *   - Lọc theo trạng thái, danh mục, khoảng thời gian
 *   - Tìm kiếm theo tiêu đề, họ tên hoặc mã sinh viên
 *   - Phân trang 10 bản ghi / trang
 *   - Nút xuất Excel / PDF giữ nguyên điều kiện lọc đang áp dụng
 *   - Xóa yêu cầu chưa xử lý (chỉ Quản trị viên, có xác nhận và kiểm tra CSRF)
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login();

/* -----------------------------------------------------------------------------
 * XỬ LÝ XÓA YÊU CẦU (chỉ dành cho vai trò admin)
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {

    csrf_check_or_die();

    if (!is_super_admin()) {
        set_flash('error', 'Chỉ Quản trị viên mới được xóa yêu cầu.');
    } else {
        $deleteId = (int)post('request_id');

        // Chốt chặn phía máy chủ: chỉ xóa được yêu cầu chưa xử lý (xem delete_unprocessed_request);
        // hộp xác nhận JavaScript chỉ để đỡ bấm nhầm.
        $result = delete_unprocessed_request($deleteId);

        if ($result === 'notfound') {
            set_flash('error', 'Không tìm thấy yêu cầu cần xóa.');
        } elseif ($result === 'processed') {
            set_flash('error', 'Không thể xóa ' . request_code($deleteId)
                . ': yêu cầu đã được xử lý hoặc đã có phản hồi, phải giữ lại để tra cứu.');
        } else {
            set_flash('success', 'Đã xóa yêu cầu ' . request_code($deleteId) . ' khỏi hệ thống.');
        }
    }

    // Chuyển hướng sau khi POST để tránh gửi lại biểu mẫu khi bấm F5
    redirect('manage_requests.php?' . http_build_query(array_diff_key($_GET, ['page' => ''])));
}

/* -----------------------------------------------------------------------------
 * ĐỌC BỘ LỌC VÀ LẤY DỮ LIỆU
 * -------------------------------------------------------------------------- */
$filters = export_filters();

$totalItems = count_requests($filters);
$pagination = paginate($totalItems, (int)get('page', 1));
$requests   = get_requests($filters, $pagination['per_page'], $pagination['offset']);

$categories = get_categories();

// Chuỗi tham số dùng lại cho liên kết xuất báo cáo (giữ nguyên bộ lọc)
$exportQuery = http_build_query(array_filter($filters));

$page_title = 'Quản lý yêu cầu';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Quản lý yêu cầu hỗ trợ</h1>
    <div>
        <a class="btn btn-success" href="export_excel.php?<?php echo e($exportQuery); ?>">Xuất Excel</a>
        <a class="btn btn-danger"  href="export_pdf.php?<?php echo e($exportQuery); ?>">Xuất PDF</a>
    </div>
</div>

<!-- ===================== BỘ LỌC ===================== -->
<form class="filter-bar" method="get" action="manage_requests.php">
    <div class="form-group">
        <label for="keyword">Tìm kiếm</label>
        <input type="text" id="keyword" name="keyword" placeholder="Tiêu đề, họ tên hoặc MSSV"
               value="<?php echo e($filters['keyword']); ?>">
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
        <a class="btn btn-muted" href="manage_requests.php">Xóa lọc</a>
    </div>
</form>

<!-- ===================== BẢNG DỮ LIỆU ===================== -->
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
        <?php if (empty($requests)): ?>
            <tr><td colspan="8" class="table-empty">Không có yêu cầu nào khớp điều kiện lọc.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
            <tr>
                <td class="nowrap"><strong><?php echo e(request_code($request['id'])); ?></strong></td>
                <td><?php echo e($request['full_name']); ?></td>
                <td><?php echo e($request['student_id']); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td><?php echo e(mb_strimwidth($request['title'], 0, 45, '…')); ?></td>
                <td class="nowrap"><?php echo e(format_datetime($request['created_at'])); ?></td>
                <td><?php echo status_badges($request); ?></td>
                <td class="text-center nowrap">
                    <a class="btn btn-sm" href="view_request.php?id=<?php echo (int)$request['id']; ?>">Xem</a>
                    <a class="btn btn-sm btn-success" href="process_request.php?id=<?php echo (int)$request['id']; ?>">Xử lý</a>

                    <?php if (is_super_admin() && $request['status'] === 'received'): ?>
                    <!-- Xóa phải dùng POST kèm token CSRF, không dùng liên kết GET;
                         chỉ hiện với yêu cầu chưa xử lý (máy chủ còn kiểm tra thêm số phản hồi) -->
                    <form method="post" action="manage_requests.php?<?php echo e($exportQuery); ?>" class="inline-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger"
                                data-confirm="Bạn có chắc chắn muốn xóa yêu cầu <?php echo e(request_code($request['id'])); ?>? Thao tác này không thể hoàn tác.">
                            Xóa
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php render_pagination($pagination, 'manage_requests.php'); ?>

<p class="text-muted text-center">
    Tổng cộng <?php echo $pagination['total_items']; ?> yêu cầu
    - trang <?php echo $pagination['current_page']; ?>/<?php echo $pagination['total_pages']; ?>
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
