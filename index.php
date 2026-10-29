<?php
/**
 * =============================================================================
 * index.php - Trang chủ của hệ thống
 * =============================================================================
 * Trang giới thiệu, hiển thị:
 *   - Lời giới thiệu và nút vào các chức năng chính
 *   - Danh sách 5 danh mục hỗ trợ mà sinh viên có thể gửi yêu cầu
 *   - Vài số liệu thống kê công khai (tổng số yêu cầu, số đã hoàn thành)
 *   - Quy trình xử lý 3 bước
 *
 * Người dùng đã đăng nhập sẽ thấy nút dẫn thẳng tới khu vực làm việc của mình.
 * =============================================================================
 */

require_once __DIR__ . '/functions.php';

// Lấy danh mục để giới thiệu phạm vi hỗ trợ
$categories = get_categories();

// Thống kê tổng quan (không chứa thông tin cá nhân nên hiển thị công khai được)
$totalRequests     = (int)db_scalar('SELECT COUNT(*) FROM tbl_requests');
$completedRequests = (int)db_scalar('SELECT COUNT(*) FROM tbl_requests WHERE status = ?', ['completed'], 's');
$totalStudents     = (int)db_scalar('SELECT COUNT(*) FROM tbl_users WHERE role = ?', ['student'], 's');

$page_title = 'Trang chủ';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ===================== KHỐI GIỚI THIỆU ===================== -->
<section class="hero">
    <div>
        <h1><?php echo e(APP_NAME); ?></h1>
        <p>
            Gửi yêu cầu một lần, theo dõi tiến độ và nhận phản hồi chính thức
            từ <?php echo e(APP_DEPARTMENT); ?>.
        </p>

        <div class="hero-actions">
        <?php if (is_logged_in()): ?>
            <?php if (is_admin()): ?>
                <a class="btn btn-success" href="<?php echo BASE_URL; ?>/admin/index.php">Vào bảng điều khiển</a>
            <?php else: ?>
                <a class="btn btn-success" href="<?php echo BASE_URL; ?>/user/submit_request.php">Gửi yêu cầu mới</a>
                <a class="btn" href="<?php echo BASE_URL; ?>/user/index.php">Yêu cầu của tôi</a>
            <?php endif; ?>
        <?php else: ?>
            <a class="btn btn-success" href="<?php echo BASE_URL; ?>/user/register.php">Đăng ký tài khoản</a>
            <a class="btn" href="<?php echo BASE_URL; ?>/user/login.php">Đăng nhập</a>
        <?php endif; ?>
        </div>
    </div>

    <!-- Số liệu thật từ CSDL (không chứa thông tin cá nhân nên hiển thị công khai được) -->
    <dl class="hero-figures">
        <div>
            <dt class="figure-label">Yêu cầu đã tiếp nhận</dt>
            <dd class="figure-value"><?php echo $totalRequests; ?></dd>
        </div>
        <div>
            <dt class="figure-label">Yêu cầu đã hoàn thành</dt>
            <dd class="figure-value"><?php echo $completedRequests; ?></dd>
        </div>
        <div>
            <dt class="figure-label">Sinh viên đang sử dụng</dt>
            <dd class="figure-value"><?php echo $totalStudents; ?></dd>
        </div>
        <div>
            <dt class="figure-label">Nhóm hỗ trợ</dt>
            <dd class="figure-value"><?php echo count($categories); ?></dd>
        </div>
    </dl>
</section>

<!-- ===================== CÁC NHÓM HỖ TRỢ ===================== -->
<h2>Các nhóm yêu cầu được hỗ trợ</h2>
<section class="feature-grid">
    <?php foreach ($categories as $category): ?>
    <article class="feature-card">
        <h3><?php echo e($category['category_name']); ?></h3>
        <p><?php echo e($category['description']); ?></p>
    </article>
    <?php endforeach; ?>
</section>

<!-- ===================== QUY TRÌNH XỬ LÝ ===================== -->
<h2>Quy trình xử lý yêu cầu</h2>
<ol class="steps">
    <li>
        <h3>Đã tiếp nhận</h3>
        <p>
            Sinh viên đăng nhập và gửi yêu cầu kèm tệp minh chứng (nếu có).
            Hệ thống ghi nhận và gửi email xác nhận ngay lập tức.
        </p>
    </li>
    <li>
        <h3>Đang xử lý</h3>
        <p>
            Cán bộ <?php echo e(APP_DEPARTMENT); ?> tiếp nhận, phân công xử lý
            và cập nhật trạng thái. Sinh viên nhận được email thông báo.
        </p>
    </li>
    <li>
        <h3>Hoàn thành</h3>
        <p>
            Kết quả và phản hồi chính thức được gửi tới sinh viên.
            Sinh viên có thể đánh giá mức độ hài lòng sau khi nhận kết quả.
        </p>
    </li>
</ol>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
