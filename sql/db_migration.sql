-- =============================================================================
-- db_migration.sql - Nâng cấp CSDL đã có (KHÔNG xóa dữ liệu)
-- =============================================================================
-- Chỉ chạy MỘT LẦN trên CSDL student_support đã nạp từ db.sql cũ.
-- CSDL mới nạp từ db.sql hiện tại đã có sẵn các cột này, không cần chạy.
--
--   mysql -u root student_support < sql/db_migration.sql
--   hoặc phpMyAdmin: chọn CSDL student_support -> tab SQL -> dán nội dung tệp này
-- =============================================================================

-- Batch 2: chống dò mật khẩu đăng nhập (cột có giá trị mặc định, tương thích ngược)
ALTER TABLE `tbl_users`
    ADD COLUMN `failed_logins` INT NOT NULL DEFAULT 0 COMMENT 'Số lần đăng nhập sai liên tiếp' AFTER `status`,
    ADD COLUMN `locked_until`  DATETIME DEFAULT NULL  COMMENT 'Tạm khóa đăng nhập tới thời điểm này' AFTER `failed_logins`;
