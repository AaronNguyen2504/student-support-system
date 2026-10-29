-- =============================================================================
-- db_migration_2.sql - Thêm index tổng hợp cho truy vấn lọc/sắp xếp (KHÔNG xóa dữ liệu)
-- =============================================================================
-- Chỉ chạy MỘT LẦN trên CSDL student_support đã có (cần đã chạy db_migration.sql nếu CSDL cũ).
-- CSDL mới nạp từ db.sql hiện tại đã có sẵn các index này, không cần chạy.
--
--   mysql -u root student_support < sql/db_migration_2.sql
-- =============================================================================

-- Danh sách yêu cầu của sinh viên: lọc theo user, sắp xếp theo ngày gửi
ALTER TABLE `tbl_requests` ADD INDEX `idx_requests_user_created`   (`user_id`, `created_at`);
-- Danh sách/báo cáo của cán bộ: lọc theo trạng thái, sắp xếp theo ngày gửi
ALTER TABLE `tbl_requests` ADD INDEX `idx_requests_status_created` (`status`, `created_at`);
-- Trang chi tiết: phản hồi và nhật ký của một yêu cầu, đã sắp theo thời gian
ALTER TABLE `tbl_responses`  ADD INDEX `idx_responses_request_date` (`request_id`, `response_date`);
ALTER TABLE `tbl_status_log` ADD INDEX `idx_log_request_changed`    (`request_id`, `changed_at`);
