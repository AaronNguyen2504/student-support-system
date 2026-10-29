<?php
/**
 * =============================================================================
 * includes/footer.php - Phần chân trang dùng chung
 * =============================================================================
 * Đóng các thẻ HTML mở ở header.php và nạp tệp JavaScript chung.
 * Mọi trang giao diện đều kết thúc bằng: require_once '.../includes/footer.php';
 * =============================================================================
 */
?>
</main><!-- /.page-content -->

<footer class="site-footer">
    <div class="container footer-inner">
        <div>
            <strong><?php echo e(APP_NAME); ?></strong><br>
            <?php echo e(APP_UNIVERSITY); ?> - <?php echo e(APP_DEPARTMENT); ?>
        </div>
        <div class="footer-contact">
            Hỗ trợ kỹ thuật: <a href="mailto:ctsv@ulsa.edu.vn">ctsv@ulsa.edu.vn</a><br>
            Điện thoại: (024) 3855 1234<br>
            Giờ làm việc: 08:00 - 17:00 (Thứ 2 - Thứ 6)
        </div>
        <div class="footer-copy">
            &copy; <?php echo date('Y'); ?> Bài tập lớn môn Lập Trình PHP Cơ Bản
        </div>
    </div>
</footer>

<script src="<?php echo asset('/js/script.js'); ?>"></script>
</body>
</html>
