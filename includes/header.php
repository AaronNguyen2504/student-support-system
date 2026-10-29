<?php
/**
 * =============================================================================
 * includes/header.php - Phần đầu trang dùng chung
 * =============================================================================
 * Cách dùng: trang gọi đặt biến $page_title rồi include tệp này.
 *
 *   $page_title = 'Tiêu đề trang';
 *   require_once __DIR__ . '/../includes/header.php';
 *
 * Thanh điều hướng tự động thay đổi theo trạng thái đăng nhập và vai trò:
 *   - Khách       : Trang chủ, Đăng nhập, Đăng ký
 *   - Sinh viên   : Yêu cầu của tôi, Gửi yêu cầu, Hồ sơ
 *   - Cán bộ/QTV  : Bảng điều khiển, Quản lý yêu cầu, Danh mục, Báo cáo...
 * =============================================================================
 */

require_once __DIR__ . '/../functions.php';

// Tiêu đề mặc định nếu trang gọi không đặt
$page_title = $page_title ?? APP_NAME;

// Xác định tệp đang mở để tô sáng mục menu tương ứng
$current_file = basename($_SERVER['PHP_SELF']);
$in_admin_area = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);

/**
 * Trả về class "active" nếu tệp hiện tại trùng với tên tệp truyền vào.
 */
function nav_active($file)
{
    global $current_file;
    return $current_file === $file ? ' class="active" aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e(APP_NAME); ?> - <?php echo e(APP_UNIVERSITY); ?>">
    <meta name="theme-color" content="#0064A2">
    <title><?php echo e($page_title); ?> - <?php echo e(APP_SHORT_NAME); ?></title>
    <link rel="icon" type="image/png" href="<?php echo APP_LOGO; ?>">
    <link rel="stylesheet" href="<?php echo asset('/css/style.css'); ?>">
</head>
<body class="<?php echo $in_admin_area ? 'admin-area' : 'user-area'; ?>">

<a class="skip-link" href="#main">Bỏ qua tới nội dung chính</a>

<header class="site-header">
    <div class="container header-inner">

        <!-- Logo và tên ứng dụng -->
        <a class="brand" href="<?php echo BASE_URL; ?>/index.php">
            <!-- Logo có nền trong suốt và dùng tông xanh đậm, nên được đặt trên
                 một nền trắng bo góc để nổi rõ trên thanh màu xanh đậm của hệ thống -->
            <span class="brand-mark">
                <img src="<?php echo APP_LOGO; ?>"
                     alt="Logo <?php echo e(APP_UNIVERSITY); ?>">
            </span>
            <span class="brand-text">
                <strong><?php echo e(APP_SHORT_NAME); ?></strong>
                <small><?php echo e(APP_DEPARTMENT); ?></small>
            </span>
        </a>

        <!-- Nút mở menu trên màn hình nhỏ -->
        <button class="nav-toggle" id="navToggle" aria-label="Mở menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <!-- Thanh điều hướng chính -->
        <nav class="main-nav" id="mainNav">
            <ul>
            <?php if (!is_logged_in()): ?>
                <li><a href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/login.php">Đăng nhập</a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/register.php">Đăng ký</a></li>

            <?php elseif (is_admin() && $in_admin_area): ?>
                <li><a<?php echo nav_active('index.php'); ?> href="<?php echo BASE_URL; ?>/admin/index.php">Bảng điều khiển</a></li>
                <li><a<?php echo nav_active('manage_requests.php'); ?> href="<?php echo BASE_URL; ?>/admin/manage_requests.php">Quản lý yêu cầu</a></li>
                <li><a<?php echo nav_active('manage_categories.php'); ?> href="<?php echo BASE_URL; ?>/admin/manage_categories.php">Danh mục</a></li>
                <?php if (is_super_admin()): ?>
                <li><a<?php echo nav_active('manage_staff.php'); ?> href="<?php echo BASE_URL; ?>/admin/manage_staff.php">Tài khoản</a></li>
                <?php endif; ?>
                <li><a<?php echo nav_active('reports.php'); ?> href="<?php echo BASE_URL; ?>/admin/reports.php">Báo cáo</a></li>

            <?php else: ?>
                <li><a<?php echo nav_active('index.php'); ?> href="<?php echo BASE_URL; ?>/user/index.php">Yêu cầu của tôi</a></li>
                <li><a<?php echo nav_active('submit_request.php'); ?> href="<?php echo BASE_URL; ?>/user/submit_request.php">Gửi yêu cầu mới</a></li>
                <li><a<?php echo nav_active('profile.php'); ?> href="<?php echo BASE_URL; ?>/user/profile.php">Hồ sơ</a></li>
                <?php if (is_admin()): ?>
                <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Khu vực quản trị</a></li>
                <?php endif; ?>
            <?php endif; ?>
            </ul>
        </nav>

        <!-- Khối thông tin người dùng đang đăng nhập -->
        <?php if (is_logged_in()): ?>
        <div class="user-box">
            <span class="user-name"><?php echo e($_SESSION['full_name']); ?></span>
            <span class="user-role"><?php echo e(role_label(current_role())); ?></span>
            <!-- Đăng xuất dùng POST + CSRF để trang bên ngoài không ép người dùng thoát được -->
            <form method="post" class="inline-form"
                  action="<?php echo BASE_URL; ?>/<?php echo $in_admin_area ? 'admin' : 'user'; ?>/logout.php">
                <?php csrf_field(); ?>
                <button type="submit" class="btn btn-sm btn-outline">Đăng xuất</button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</header>

<main id="main" class="container page-content">
    <?php render_flash(); // Hiển thị thông báo của thao tác trước đó ?>
