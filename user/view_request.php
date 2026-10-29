<?php
/**
 * =============================================================================
 * user/view_request.php - Xem chi tiết và theo dõi một yêu cầu
 * =============================================================================
 * Hiển thị:
 *   - Toàn bộ thông tin yêu cầu và tệp đính kèm
 *   - Thanh tiến độ + dòng thời gian xử lý lấy từ bảng tbl_status_log
 *   - Danh sách phản hồi của cán bộ theo thứ tự thời gian
 *   - Biểu mẫu đánh giá mức độ hài lòng (chỉ hiện khi yêu cầu đã hoàn thành)
 *
 * Bảo mật: so sánh user_id của yêu cầu với người đang đăng nhập. Sinh viên
 * không thể xem yêu cầu của người khác dù có đoán đúng ID trên đường dẫn.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_user_login();

$requestId = (int)get('id');
$userId    = (int)$_SESSION['user_id'];

$request = get_request_detail($requestId);

// Khu vực /user chỉ phục vụ chính chủ. Cán bộ và quản trị viên xem yêu cầu
// tại admin/view_request.php để hai khu vực quyền không bị nhập nhằng.
if (!$request || (int)$request['user_id'] !== $userId) {
    if (is_admin()) {
        redirect(BASE_URL . '/admin/view_request.php?id=' . $requestId);
    }
    set_flash('error', 'Không tìm thấy yêu cầu hoặc bạn không có quyền xem yêu cầu này.');
    redirect('index.php');
}

/* -----------------------------------------------------------------------------
 * XỬ LÝ GỬI ĐÁNH GIÁ HÀI LÒNG
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'rate') {

    csrf_check_or_die();

    $rating  = (int)post('rating');
    $comment = post('rating_comment');

    if ((int)$request['user_id'] !== $userId) {
        set_flash('error', 'Bạn chỉ được đánh giá yêu cầu của chính mình.');
    } elseif ($request['status'] !== 'completed') {
        set_flash('error', 'Chỉ có thể đánh giá khi yêu cầu đã hoàn thành.');
    } elseif ($rating < 1 || $rating > 5) {
        set_flash('error', 'Vui lòng chọn số sao từ 1 đến 5.');
    } elseif (mb_strlen($comment) > 500) {
        set_flash('error', 'Nhận xét không được vượt quá 500 ký tự.');
    } else {
        db_execute(
            'UPDATE tbl_requests SET rating = ?, rating_comment = ? WHERE id = ? AND user_id = ?',
            [$rating, $comment, $requestId, $userId],
            'issi'
        );
        set_flash('success', 'Cảm ơn bạn đã gửi đánh giá!');
    }

    redirect('view_request.php?id=' . $requestId);
}

/* -----------------------------------------------------------------------------
 * SINH VIÊN TỰ HỦY YÊU CẦU (chỉ khi chưa được xử lý)
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel') {

    csrf_check_or_die();

    $result = delete_unprocessed_request($requestId, $userId);

    if ($result === 'ok') {
        set_flash('success', 'Đã hủy yêu cầu ' . request_code($requestId) . '.');
        redirect('index.php');
    }
    set_flash('error', $result === 'processed'
        ? 'Không thể hủy: cán bộ đã tiếp nhận xử lý yêu cầu này.'
        : 'Không tìm thấy yêu cầu cần hủy.');
    redirect('view_request.php?id=' . $requestId);
}

// --- Dữ liệu phục vụ hiển thị ---
$responses  = get_responses($requestId);
$statusLogs = get_status_logs($requestId);

// Phần trăm tiến độ tương ứng từng trạng thái
$progressMap = ['received' => 33, 'processing' => 66, 'completed' => 100];
$progress    = $progressMap[$request['status']] ?? 0;

// Tìm thời điểm đạt được mỗi trạng thái để vẽ dòng thời gian
$reachedAt = [];
foreach ($statusLogs as $log) {
    if (!isset($reachedAt[$log['new_status']])) {
        $reachedAt[$log['new_status']] = $log['changed_at'];
    }
}

$page_title = 'Chi tiết yêu cầu ' . request_code($requestId);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Yêu cầu <?php echo e(request_code($request['id'])); ?></h1>
    <a class="btn btn-muted" href="index.php">&laquo; Quay lại danh sách</a>
</div>

<div class="detail-grid">

    <!-- ===================== CỘT TRÁI: NỘI DUNG ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Thông tin yêu cầu</div>

            <table class="info-table">
                <tr>
                    <th>Trạng thái</th>
                    <td>
                        <?php echo status_badge($request['status']); ?>
                        <div class="progress-track">
                            <div class="progress-fill <?php echo $request['status'] === 'completed' ? 'completed' : ''; ?>"
                                 style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </td>
                </tr>
                <tr><th>Tiêu đề</th><td><strong><?php echo e($request['title']); ?></strong></td></tr>
                <tr><th>Danh mục</th><td><?php echo e($request['category_name']); ?></td></tr>
                <tr><th>Người gửi</th>
                    <td><?php echo e($request['full_name']); ?> (<?php echo e($request['student_id']); ?>)</td></tr>
                <tr><th>Ngày gửi</th><td><?php echo e(format_datetime($request['created_at'])); ?></td></tr>
                <tr><th>Cập nhật lần cuối</th><td><?php echo e(format_datetime($request['updated_at'])); ?></td></tr>
                <?php if (!empty($request['attachment_path'])): ?>
                <tr>
                    <th>Tệp đính kèm</th>
                    <td>
                        <a href="<?php echo e(attachment_url($request['id'])); ?>"
                           target="_blank" rel="noopener">
                            Tải tệp đính kèm
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <h3 class="mt-18">Nội dung chi tiết</h3>
            <!-- e() chống XSS, white-space:pre-wrap giữ nguyên cách xuống dòng -->
            <div class="description-box"><?php echo e($request['description']); ?></div>
        </div>

        <!-- ===================== PHẢN HỒI CỦA CÁN BỘ ===================== -->
        <div class="card">
            <div class="card-title">Phản hồi từ <?php echo e(APP_DEPARTMENT); ?>
                (<?php echo count($responses); ?>)</div>

            <?php if (empty($responses)): ?>
                <p class="text-muted mb-0">
                    Chưa có phản hồi. Yêu cầu của bạn đang chờ cán bộ xử lý.
                </p>
            <?php else: ?>
                <?php render_responses($responses); ?>
            <?php endif; ?>
        </div>

        <!-- ===================== HỦY YÊU CẦU (chỉ khi chưa ai xử lý) ===================== -->
        <?php if ($request['status'] === 'received' && empty($responses)): ?>
        <div class="card">
            <div class="card-title">Hủy yêu cầu</div>
            <p class="text-muted">
                Yêu cầu chưa được cán bộ xử lý nên bạn có thể hủy. Yêu cầu bị hủy sẽ bị xóa khỏi hệ thống
                và không thể khôi phục.
            </p>
            <form method="post" action="view_request.php?id=<?php echo (int)$request['id']; ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-danger"
                        data-confirm="Hủy yêu cầu <?php echo e(request_code($request['id'])); ?>? Thao tác này không thể hoàn tác.">
                    Hủy yêu cầu
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- ===================== ĐÁNH GIÁ HÀI LÒNG ===================== -->
        <?php if ($request['status'] === 'completed' && (int)$request['user_id'] === $userId): ?>
        <div class="card">
            <div class="card-title">Đánh giá mức độ hài lòng</div>

            <?php if ($request['rating'] !== null): ?>
                <p>
                    Bạn đã đánh giá:
                    <span class="rating-static"><?php echo str_repeat('★', (int)$request['rating'])
                        . str_repeat('☆', 5 - (int)$request['rating']); ?></span>
                    (<?php echo (int)$request['rating']; ?>/5)
                </p>
                <?php if (!empty($request['rating_comment'])): ?>
                <div class="description-box"><?php echo e($request['rating_comment']); ?></div>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" action="view_request.php?id=<?php echo (int)$request['id']; ?>">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="rate">

                    <div class="form-group">
                        <label>Mức độ hài lòng của bạn</label>
                        <!-- Các sao được xếp ngược để dùng bộ chọn CSS "~" tô màu khi di chuột -->
                        <div class="rating-stars">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                            <input type="radio" id="star<?php echo $star; ?>" name="rating"
                                   value="<?php echo $star; ?>" <?php echo $star === 5 ? 'checked' : ''; ?>>
                            <label for="star<?php echo $star; ?>" title="<?php echo $star; ?> sao">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rating_comment">Nhận xét thêm</label>
                        <textarea id="rating_comment" name="rating_comment" maxlength="500"
                                  class="min-h-80"
                                  placeholder="Ý kiến của bạn giúp Phòng Công Tác Sinh Viên phục vụ tốt hơn."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Gửi đánh giá</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===================== CỘT PHẢI: DÒNG THỜI GIAN ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Tiến trình xử lý</div>

            <ul class="timeline">
                <?php foreach (status_list() as $code => $label): ?>
                <?php
                    // done    = đã đi qua mốc này
                    // current = đang dừng ở mốc này
                    $isDone    = isset($reachedAt[$code]);
                    $isCurrent = ($request['status'] === $code);
                    $class     = $isCurrent ? 'current' : ($isDone ? 'done' : '');
                ?>
                <li class="<?php echo $class; ?>">
                    <div class="tl-title"><?php echo e($label); ?></div>
                    <div class="tl-meta">
                        <?php if ($isDone): ?>
                            <?php echo e(format_datetime($reachedAt[$code])); ?>
                        <?php else: ?>
                            Chưa đến bước này
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-title">Nhật ký thay đổi</div>
            <?php if (empty($statusLogs)): ?>
                <p class="text-muted mb-0">Chưa có thay đổi nào được ghi nhận.</p>
            <?php else: ?>
                <ul class="list-tight">
                    <?php foreach ($statusLogs as $log): ?>
                    <li>
                        <?php if ($log['old_status'] === null): ?>
                            Tạo yêu cầu &rarr; <strong><?php echo e(status_label($log['new_status'])); ?></strong>
                        <?php else: ?>
                            <?php echo e(status_label($log['old_status'])); ?>
                            &rarr; <strong><?php echo e(status_label($log['new_status'])); ?></strong>
                        <?php endif; ?>
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
