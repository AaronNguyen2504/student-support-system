<?php
/**
 * =============================================================================
 * admin/view_request.php - Xem chi tiết một yêu cầu (chế độ chỉ đọc)
 * =============================================================================
 * Trang này dành cho cán bộ xem đầy đủ thông tin trước khi quyết định xử lý:
 *   - Thông tin sinh viên và nội dung yêu cầu
 *   - Toàn bộ phản hồi đã gửi
 *   - Nhật ký thay đổi trạng thái
 *   - Đánh giá hài lòng của sinh viên (nếu đã có)
 * Muốn thay đổi trạng thái hoặc gửi phản hồi, bấm nút "Xử lý yêu cầu".
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login();

$requestId = (int)get('id');
$request   = get_request_detail($requestId);

if (!$request) {
    set_flash('error', 'Không tìm thấy yêu cầu có mã đã chọn.');
    redirect('manage_requests.php');
}

$responses  = get_responses($requestId);
$statusLogs = get_status_logs($requestId);

// Tên cán bộ đang được phân công (nếu có)
$assignedStaff = null;
if (!empty($request['staff_assigned_id'])) {
    $assignedStaff = db_select_one(
        'SELECT full_name, email FROM tbl_users WHERE id = ? LIMIT 1',
        [(int)$request['staff_assigned_id']],
        'i'
    );
}

$page_title = 'Chi tiết yêu cầu ' . request_code($requestId);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Yêu cầu <?php echo e(request_code($request['id'])); ?></h1>
    <div>
        <a class="btn btn-success" href="process_request.php?id=<?php echo (int)$request['id']; ?>">Xử lý yêu cầu</a>
        <a class="btn btn-muted" href="manage_requests.php">&laquo; Danh sách</a>
    </div>
</div>

<div class="detail-grid">

    <!-- ===================== CỘT TRÁI ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Nội dung yêu cầu</div>

            <table class="info-table">
                <tr><th>Trạng thái</th><td><?php echo status_badge($request['status']); ?></td></tr>
                <tr><th>Tiêu đề</th><td><strong><?php echo e($request['title']); ?></strong></td></tr>
                <tr><th>Danh mục</th><td><?php echo e($request['category_name']); ?></td></tr>
                <tr><th>Ngày gửi</th><td><?php echo e(format_datetime($request['created_at'])); ?></td></tr>
                <tr><th>Cập nhật lần cuối</th><td><?php echo e(format_datetime($request['updated_at'])); ?></td></tr>
                <tr>
                    <th>Cán bộ phụ trách</th>
                    <td>
                        <?php echo $assignedStaff
                            ? e($assignedStaff['full_name'])
                            : '<span class="text-muted">Chưa phân công</span>'; ?>
                    </td>
                </tr>
                <?php if (!empty($request['attachment_path'])): ?>
                <tr>
                    <th>Tệp đính kèm</th>
                    <td>
                        <a href="<?php echo e(attachment_url($request['id'])); ?>"
                           target="_blank" rel="noopener">Tải tệp sinh viên gửi</a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <h3 class="mt-18">Chi tiết</h3>
            <div class="description-box"><?php echo e($request['description']); ?></div>
        </div>

        <!-- ===================== PHẢN HỒI ĐÃ GỬI ===================== -->
        <div class="card">
            <div class="card-title">Lịch sử phản hồi (<?php echo count($responses); ?>)</div>

            <?php if (empty($responses)): ?>
                <p class="text-muted mb-0">Chưa có phản hồi nào được gửi cho sinh viên.</p>
            <?php else: ?>
                <?php render_responses($responses); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== CỘT PHẢI ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Thông tin sinh viên</div>
            <table class="info-table">
                <tr><th>Họ và tên</th><td><?php echo e($request['full_name']); ?></td></tr>
                <tr><th>Mã sinh viên</th><td><?php echo e($request['student_id']); ?></td></tr>
                <tr><th>Email</th>
                    <td><a href="mailto:<?php echo e($request['email']); ?>"><?php echo e($request['email']); ?></a></td></tr>
                <tr><th>Điện thoại</th>
                    <td><?php echo $request['phone'] ? e($request['phone'])
                        : '<span class="text-muted">Chưa cung cấp</span>'; ?></td></tr>
            </table>
        </div>

        <?php if ($request['rating'] !== null): ?>
        <div class="card">
            <div class="card-title">Đánh giá của sinh viên</div>
            <p class="rating-static">
                <?php echo str_repeat('★', (int)$request['rating'])
                         . str_repeat('☆', 5 - (int)$request['rating']); ?>
                <span class="text-muted">(<?php echo (int)$request['rating']; ?>/5)</span>
            </p>
            <?php if (!empty($request['rating_comment'])): ?>
            <div class="description-box"><?php echo e($request['rating_comment']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">Nhật ký trạng thái</div>
            <?php if (empty($statusLogs)): ?>
                <p class="text-muted mb-0">Chưa có thay đổi nào.</p>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($statusLogs as $log): ?>
                    <li class="done">
                        <div class="tl-title">
                            <?php echo $log['old_status'] === null
                                ? 'Sinh viên tạo yêu cầu'
                                : e(status_label($log['old_status'])) . ' → ' . e(status_label($log['new_status'])); ?>
                        </div>
                        <div class="tl-meta">
                            <?php echo e($log['changed_by_name']); ?>
                            - <?php echo e(format_datetime($log['changed_at'])); ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
