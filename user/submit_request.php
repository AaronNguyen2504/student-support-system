<?php
/**
 * =============================================================================
 * user/submit_request.php - Gửi yêu cầu hỗ trợ mới
 * =============================================================================
 * Luồng xử lý khi sinh viên bấm "Gửi yêu cầu":
 *   1. Kiểm tra token CSRF
 *   2. Kiểm tra dữ liệu phía máy chủ (danh mục, tiêu đề, nội dung)
 *   3. Kiểm tra và lưu tệp đính kèm (nếu có): dung lượng, đuôi tệp, kiểu MIME
 *   4. Thêm bản ghi vào tbl_requests
 *   5. Ghi nhật ký trạng thái đầu tiên vào tbl_status_log
 *   6. Gửi email xác nhận cho sinh viên
 *   7. Chuyển tới trang chi tiết yêu cầu vừa tạo
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/mailer.php';

require_user_login();

$userId     = (int)$_SESSION['user_id'];
$categories = get_categories();

$errors = [];
$old    = ['categoryId' => '', 'title' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die();

    $categoryId  = (int)post('category_id');
    $title       = post('title');
    $description = post('description');

    $old = compact('categoryId', 'title', 'description');

    // --- Kiểm tra danh mục có thật trong CSDL không ---
    if ($categoryId <= 0) {
        $errors[] = 'Vui lòng chọn danh mục hỗ trợ.';
    } else {
        $exists = db_scalar('SELECT COUNT(*) FROM tbl_categories WHERE id = ?', [$categoryId], 'i');
        if (!$exists) {
            $errors[] = 'Danh mục được chọn không tồn tại.';
        }
    }

    // --- Kiểm tra tiêu đề ---
    if ($title === '') {
        $errors[] = 'Vui lòng nhập tiêu đề yêu cầu.';
    } elseif (mb_strlen($title) < 10) {
        $errors[] = 'Tiêu đề quá ngắn, cần ít nhất 10 ký tự để cán bộ hiểu rõ vấn đề.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Tiêu đề không được vượt quá 255 ký tự.';
    }

    // --- Kiểm tra nội dung ---
    if ($description === '') {
        $errors[] = 'Vui lòng nhập nội dung chi tiết của yêu cầu.';
    } elseif (mb_strlen($description) < 20) {
        $errors[] = 'Nội dung quá ngắn, cần ít nhất 20 ký tự.';
    } elseif (mb_strlen($description) > 5000) {
        $errors[] = 'Nội dung không được vượt quá 5000 ký tự.';
    }

    // --- Kiểm tra và lưu tệp đính kèm (tùy chọn) ---
    $attachment = null;
    if (empty($errors)) {
        $uploadError = '';
        $attachment  = handle_file_upload($_FILES['attachment'] ?? null, $uploadError);
        if ($uploadError !== '') {
            $errors[] = $uploadError;
        }
    }

    // --- Lưu yêu cầu ---
    if (empty($errors)) {
        $requestId = db_execute(
            'INSERT INTO tbl_requests (user_id, category_id, title, description, status, attachment_path)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $categoryId, $title, $description, 'received', $attachment],
            'iissss'
        );

        // Ghi mốc đầu tiên của dòng thời gian xử lý
        log_status_change($requestId, null, 'received', $userId);

        // Gửi email xác nhận đã tiếp nhận
        $request = get_request_detail($requestId);
        $user    = current_user();
        send_request_created_email($user, $request);

        set_flash('success', 'Gửi yêu cầu thành công! Mã yêu cầu của bạn là '
            . request_code($requestId) . '. Email xác nhận đã được gửi tới hộp thư của bạn.');

        redirect('view_request.php?id=' . $requestId);
    }
}

$page_title = 'Gửi yêu cầu hỗ trợ';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Gửi yêu cầu hỗ trợ mới</h1>
    <a class="btn btn-muted" href="index.php">&laquo; Quay lại danh sách</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <strong>Vui lòng kiểm tra lại:</strong>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Thông tin yêu cầu</div>

    <!-- enctype bắt buộc để trình duyệt gửi kèm tệp đính kèm -->
    <form method="post" action="submit_request.php" enctype="multipart/form-data"
         >
        <?php csrf_field(); ?>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Danh mục hỗ trợ <span class="required">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int)$category['id']; ?>"
                        <?php echo (int)($old['categoryId'] ?? 0) === (int)$category['id'] ? 'selected' : ''; ?>>
                        <?php echo e($category['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Chọn đúng nhóm để yêu cầu được chuyển tới cán bộ phụ trách nhanh nhất.</div>
            </div>
        </div>

        <div class="form-group">
            <label for="title">Tiêu đề <span class="required">*</span></label>
            <input type="text" id="title" name="title" required maxlength="255" minlength="10"
                   placeholder="Tóm tắt ngắn gọn vấn đề, ví dụ: Xin cấp lại bảng điểm học kỳ 1"
                   value="<?php echo e($old['title'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="description">Nội dung chi tiết <span class="required">*</span></label>
            <textarea id="description" name="description" required minlength="20"
                      data-counter="5000" maxlength="5000"
                      placeholder="Mô tả rõ hoàn cảnh, mong muốn của bạn và các thông tin liên quan (lớp, khóa, thời gian...)."><?php echo e($old['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="attachment">Tệp đính kèm</label>
            <input type="file" id="attachment" name="attachment"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
            <div class="form-hint">
                Không bắt buộc. Chấp nhận: PDF, DOC, DOCX, JPG, PNG, GIF. Dung lượng tối đa 5MB.
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">Gửi yêu cầu</button>
            <a class="btn btn-muted" href="index.php">Hủy bỏ</a>
        </div>
    </form>
</div>

<div class="alert alert-info">
    <strong>Lưu ý:</strong> yêu cầu sẽ được <?php echo e(APP_DEPARTMENT); ?> xử lý trong vòng
    3-5 ngày làm việc. Bạn sẽ nhận được email mỗi khi trạng thái yêu cầu thay đổi.
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
