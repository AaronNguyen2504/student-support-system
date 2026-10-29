# HƯỚNG DẪN CÀI ĐẶT VÀ CHẠY HỆ THỐNG

**Đề tài 63: Hệ Thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên**
Trường Đại Học Lao Động – Xã Hội CS2 · Môn Lập Trình PHP Cơ Bản

---

## 1. YÊU CẦU MÔI TRƯỜNG

| Thành phần | Phiên bản tối thiểu | Ghi chú |
|---|---|---|
| PHP | 8.0 (khuyến nghị 8.2) | Bật sẵn các phần mở rộng: `mysqli`, `mbstring`, `fileinfo`, `zlib`, `openssl` |
| MySQL / MariaDB | MySQL 5.7 hoặc MariaDB 10.3 | Bảng phải dùng InnoDB để có khóa ngoại |
| Apache | 2.4 | Nên bật `mod_headers` |
| Trình duyệt | Chrome, Edge, Firefox bản mới | |

Cách nhanh nhất là cài **XAMPP 8.x** – đã có sẵn toàn bộ những thứ trên.

> Hệ thống **không cần Composer**. Thư viện PHPMailer đã được đặt sẵn trong
> thư mục `lib/PHPMailer/`. Chức năng xuất Excel và PDF chỉ dùng hàm chuẩn của PHP
> và trình duyệt, không phụ thuộc thư viện ngoài.

---

## 2. CÀI ĐẶT TRONG 6 BƯỚC

### Bước 1 – Chép mã nguồn vào thư mục web

Chép cả thư mục `student-support-system` vào thư mục gốc của máy chủ web:

| Phần mềm | Đường dẫn |
|---|---|
| XAMPP (Windows) | `C:\xampp\htdocs\student-support-system` |
| WAMP | `C:\wamp64\www\student-support-system` |
| XAMPP (macOS/Linux) | `/opt/lampp/htdocs/student-support-system` |

### Bước 2 – Khởi động Apache và MySQL

Mở **XAMPP Control Panel**, bấm **Start** ở hai dòng `Apache` và `MySQL`.

### Bước 3 – Tạo cơ sở dữ liệu

**Cách A – dùng phpMyAdmin (dễ nhất):**

1. Mở `http://localhost/phpmyadmin`
2. Chọn thẻ **Import** (Nhập)
3. Bấm **Choose File**, chọn tệp `sql/db.sql` trong thư mục mã nguồn
4. Bấm **Go**. Tệp này tự tạo cơ sở dữ liệu `student_support`, 5 bảng và dữ liệu mẫu.

**Cách B – dùng dòng lệnh:**

```bash
mysql -u root -p < sql/db.sql
```

Trên Windows nếu lệnh `mysql` chưa có trong PATH:

```bash
C:\xampp\mysql\bin\mysql.exe -u root < sql/db.sql
```

### Bước 4 – Cấu hình kết nối

Mở tệp `config.php` và sửa phần đầu nếu thông tin MySQL của bạn khác mặc định:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');              // XAMPP để trống, WAMP thường cũng trống
define('DB_NAME', 'student_support');
```

Nếu bạn đặt mã nguồn vào thư mục có tên khác, sửa thêm dòng:

```php
define('BASE_URL', '/student-support-system');   // đổi cho khớp tên thư mục
```

### Bước 5 – Cấp quyền ghi cho thư mục

Hai thư mục sau cần quyền ghi:

- `uploads/attachments/` – nơi lưu tệp sinh viên gửi kèm
- `logs/` – nơi ghi nhật ký lỗi và nhật ký email

Trên Windows thường đã có sẵn quyền. Trên Linux/macOS chạy:

```bash
chmod -R 775 uploads logs
```

### Bước 6 – Mở hệ thống

Truy cập: **http://localhost/student-support-system/**

---

## 3. TÀI KHOẢN MẶC ĐỊNH

| Vai trò | Email | Mật khẩu | Đăng nhập tại |
|---|---|---|---|
| Quản trị viên | `admin@system.com` | `Admin@123` | `/admin/login.php` |
| Cán bộ hỗ trợ | `staff@system.com` | `Staff@123` | `/admin/login.php` |
| Sinh viên | `an.tran@sv.ulsa.edu.vn` | `Sinhvien@123` | `/user/login.php` |
| Sinh viên | `binh.le@sv.ulsa.edu.vn` | `Sinhvien@123` | `/user/login.php` |
| Sinh viên | `cuong.pham@sv.ulsa.edu.vn` | `Sinhvien@123` | `/user/login.php` |

Sinh viên mới có thể tự đăng ký tại `/user/register.php`.

> **Trước khi triển khai thật, bắt buộc đổi toàn bộ mật khẩu mặc định ở trên.**

---

## 4. CẤU HÌNH GỬI EMAIL

Mặc định hệ thống chạy ở **chế độ ghi log**: nội dung email được ghi vào
`logs/email.log` thay vì gửi thật. Nhờ vậy có thể demo mà không cần máy chủ thư.

Muốn gửi email thật qua Gmail, sửa trong `config.php`:

```php
define('MAIL_LOG_ONLY', false);                  // tắt chế độ ghi log
define('MAIL_USERNAME', 'email-cua-ban@gmail.com');
define('MAIL_PASSWORD', 'mat-khau-ung-dung');    // KHÔNG dùng mật khẩu Gmail thường
```

Cách lấy **mật khẩu ứng dụng** của Gmail:

1. Bật Xác minh 2 bước cho tài khoản Google
2. Vào <https://myaccount.google.com/apppasswords>
3. Tạo mật khẩu ứng dụng mới, chép chuỗi 16 ký tự vào `MAIL_PASSWORD`

Muốn tắt hẳn tính năng email: đặt `define('MAIL_ENABLED', false);`

---

## 5. XUẤT EXCEL VÀ PDF

Không cần cấu hình gì thêm.

- **Xuất Excel** tải về tệp `.csv`. Mở trực tiếp bằng Excel, tiếng Việt và các cột hiển thị đúng.
- **Xuất PDF** mở một trang báo cáo và tự bật hộp thoại in. Chọn máy in **Lưu thành PDF** (Chrome, Edge) hoặc **Microsoft Print to PDF** rồi bấm Lưu.

Nếu hộp thoại in bị trình duyệt chặn, bấm nút **Mở lại hộp thoại in** ở đầu trang báo cáo.

---

## 6. KIỂM TRA SAU KHI CÀI ĐẶT

Làm lần lượt, cả 6 bước đều phải chạy đúng:

1. Mở trang chủ → thấy 5 nhóm hỗ trợ và số liệu thống kê
2. Đăng nhập bằng tài khoản sinh viên → vào được "Yêu cầu của tôi"
3. Gửi một yêu cầu mới → hiện thông báo thành công và mã `YC-0000xx`
4. Mở `logs/email.log` → thấy nội dung email xác nhận vừa được ghi
5. Đăng nhập tài khoản quản trị → bảng điều khiển hiện đúng số liệu
6. Vào Quản lý yêu cầu → bấm **Xuất Excel** tải về được tệp `.csv`, bấm **Xuất PDF** mở được hộp thoại in

---

## 7. XỬ LÝ SỰ CỐ THƯỜNG GẶP

| Hiện tượng | Nguyên nhân & cách khắc phục |
|---|---|
| "Không thể kết nối cơ sở dữ liệu" | MySQL chưa chạy, hoặc `DB_USER`/`DB_PASS` trong `config.php` sai. Kiểm tra XAMPP Control Panel. |
| Trang trắng, không báo gì | Đặt tạm `define('DEBUG_MODE', true);` trong `config.php` để xem lỗi, hoặc mở `logs/php_error.log`. |
| Chữ tiếng Việt bị lỗi font | Cơ sở dữ liệu phải là `utf8mb4`. Nạp lại `db.sql`, đừng tạo bảng bằng tay. |
| Không tải được tệp đính kèm | Thư mục `uploads/attachments/` thiếu quyền ghi; hoặc `upload_max_filesize` trong `php.ini` nhỏ hơn 5MB. |
| "Mã bảo mật (CSRF token) không đúng" | Phiên làm việc đã hết hạn. Tải lại trang rồi thao tác lại. |
| Không gửi được email | Còn ở chế độ `MAIL_LOG_ONLY = true`, hoặc mật khẩu ứng dụng Gmail sai. |
| Bấm "Xuất PDF" không thấy hộp thoại in | Trình duyệt chặn cửa sổ in tự động. Bấm nút "Mở lại hộp thoại in" hoặc nhấn `Ctrl + P`. |
| Tệp Excel dồn hết vào một cột | Đừng mở rồi lưu lại tệp bằng phần mềm khác trước khi mở bằng Excel; hãy mở trực tiếp tệp vừa tải về. |
| Tệp CSS không được nạp (trang xấu) | `BASE_URL` không khớp tên thư mục thực tế. |
| Bị đăng xuất liên tục | Phiên hết hạn sau 30 phút (sinh viên) / 60 phút (cán bộ). Sửa `SESSION_TIMEOUT_*` nếu cần. |

---

## 8. GỠ CÀI ĐẶT

```sql
DROP DATABASE student_support;
```

Sau đó xóa thư mục `student-support-system` khỏi `htdocs`.

---

## NÂNG CẤP TỪ BẢN CŨ

CSDL đã nạp từ `db.sql` bản cũ cần chạy **một lần**, theo thứ tự:

1. `sql/db_migration.sql` - thêm cột chống dò mật khẩu (`tbl_users.failed_logins`, `tbl_users.locked_until`).
2. `sql/db_migration_2.sql` - thêm index tổng hợp để lọc/sắp xếp nhanh hơn.

Không mất dữ liệu. CSDL mới nạp từ `db.sql` hiện tại đã có sẵn tất cả, không cần chạy.
Tệp đính kèm không còn truy cập trực tiếp qua `/uploads/...`; hệ thống phát qua `download.php` có kiểm tra quyền.
