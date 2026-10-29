<?php
/**
 * =============================================================================
 * admin/manage_categories.php - Quản lý danh mục yêu cầu hỗ trợ
 * =============================================================================
 * Đầy đủ 4 thao tác CRUD trên bảng tbl_categories:
 *   - Thêm danh mục mới
 *   - Sửa danh mục đang có (biểu mẫu được nạp sẵn dữ liệu)
 *   - Xóa danh mục (CHẶN nếu vẫn còn yêu cầu thuộc danh mục đó)
 *   - Sắp xếp thứ tự hiển thị bằng trường sort_order
 *
 * Mọi thao tác ghi đều qua POST kèm token CSRF; thao tác xóa còn hỏi xác nhận.
 * =============================================================================
 */

require_once __DIR__ . '/../includes/session_check.php';

require_admin_login();

$errors    = [];
$editing   = null;  // Bản ghi đang được sửa (null nếu đang ở chế độ thêm mới)

/* -----------------------------------------------------------------------------
 * XỬ LÝ CÁC THAO TÁC GHI DỮ LIỆU
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check_or_die();
    $action = post('action');

    /* --- THÊM MỚI hoặc CẬP NHẬT --------------------------------------------- */
    if ($action === 'save') {

        $id          = (int)post('id');
        $name        = post('category_name');
        $code        = strtoupper(post('category_code'));
        $description = post('description');
        $sortOrder   = (int)post('sort_order');

        if ($name === '') {
            $errors[] = 'Vui lòng nhập tên danh mục.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Tên danh mục không được vượt quá 100 ký tự.';
        }

        if ($code !== '' && !preg_match('/^[A-Z0-9_]{2,50}$/', $code)) {
            $errors[] = 'Mã danh mục chỉ gồm chữ in hoa, chữ số và dấu gạch dưới (2-50 ký tự).';
        }

        if (mb_strlen($description) > 1000) {
            $errors[] = 'Mô tả không được vượt quá 1000 ký tự.';
        }

        // Kiểm tra trùng tên (bỏ qua chính bản ghi đang sửa)
        if (empty($errors)) {
            $duplicate = db_scalar(
                'SELECT COUNT(*) FROM tbl_categories WHERE category_name = ? AND id <> ?',
                [$name, $id],
                'si'
            );
            if ($duplicate) {
                $errors[] = 'Tên danh mục này đã tồn tại.';
            }
        }

        if (empty($errors)) {
            if ($id > 0) {
                // Cập nhật bản ghi đã có
                db_execute(
                    'UPDATE tbl_categories
                        SET category_name = ?, category_code = ?, description = ?, sort_order = ?
                      WHERE id = ?',
                    [$name, ($code !== '' ? $code : null), $description, $sortOrder, $id],
                    'sssii'
                );
                set_flash('success', 'Đã cập nhật danh mục "' . $name . '".');
            } else {
                // Thêm bản ghi mới
                db_execute(
                    'INSERT INTO tbl_categories (category_name, category_code, description, sort_order)
                     VALUES (?, ?, ?, ?)',
                    [$name, ($code !== '' ? $code : null), $description, $sortOrder],
                    'sssi'
                );
                set_flash('success', 'Đã thêm danh mục "' . $name . '".');
            }
            redirect('manage_categories.php');
        }

        // Có lỗi -> giữ lại dữ liệu vừa nhập trên biểu mẫu
        $editing = [
            'id'            => $id,
            'category_name' => $name,
            'category_code' => $code,
            'description'   => $description,
            'sort_order'    => $sortOrder,
        ];
    }

    /* --- XÓA ---------------------------------------------------------------- */
    if ($action === 'delete') {

        $id = (int)post('id');

        // Ràng buộc nghiệp vụ: không xóa danh mục vẫn còn yêu cầu tham chiếu tới
        $inUse = (int)db_scalar(
            'SELECT COUNT(*) FROM tbl_requests WHERE category_id = ?',
            [$id],
            'i'
        );

        if ($inUse > 0) {
            set_flash('error', 'Không thể xóa: danh mục này đang được ' . $inUse
                . ' yêu cầu sử dụng. Hãy chuyển các yêu cầu sang danh mục khác trước.');
        } else {
            db_execute('DELETE FROM tbl_categories WHERE id = ?', [$id], 'i');
            set_flash('success', 'Đã xóa danh mục khỏi hệ thống.');
        }

        redirect('manage_categories.php');
    }
}

/* -----------------------------------------------------------------------------
 * CHẾ ĐỘ SỬA: nạp dữ liệu danh mục được chọn từ đường dẫn ?edit=ID
 * -------------------------------------------------------------------------- */
if ($editing === null && get('edit') !== '') {
    $editing = db_select_one(
        'SELECT * FROM tbl_categories WHERE id = ? LIMIT 1',
        [(int)get('edit')],
        'i'
    );
}

// Danh sách danh mục kèm số yêu cầu đang dùng (giúp cán bộ biết có xóa được không)
$categories = db_select(
    'SELECT c.*, COUNT(r.id) AS request_count
       FROM tbl_categories c
       LEFT JOIN tbl_requests r ON r.category_id = c.id
      GROUP BY c.id
      ORDER BY c.sort_order ASC, c.id ASC'
);

$page_title = 'Quản lý danh mục';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Quản lý danh mục hỗ trợ</h1>
    <a class="btn btn-muted" href="index.php">&laquo; Bảng điều khiển</a>
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

<div class="detail-grid">

    <!-- ===================== DANH SÁCH ===================== -->
    <div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tên danh mục</th>
                        <th>Mã</th>
                        <th>Mô tả</th>
                        <th class="text-center">Số yêu cầu</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="6" class="table-empty">Chưa có danh mục nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo (int)$category['sort_order']; ?></td>
                        <td><strong><?php echo e($category['category_name']); ?></strong></td>
                        <td><?php echo e($category['category_code']); ?></td>
                        <td><?php echo e(mb_strimwidth((string)$category['description'], 0, 60, '…')); ?></td>
                        <td class="text-center"><?php echo (int)$category['request_count']; ?></td>
                        <td class="text-center nowrap">
                            <a class="btn btn-sm" href="manage_categories.php?edit=<?php echo (int)$category['id']; ?>">
                                Sửa
                            </a>

                            <form method="post" action="manage_categories.php" class="inline-form">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                    <?php echo (int)$category['request_count'] > 0 ? 'disabled title="Đang có yêu cầu sử dụng"' : ''; ?>
                                    data-confirm="Xóa danh mục &quot;<?php echo e($category['category_name']); ?>&quot;?">
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info mt-16">
            Danh mục đang có yêu cầu sử dụng sẽ không xóa được, nhằm bảo đảm toàn vẹn dữ liệu
            (ràng buộc khóa ngoại của CSDL).
        </div>
    </div>

    <!-- ===================== BIỂU MẪU THÊM / SỬA ===================== -->
    <div class="card">
        <div class="card-title">
            <?php echo $editing ? 'Sửa danh mục' : 'Thêm danh mục mới'; ?>
        </div>

        <form method="post" action="manage_categories.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">

            <div class="form-group">
                <label for="category_name">Tên danh mục <span class="required">*</span></label>
                <input type="text" id="category_name" name="category_name" required maxlength="100"
                       placeholder="Ví dụ: Hỗ trợ học tập"
                       value="<?php echo e($editing['category_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="category_code">Mã danh mục</label>
                <input type="text" id="category_code" name="category_code" maxlength="50"
                       placeholder="Ví dụ: HOC_TAP"
                       value="<?php echo e($editing['category_code'] ?? ''); ?>">
                <div class="form-hint">Chữ in hoa, chữ số và dấu gạch dưới. Dùng cho thống kê nội bộ.</div>
            </div>

            <div class="form-group">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" maxlength="1000" class="min-h-90"
                          placeholder="Những loại yêu cầu thuộc danh mục này."><?php echo e($editing['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="sort_order">Thứ tự hiển thị</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                       value="<?php echo (int)($editing['sort_order'] ?? (count($categories) + 1)); ?>">
                <div class="form-hint">Số nhỏ hơn sẽ hiển thị trước.</div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <?php echo $editing ? 'Lưu thay đổi' : 'Thêm danh mục'; ?>
                </button>
                <?php if ($editing): ?>
                <a class="btn btn-muted" href="manage_categories.php">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
