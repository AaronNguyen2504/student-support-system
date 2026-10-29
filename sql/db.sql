-- =============================================================================
-- db.sql - Cấu trúc cơ sở dữ liệu và dữ liệu mẫu
-- =============================================================================
-- Đề tài 63: Hệ Thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên
-- Trường Đại Học Lao Động - Xã Hội CS2
--
-- Cách nạp:
--   Cách 1 (dòng lệnh):  mysql -u root -p < sql/db.sql
--   Cách 2 (phpMyAdmin): Tạo CSDL student_support -> tab Import -> chọn tệp này
--
-- Bảng mã: utf8mb4 (hỗ trợ đầy đủ tiếng Việt có dấu và biểu tượng)
-- Kiểu bảng: InnoDB (bắt buộc, vì cần khóa ngoại và ràng buộc toàn vẹn)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tạo cơ sở dữ liệu
CREATE DATABASE IF NOT EXISTS `student_support`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `student_support`;

-- Xóa bảng cũ theo thứ tự ngược với quan hệ phụ thuộc (nếu chạy lại tệp này)
DROP TABLE IF EXISTS `tbl_status_log`;
DROP TABLE IF EXISTS `tbl_responses`;
DROP TABLE IF EXISTS `tbl_requests`;
DROP TABLE IF EXISTS `tbl_categories`;
DROP TABLE IF EXISTS `tbl_users`;

-- =============================================================================
-- BẢNG 1: tbl_users - Người dùng (sinh viên, cán bộ hỗ trợ, quản trị viên)
-- =============================================================================
CREATE TABLE `tbl_users` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY           COMMENT 'Khóa chính',
    `full_name`  VARCHAR(255) NOT NULL                    COMMENT 'Họ và tên',
    `email`      VARCHAR(255) NOT NULL UNIQUE             COMMENT 'Email đăng nhập, duy nhất',
    `phone`      VARCHAR(20)  DEFAULT NULL                COMMENT 'Số điện thoại liên hệ',
    `student_id` VARCHAR(50)  DEFAULT NULL UNIQUE         COMMENT 'Mã sinh viên (NULL với cán bộ)',
    `password`   VARCHAR(255) NOT NULL                    COMMENT 'Mật khẩu đã băm bằng bcrypt',
    `role`       ENUM('student','staff','admin') NOT NULL DEFAULT 'student'
                                                          COMMENT 'Vai trò quyết định quyền truy cập',
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active'
                                                          COMMENT 'Khóa tài khoản khi inactive',
    `failed_logins` INT NOT NULL DEFAULT 0                COMMENT 'Số lần đăng nhập sai liên tiếp',
    `locked_until`  DATETIME DEFAULT NULL                 COMMENT 'Tạm khóa đăng nhập tới thời điểm này (chống dò mật khẩu)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tài khoản người dùng của hệ thống';

-- =============================================================================
-- BẢNG 2: tbl_categories - Danh mục yêu cầu hỗ trợ
-- =============================================================================
CREATE TABLE `tbl_categories` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY        COMMENT 'Khóa chính',
    `category_name` VARCHAR(100) NOT NULL UNIQUE          COMMENT 'Tên danh mục hiển thị',
    `category_code` VARCHAR(50)  DEFAULT NULL UNIQUE      COMMENT 'Mã danh mục dùng nội bộ',
    `description`   TEXT         DEFAULT NULL             COMMENT 'Mô tả phạm vi hỗ trợ',
    `sort_order`    INT NOT NULL DEFAULT 0                COMMENT 'Thứ tự hiển thị, nhỏ đứng trước',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Danh mục phân loại yêu cầu hỗ trợ';

-- =============================================================================
-- BẢNG 3: tbl_requests - Yêu cầu hỗ trợ do sinh viên gửi
-- Quan hệ: tbl_users (1) --- (N) tbl_requests
--          tbl_categories (1) --- (N) tbl_requests
-- =============================================================================
CREATE TABLE `tbl_requests` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY    COMMENT 'Khóa chính',
    `user_id`           INT NOT NULL                      COMMENT 'FK -> tbl_users: sinh viên gửi',
    `category_id`       INT NOT NULL                      COMMENT 'FK -> tbl_categories',
    `title`             VARCHAR(255) NOT NULL             COMMENT 'Tiêu đề yêu cầu',
    `description`       LONGTEXT NOT NULL                 COMMENT 'Nội dung chi tiết',
    `status`            ENUM('received','processing','completed') NOT NULL DEFAULT 'received'
                                                          COMMENT 'received=Đã tiếp nhận, processing=Đang xử lý, completed=Hoàn thành',
    `attachment_path`   VARCHAR(255) DEFAULT NULL         COMMENT 'Tên tệp đính kèm của sinh viên',
    `staff_assigned_id` INT DEFAULT NULL                  COMMENT 'FK -> tbl_users: cán bộ phụ trách',
    `rating`            TINYINT DEFAULT NULL              COMMENT 'Đánh giá hài lòng 1-5 sao (tùy chọn)',
    `rating_comment`    VARCHAR(500) DEFAULT NULL         COMMENT 'Nhận xét kèm đánh giá',
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_request_user`     FOREIGN KEY (`user_id`)
        REFERENCES `tbl_users`(`id`)      ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_request_category`  FOREIGN KEY (`category_id`)
        REFERENCES `tbl_categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_request_staff`     FOREIGN KEY (`staff_assigned_id`)
        REFERENCES `tbl_users`(`id`)      ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_requests_status`   (`status`),
    INDEX `idx_requests_user`     (`user_id`),
    INDEX `idx_requests_category` (`category_id`),
    INDEX `idx_requests_created`  (`created_at`),
    INDEX `idx_requests_user_created`   (`user_id`, `created_at`),
    INDEX `idx_requests_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Yêu cầu hỗ trợ của sinh viên';

-- =============================================================================
-- BẢNG 4: tbl_responses - Phản hồi của cán bộ cho từng yêu cầu
-- Quan hệ: tbl_requests (1) --- (N) tbl_responses
-- =============================================================================
CREATE TABLE `tbl_responses` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY      COMMENT 'Khóa chính',
    `request_id`      INT NOT NULL                        COMMENT 'FK -> tbl_requests',
    `staff_id`        INT NOT NULL                        COMMENT 'FK -> tbl_users: cán bộ trả lời',
    `response_text`   LONGTEXT NOT NULL                   COMMENT 'Nội dung phản hồi',
    `attachment_path` VARCHAR(255) DEFAULT NULL           COMMENT 'Tệp đính kèm của cán bộ',
    `response_date`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_response_request` FOREIGN KEY (`request_id`)
        REFERENCES `tbl_requests`(`id`) ON DELETE CASCADE   ON UPDATE CASCADE,
    CONSTRAINT `fk_response_staff`   FOREIGN KEY (`staff_id`)
        REFERENCES `tbl_users`(`id`)   ON DELETE RESTRICT  ON UPDATE CASCADE,
    INDEX `idx_responses_request` (`request_id`),
    INDEX `idx_responses_request_date` (`request_id`, `response_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Phản hồi của cán bộ hỗ trợ';

-- =============================================================================
-- BẢNG 5: tbl_status_log - Nhật ký thay đổi trạng thái (vết xử lý)
-- Quan hệ: tbl_requests (1) --- (N) tbl_status_log
-- =============================================================================
CREATE TABLE `tbl_status_log` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY           COMMENT 'Khóa chính',
    `request_id` INT NOT NULL                             COMMENT 'FK -> tbl_requests',
    `old_status` VARCHAR(50) DEFAULT NULL                 COMMENT 'Trạng thái trước khi đổi',
    `new_status` VARCHAR(50) NOT NULL                     COMMENT 'Trạng thái sau khi đổi',
    `changed_by` INT NOT NULL                             COMMENT 'FK -> tbl_users: người thực hiện',
    `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_log_request` FOREIGN KEY (`request_id`)
        REFERENCES `tbl_requests`(`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_log_user`    FOREIGN KEY (`changed_by`)
        REFERENCES `tbl_users`(`id`)    ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_log_request` (`request_id`),
    INDEX `idx_log_request_changed` (`request_id`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nhật ký thay đổi trạng thái yêu cầu';

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- DỮ LIỆU MẪU
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tài khoản mặc định
-- Mật khẩu được băm bằng password_hash($pass, PASSWORD_BCRYPT)
--   admin@system.com   / Admin@123
--   staff@system.com   / Staff@123
--   Sinh viên (cả 3 tài khoản) / Sinhvien@123
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_users` (`id`, `full_name`, `email`, `phone`, `student_id`, `password`, `role`) VALUES
(1, 'Quản Trị Hệ Thống',  'admin@system.com', '0912345678', NULL,
    '$2y$10$ly6km718LFotqmIzDV7Rv.SZvkSyg9yCdQCASUsTsVEPRVgc.q2Dm', 'admin'),
(2, 'Hỗ trợ viên',        'staff@system.com', '0912345679', NULL,
    '$2y$10$a7rKxgXkId7bOJdMCla1/O0jHLlr6FcsYNIX7vt.15o0zmDucmFM.', 'staff'),
(3, 'Trần Văn An',        'an.tran@sv.ulsa.edu.vn',  '0901234567', 'SV202001',
    '$2y$10$TzmRIWNR1wyyy.tDPBJ7d.RHb552ndohSUq5KBZk1Wjy7hCXMynK2', 'student'),
(4, 'Lê Thị Bình',        'binh.le@sv.ulsa.edu.vn',  '0901234568', 'SV202002',
    '$2y$10$TzmRIWNR1wyyy.tDPBJ7d.RHb552ndohSUq5KBZk1Wjy7hCXMynK2', 'student'),
(5, 'Phạm Minh Cường',    'cuong.pham@sv.ulsa.edu.vn', '0901234569', 'SV202003',
    '$2y$10$TzmRIWNR1wyyy.tDPBJ7d.RHb552ndohSUq5KBZk1Wjy7hCXMynK2', 'student');

-- -----------------------------------------------------------------------------
-- 5 danh mục hỗ trợ mặc định theo yêu cầu đề bài
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_categories` (`id`, `category_name`, `category_code`, `description`, `sort_order`) VALUES
(1, 'Hỗ trợ học tập',          'HOC_TAP',   'Đăng ký học phần, điểm số, học lại, chuyển lớp, xin bảng điểm.', 1),
(2, 'Hỗ trợ tài chính',        'TAI_CHINH', 'Học phí, học bổng, miễn giảm, vay vốn sinh viên, hoàn tiền.',    2),
(3, 'Hỗ trợ tâm lý / sức khỏe','TAM_LY',    'Tư vấn tâm lý, bảo hiểm y tế, khám sức khỏe, hỗ trợ khẩn cấp.', 3),
(4, 'Hỗ trợ ký túc xá',        'KTX',       'Đăng ký phòng, chuyển phòng, sửa chữa cơ sở vật chất, an ninh.', 4),
(5, 'Hỗ trợ khác',             'KHAC',      'Các yêu cầu không thuộc những nhóm nêu trên.',                   5);

-- -----------------------------------------------------------------------------
-- Yêu cầu mẫu ở cả 3 trạng thái để minh họa luồng nghiệp vụ
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_requests`
    (`id`, `user_id`, `category_id`, `title`, `description`, `status`, `staff_assigned_id`, `created_at`) VALUES
(1, 3, 1, 'Xin cấp lại bảng điểm học kỳ 1',
    'Em cần xin cấp lại bảng điểm học kỳ 1 năm học 2025-2026 để nộp hồ sơ xin học bổng. Mong phòng hỗ trợ em sớm ạ.',
    'completed', 2, '2026-08-15 08:30:00'),
(2, 3, 2, 'Đề nghị gia hạn thời gian đóng học phí',
    'Gia đình em gặp khó khăn đột xuất, em xin được gia hạn thời gian đóng học phí học kỳ này thêm 30 ngày.',
    'processing', 2, '2026-08-28 14:10:00'),
(3, 4, 4, 'Đề nghị sửa vòi nước phòng B305 ký túc xá',
    'Vòi nước trong phòng B305 bị rò rỉ liên tục từ ngày 01/09, gây lãng phí nước và ẩm ướt sàn nhà.',
    'processing', 2, '2026-09-02 09:05:00'),
(4, 4, 3, 'Đăng ký tư vấn tâm lý',
    'Em đang bị áp lực học tập kéo dài và mất ngủ. Em muốn đăng ký gặp chuyên viên tư vấn tâm lý của trường.',
    'received', NULL, '2026-09-08 16:45:00'),
(5, 5, 5, 'Xin xác nhận sinh viên để làm vé xe buýt',
    'Em cần giấy xác nhận đang là sinh viên của trường để làm vé xe buýt ưu đãi tháng 10.',
    'received', NULL, '2026-09-10 10:20:00');

-- -----------------------------------------------------------------------------
-- Phản hồi mẫu của cán bộ
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_responses` (`request_id`, `staff_id`, `response_text`, `response_date`) VALUES
(1, 2, 'Chào em, phòng đã tiếp nhận yêu cầu và đang in bảng điểm cho em.', '2026-08-16 09:00:00'),
(1, 2, 'Bảng điểm của em đã hoàn thành. Em vui lòng đến Phòng Công Tác Sinh Viên (phòng 105, nhà A) trong giờ hành chính để nhận. Nhớ mang theo thẻ sinh viên.', '2026-08-18 15:30:00'),
(2, 2, 'Phòng đã nhận đơn của em và đang chuyển sang Phòng Kế hoạch - Tài chính xem xét. Kết quả sẽ được thông báo trong vòng 5 ngày làm việc.', '2026-08-29 10:15:00'),
(3, 2, 'Đã chuyển thông tin cho tổ kỹ thuật ký túc xá. Dự kiến sửa chữa trong ngày 05/09.', '2026-09-03 08:40:00');

-- -----------------------------------------------------------------------------
-- Nhật ký trạng thái mẫu (tương ứng các yêu cầu ở trên)
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_status_log` (`request_id`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(1, NULL,         'received',   3, '2026-08-15 08:30:00'),
(1, 'received',   'processing', 2, '2026-08-16 09:00:00'),
(1, 'processing', 'completed',  2, '2026-08-18 15:30:00'),
(2, NULL,         'received',   3, '2026-08-28 14:10:00'),
(2, 'received',   'processing', 2, '2026-08-29 10:15:00'),
(3, NULL,         'received',   4, '2026-09-02 09:05:00'),
(3, 'received',   'processing', 2, '2026-09-03 08:40:00'),
(4, NULL,         'received',   4, '2026-09-08 16:45:00'),
(5, NULL,         'received',   5, '2026-09-10 10:20:00');

-- Đánh giá hài lòng mẫu cho yêu cầu đã hoàn thành
UPDATE `tbl_requests`
   SET `rating` = 5, `rating_comment` = 'Phòng xử lý rất nhanh, em cảm ơn ạ.'
 WHERE `id` = 1;

-- =============================================================================
-- KẾT THÚC TỆP
-- =============================================================================
