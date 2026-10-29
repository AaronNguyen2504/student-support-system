<?php
/**
 * =============================================================================
 * admin/process_request.php - Xử lý yêu cầu: đổi trạng thái và gửi phản hồi
 * =============================================================================
 * Đây là nghiệp vụ quan trọng nhất của khu vực quản trị. Khi cán bộ bấm lưu:
 *
 *   1. Kiểm tra token CSRF và tính hợp lệ của dữ liệu
 *   2. Mở giao dịch (transaction) để 4 thao tác dưới đây hoặc cùng thành công,
 *      hoặc cùng bị hủy - tránh dữ liệu nửa vời:
 *        a. Cập nhật trạng thái và cán bộ phụ trách trong tbl_requests
 *        b. Thêm bản ghi phản hồi vào tbl_responses (nếu có nhập nội dung)
 *        c. Ghi nhật ký chuyển trạng thái vào tbl_status_log (nếu trạng thái đổi)
 *   3. Gửi email thông báo cho sinh viên sau khi giao dịch thành công
 *
 * Việc gửi email đặt SAU giao dịch: nếu máy chủ thư gặp sự cố thì dữ liệu
 * nghiệp vụ vẫn được lưu đúng.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/mailer.php';

require_admin_login();

$requestId = (int)get('id', (int)post('request_id'));
$request   = get_request_detail($requestId);

if (!$request) {
    set_flash('error', 'Không tìm thấy yêu cầu cần xử lý.');
    redirect('manage_requests.php');
}

$staffId = (int)$_SESSION['user_id'];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die();

    $newStatus    = post('status');
    $responseText = post('response_text');
    $oldStatus    = $request['status'];

    // --- Kiểm tra trạng thái hợp lệ ---
    if (!array_key_exists($newStatus, status_list())) {
        $errors[] = 'Trạng thái được chọn không hợp lệ.';
    }

    // --- Yêu cầu đã hoàn thành thì không được lùi về trạng thái trước ---
    if ($oldStatus === 'completed' && $newStatus !== 'completed') {
        $errors[] = 'Yêu cầu đã Hoàn thành, không thể chuyển ngược về trạng thái trước.';
    }

    // --- Phải có ít nhất một thay đổi thực sự ---
    if ($newStatus === $oldStatus && $responseText === '') {
        $errors[] = 'Bạn chưa thay đổi trạng thái và cũng chưa nhập phản hồi nào.';
    }

    if ($responseText !== '' && mb_strlen($responseText) < 10) {
        $errors[] = 'Nội dung phản hồi quá ngắn, cần ít nhất 10 ký tự.';
    }
    if (mb_strlen($responseText) > 5000) {
        $errors[] = 'Nội dung phản hồi không được vượt quá 5000 ký tự.';
    }

    // --- Khi kết thúc yêu cầu, bắt buộc phải có phản hồi cho sinh viên ---
    if ($newStatus === 'completed' && $responseText === '') {
        $existingResponses = (int)db_scalar(
            'SELECT COUNT(*) FROM tbl_responses WHERE request_id = ?',
            [$requestId],
            'i'
        );
        if ($existingResponses === 0) {
            $errors[] = 'Cần có ít nhất một phản hồi trước khi đánh dấu yêu cầu là Hoàn thành.';
        }
    }

    // --- Kiểm tra tệp đính kèm của cán bộ (nếu có) ---
    $attachment = null;
    if (empty($errors)) {
        $uploadError = '';
        $attachment  = handle_file_upload($_FILES['attachment'] ?? null, $uploadError);
        if ($uploadError !== '') {
            $errors[] = $uploadError;
        }
    }

    /* -------------------------------------------------------------------------
     * GHI DỮ LIỆU TRONG MỘT GIAO DỊCH
     * ---------------------------------------------------------------------- */
    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        try {
            // (a) Cập nhật trạng thái; cán bộ đầu tiên xử lý giữ quyền phụ trách
            db_execute(
                'UPDATE tbl_requests SET status = ?, staff_assigned_id = COALESCE(staff_assigned_id, ?) WHERE id = ?',
                [$newStatus, $staffId, $requestId],
                'sii'
            );

            // (b) Lưu phản hồi (nếu cán bộ có nhập)
            if ($responseText !== '') {
                db_execute(
                    'INSERT INTO tbl_responses (request_id, staff_id, response_text, attachment_path)
                     VALUES (?, ?, ?, ?)',
                    [$requestId, $staffId, $responseText, $attachment],
                    'iiss'
                );
            }

            // (c) Ghi nhật ký nếu trạng thái thực sự thay đổi
            if ($newStatus !== $oldStatus) {
                log_status_change($requestId, $oldStatus, $newStatus, $staffId);
            }

            mysqli_commit($conn);

        } catch (Throwable $e) {
            // Có lỗi -> hoàn tác toàn bộ để CSDL trở về trạng thái ban đầu
            mysqli_rollback($conn);
            if ($attachment !== null) {
                @unlink(UPLOAD_DIR . $attachment); // Không để lại tệp mồ côi
            }
            error_log('Loi xu ly yeu cau #' . $requestId . ': ' . $e->getMessage());
            $errors[] = 'Đã xảy ra lỗi khi lưu dữ liệu. Vui lòng thử lại.';
        }
    }

    // --- Gửi email thông báo sau khi lưu thành công ---
    if (empty($errors)) {
        $mailSent = send_status_update_email($request, $newStatus, $responseText);

        $message = 'Cập nhật yêu cầu ' . request_code($requestId) . ' thành công.';
        $message .= $mailSent
            ? ' Email thông báo đã được gửi tới sinh viên.'
            : ' (Lưu ý: chưa gửi được email thông báo, vui lòng kiểm tra cấu hình thư.)';

        set_flash('success', $message);
        redirect('view_request.php?id=' . $requestId);
    }
}

// Danh sách phản hồi hiện có để cán bộ tham khảo khi soạn câu trả lời
$responses = get_responses($requestId);

$page_title = 'Xử lý yêu cầu ' . request_code($requestId);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Xử lý yêu cầu <?php echo e(request_code($request['id'])); ?></h1>
    <a class="btn btn-muted" href="view_request.php?id=<?php echo (int)$request['id']; ?>">&laquo; Xem chi tiết</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong>Không thể lưu:</strong>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="detail-grid">

    <!-- ===================== BIỂU MẪU XỬ LÝ ===================== -->
    <div class="card">
        <div class="card-title">Cập nhật trạng thái và phản hồi</div>

        <form method="post" action="process_request.php?id=<?php echo (int)$request['id']; ?>"
              enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">

            <div class="form-group">
                <label>Trạng thái hiện tại</label>
                <p class="m-0"><?php echo status_badge($request['status']); ?></p>
            </div>

            <div class="form-group">
                <label for="status">Chuyển sang trạng thái <span class="required">*</span></label>
                <select id="status" name="status" required>
                    <?php foreach (status_list() as $code => $label): ?>
                    <option value="<?php echo e($code); ?>"
                        <?php echo $request['status'] === $code ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Quy trình chuẩn: Đã tiếp nhận → Đang xử lý → Hoàn thành. Yêu cầu đã hoàn thành không thể chuyển ngược.</div>
            </div>

            <div class="form-group">
                <label for="response_text">Nội dung phản hồi gửi sinh viên</label>
                <textarea id="response_text" name="response_text" data-counter="5000" maxlength="5000"
                          placeholder="Nêu rõ kết quả xử lý, hướng dẫn tiếp theo hoặc lý do chưa giải quyết được."></textarea>
                <div class="form-hint">
                    Nội dung này được gửi kèm trong email thông báo và hiển thị trên trang theo dõi của sinh viên.
                </div>
            </div>

            <div class="form-group">
                <label for="attachment">Tệp đính kèm</label>
                <input type="file" id="attachment" name="attachment"
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                <div class="form-hint">Không bắt buộc. PDF, DOC, DOCX, JPG, PNG, GIF - tối đa 5MB.</div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">Lưu và gửi thông báo</button>
                <a class="btn btn-muted" href="manage_requests.php">Hủy bỏ</a>
            </div>
        </form>
    </div>

    <!-- ===================== TÓM TẮT YÊU CẦU ===================== -->
    <div>
        <div class="card">
            <div class="card-title">Tóm tắt yêu cầu</div>
            <table class="info-table">
                <tr><th>Sinh viên</th><td><?php echo e($request['full_name']); ?></td></tr>
                <tr><th>Mã sinh viên</th><td><?php echo e($request['student_id']); ?></td></tr>
                <tr><th>Email</th><td><?php echo e($request['email']); ?></td></tr>
                <tr><th>Danh mục</th><td><?php echo e($request['category_name']); ?></td></tr>
                <tr><th>Ngày gửi</th><td><?php echo e(format_datetime($request['created_at'])); ?></td></tr>
            </table>

            <h3 class="mt-16"><?php echo e($request['title']); ?></h3>
            <div class="description-box"><?php echo e($request['description']); ?></div>

            <?php if (!empty($request['attachment_path'])): ?>
            <p class="mt-12 mb-0">
                <a href="<?php echo e(attachment_url($request['id'])); ?>"
                   target="_blank" rel="noopener">Tải tệp đính kèm của sinh viên</a>
            </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($responses)): ?>
        <div class="card">
            <div class="card-title">Phản hồi trước đó (<?php echo count($responses); ?>)</div>
            <?php render_responses($responses); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
