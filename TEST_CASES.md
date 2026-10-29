# HỒ SƠ KIỂM THỬ HỆ THỐNG

**Đề tài 63: Hệ Thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên**

- Môi trường kiểm thử: PHP 8.2.12, MariaDB 10.4.32, trình duyệt nhân Chromium
- Dữ liệu đầu vào: bộ dữ liệu mẫu trong `sql/db.sql` (5 người dùng, 5 danh mục, 5 yêu cầu)
- Ký hiệu kết quả: **Đạt** = chạy đúng như mong đợi

---

## A. ĐĂNG KÝ, ĐĂNG NHẬP, PHIÊN LÀM VIỆC

| Mã | Chức năng | Dữ liệu vào | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_001 | Đăng ký tài khoản | Họ tên, MSSV, email hợp lệ, mật khẩu `Sinhvien@123` | Tạo tài khoản, băm mật khẩu, gửi email chào mừng, chuyển về trang đăng nhập | Bản ghi mới trong `tbl_users` với mật khẩu dạng `$2y$10$...`, email ghi vào `logs/email.log` | Đạt |
| TC_002 | Đăng ký trùng email | Email `an.tran@sv.ulsa.edu.vn` đã tồn tại | Báo lỗi "Địa chỉ email này đã được đăng ký" | Đúng thông báo, không tạo bản ghi | Đạt |
| TC_003 | Đăng ký mật khẩu yếu | Mật khẩu `12345678` | Chặn, báo thiếu chữ in hoa | "Mật khẩu phải có ít nhất một chữ cái in hoa.", không tạo tài khoản | Đạt |
| TC_004 | Đăng nhập đúng | `an.tran@sv.ulsa.edu.vn` / `Sinhvien@123` | Chuyển tới "Yêu cầu của tôi" | HTTP 302 → `user/index.php`, tiêu đề trang "Yêu cầu của tôi" | Đạt |
| TC_005 | Đăng nhập sai mật khẩu | `an.tran@sv.ulsa.edu.vn` / `Sai@12345` | Báo "Email hoặc mật khẩu không đúng" | Đúng thông báo chung, không lộ email có tồn tại hay không | Đạt |
| TC_006 | Đăng nhập email không tồn tại | `khongcó@sv.ulsa.edu.vn` | Thông báo giống hệt TC_005 | Đúng, cùng một thông báo | Đạt |
| TC_007 | Đăng xuất | Bấm "Đăng xuất" | Hủy phiên, quay về trang đăng nhập | HTTP 302 → `login.php`, phiên bị hủy | Đạt |
| TC_008 | Hết hạn phiên | Sửa mốc `last_activity` trong tệp phiên lùi 40 phút rồi tải lại trang | Tự đăng xuất, báo "Phiên làm việc đã hết hạn" | HTTP 302 → `login.php`, hiện đúng thông báo hết hạn; mở tiếp trang hồ sơ vẫn bị chuyển về đăng nhập | Đạt (sau khi sửa lỗi, xem mục G) |
| TC_043 | Sinh viên đăng nhập ở trang quản trị | `binh.le@sv.ulsa.edu.vn` / `Sinhvien@123` tại `admin/login.php` | Từ chối dù đúng mật khẩu | Báo "Tài khoản này không có quyền truy cập khu vực quản trị.", không tạo phiên đăng nhập | Đạt |
| TC_044 | Cán bộ đăng nhập ở trang sinh viên | `staff@system.com` / `Staff@123` tại `user/login.php` | Tự chuyển tới khu vực quản trị | HTTP 302 → `admin/index.php` | Đạt |

---

## B. GỬI VÀ THEO DÕI YÊU CẦU (PHÍA SINH VIÊN)

| Mã | Chức năng | Dữ liệu vào | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_009 | Gửi yêu cầu hợp lệ | Danh mục "Hỗ trợ tài chính", tiêu đề 44 ký tự, nội dung 124 ký tự, đính kèm PNG | Tạo yêu cầu, ghi nhật ký trạng thái, gửi email, chuyển tới trang chi tiết | Tạo `YC-000007`, HTTP 302 → `view_request.php?id=7`, email đã ghi log | Đạt |
| TC_010 | Thiếu tiêu đề | Bỏ trống ô tiêu đề | Báo "Vui lòng nhập tiêu đề yêu cầu" | Đúng thông báo, không tạo bản ghi | Đạt |
| TC_011 | Tiêu đề quá ngắn | Tiêu đề 5 ký tự | Báo cần ít nhất 10 ký tự | Đúng thông báo | Đạt |
| TC_012 | Tệp sai định dạng | Tải lên tệp `.exe` | Từ chối, báo danh sách định dạng cho phép | Bị chặn ở bước kiểm tra đuôi tệp, tệp không được lưu | Đạt |
| TC_013 | Tệp vượt 5MB | Tải lên tệp PDF 6MB | Từ chối, báo "Dung lượng tệp vượt quá 5MB" | Đúng thông báo; PHP chặn độc lập với JavaScript (JavaScript chỉ chặn sớm theo dung lượng, định dạng tệp giới hạn bằng thuộc tính `accept`) | Đạt |
| TC_014 | Tệp giả mạo đuôi | Đổi tên `virus.exe` thành `anh.png` | Từ chối vì MIME thật không khớp | `finfo_file()` phát hiện, báo "Nội dung tệp không khớp" | Đạt |
| TC_015 | Theo dõi tiến độ | Mở chi tiết `YC-000007` | Hiện thanh tiến độ, dòng thời gian 3 mốc, danh sách phản hồi | Hiện đúng trạng thái "Đã tiếp nhận" và mốc thời gian tương ứng | Đạt |
| TC_016 | Lọc và phân trang | 19 yêu cầu, lọc trạng thái "Đã tiếp nhận", sang trang 2 | 10 dòng ở trang 1, phần còn lại ở trang 2, giữ nguyên bộ lọc | Trang 1 có 10 dòng, trang 2 có 9 dòng, thanh phân trang hiện đúng "trang 2/2" | Đạt |
| TC_017 | Đánh giá hài lòng | Chọn 5 sao kèm nhận xét, yêu cầu đã hoàn thành | Lưu điểm và nhận xét, hiện lại dạng chỉ đọc | `rating = 5`, nhận xét lưu đúng tiếng Việt trong CSDL | Đạt |

---

## C. XỬ LÝ YÊU CẦU (PHÍA CÁN BỘ)

| Mã | Chức năng | Dữ liệu vào | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_018 | Đăng nhập quản trị | `admin@system.com` / `Admin@123` | Vào bảng điều khiển | HTTP 302 → `admin/index.php`, thống kê hiện đúng số liệu | Đạt |
| TC_019 | Xem danh sách yêu cầu | Đăng nhập cán bộ | Hiện toàn bộ yêu cầu, có phân trang | 19 bản ghi, chia 2 trang, đúng số đếm "Tổng cộng 19 yêu cầu" | Đạt |
| TC_020 | Đổi trạng thái + phản hồi | `YC-000007`: Đã tiếp nhận → Đang xử lý, kèm nội dung phản hồi | Cập nhật trạng thái, lưu phản hồi, ghi nhật ký, gửi email | `tbl_status_log` có bản ghi `received → processing`, phản hồi lưu đúng, email đã ghi log | Đạt |
| TC_021 | Hoàn thành không có phản hồi | Chuyển sang "Hoàn thành" khi chưa từng phản hồi | Chặn, yêu cầu nhập phản hồi trước | Đúng thông báo chặn | Đạt |
| TC_022 | Lưu không thay đổi gì | Giữ nguyên trạng thái, để trống phản hồi | Báo "chưa thay đổi trạng thái và cũng chưa nhập phản hồi" | Đúng thông báo | Đạt |
| TC_023 | Thêm danh mục | Tên "Hỗ trợ thực tập và việc làm", mã `THUC_TAP` | Thêm thành công, hiện trong danh sách | Bản ghi mới id = 6, tên tiếng Việt lưu đúng | Đạt |
| TC_024 | Xóa danh mục đang dùng | Xóa "Hỗ trợ học tập" (có yêu cầu tham chiếu) | Chặn, báo số yêu cầu đang sử dụng | "Không thể xóa: danh mục này đang được 1 yêu cầu sử dụng" | Đạt |
| TC_025 | Xóa danh mục chưa dùng | Xóa danh mục vừa tạo ở TC_023 | Xóa thành công | Đã xóa, còn lại 5 danh mục | Đạt |
| TC_026 | Phân quyền trang Tài khoản | Quản trị viên và cán bộ cùng mở `admin/manage_staff.php` | Chỉ Quản trị viên vào được | Quản trị viên: HTTP 200, hiện danh sách tài khoản kèm vai trò. Cán bộ: HTTP 403 "Không có quyền" | Đạt |

---

## D. BÁO CÁO VÀ KẾT XUẤT

| Mã | Chức năng | Dữ liệu vào | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_027 | Trang báo cáo | Mở `admin/reports.php` | Thống kê theo trạng thái, danh mục, tháng, điểm hài lòng | HTTP 200, đủ 4 khối thống kê | Đạt |
| TC_028 | Xuất Excel | Bấm "Xuất Excel" | Tải về tệp mở được bằng Excel, tiếng Việt đúng dấu | Tải về `bao_cao_yeu_cau_....csv`, có BOM UTF-16LE, cột phân cách bằng Tab. Mở bằng Microsoft Excel 16: đủ 9 cột, ô B1 "Mã yêu cầu", ô C2 "Trần Văn An" đúng dấu | Đạt |
| TC_029 | Xuất PDF | Bấm "Xuất PDF" | Mở trang báo cáo và hộp thoại in, lưu được thành PDF | Trang in HTTP 200, tự gọi `window.print()`, khổ A4 nằm ngang. In thử bằng trình duyệt Edge ra tệp PDF thật: 1 trang A4 ngang, dòng tiêu đề bảng giữ màu xanh, tiếng Việt đủ dấu, dải hướng dẫn phía trên bị ẩn khi in | Đạt |
| TC_030 | Xuất theo bộ lọc | Lọc trạng thái "Đang xử lý" rồi xuất Excel | Tệp chỉ chứa yêu cầu đang xử lý | CSDL có 3 yêu cầu "Đang xử lý"; tệp xuất ra đúng 4 dòng (1 dòng tiêu đề + 3 dòng dữ liệu) | Đạt |
| TC_031 | Xuất khi không có dữ liệu | Lọc điều kiện không khớp gì rồi xuất | Không tạo tệp rỗng, quay lại kèm cảnh báo | Chuyển hướng kèm thông báo "Không có dữ liệu nào khớp điều kiện lọc" | Đạt |

---

## E. KIỂM THỬ BẢO MẬT

| Mã | Chức năng | Dữ liệu vào | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_032 | Chống CSRF | Gửi POST tạo yêu cầu không kèm token | Từ chối với mã 419 | HTTP 419, hiện thông báo mã bảo mật không hợp lệ | Đạt |
| TC_033 | Chống SQL Injection | Ô tìm kiếm nhập `' OR 1=1 -- ` | Coi là chuỗi tìm kiếm bình thường, không trả về toàn bộ bảng | Kết quả "Không có yêu cầu nào khớp điều kiện lọc" | Đạt |
| TC_034 | Chống XSS | Ô tìm kiếm nhập `<script>alert(1)</script>` | Hiển thị lại dưới dạng văn bản, không chạy mã | Kết quả in ra `&lt;script&gt;alert(1)&lt;/script&gt;`, không có thẻ script thật | Đạt |
| TC_035 | Phân quyền khu vực quản trị | Tài khoản sinh viên mở `admin/manage_requests.php` | Từ chối với mã 403 | HTTP 403 kèm trang "Không có quyền truy cập" | Đạt |
| TC_036 | Bắt buộc đăng nhập | Khách vãng lai mở `user/index.php` | Chuyển về trang đăng nhập | HTTP 302 → `user/login.php` | Đạt |
| TC_037 | Xem trộm dữ liệu người khác | Sinh viên A mở yêu cầu của sinh viên B qua URL | Từ chối và chuyển về danh sách của mình | HTTP 302 → `user/index.php` kèm thông báo không có quyền | Đạt |
| TC_038 | Cán bộ xóa yêu cầu | Đăng nhập vai trò staff, gửi POST xóa kèm token CSRF hợp lệ | Chặn ở phía máy chủ, không xóa dữ liệu | Nút xóa không hiển thị (0 nút); POST giả lập bị chặn với thông báo "Chỉ Quản trị viên mới được xóa yêu cầu", bản ghi vẫn còn trong CSDL | Đạt |
| TC_039 | Dữ liệu sai mã hóa | Gửi chuỗi có byte UTF-8 hỏng | Làm sạch an toàn, không gây lỗi logic | `clean_input()` loại bỏ byte hỏng, các bước kiểm tra phía sau chạy đúng | Đạt |
| TC_045 | Chặn xóa yêu cầu đã xử lý | Quản trị viên gửi POST xóa (kèm token CSRF hợp lệ) một yêu cầu đã "Hoàn thành", sau đó xóa một yêu cầu mới "Đã tiếp nhận" chưa có phản hồi | Yêu cầu đã xử lý được giữ nguyên; yêu cầu chưa xử lý xóa được | Lần 1 báo "Không thể xóa YC-000006: yêu cầu đã được xử lý hoặc đã có phản hồi…", yêu cầu và 2 phản hồi còn nguyên; lần 2 xóa thành công. Nút Xóa chỉ hiện ở yêu cầu "Đã tiếp nhận" (chạy lại 18/09/2026) | Đạt |

---

## F. GIAO DIỆN VÀ TƯƠNG THÍCH

| Mã | Chức năng | Cách kiểm tra | Kết quả mong đợi | Kết quả thực tế | Đánh giá |
|---|---|---|---|---|---|
| TC_040 | Hiển thị trên màn hình hẹp | Mở trang chủ ở khung nhìn rộng 529px | Menu gom thành nút ba gạch, các khối xếp chồng | Đúng: nút ba gạch xuất hiện, ô thống kê và thẻ danh mục xếp dọc, không tràn ngang | Đạt |
| TC_041 | Kiểm tra biểu mẫu phía trình duyệt | Trang đăng ký: để trống, nhập email `abc`, SĐT `123`, mật khẩu `12345678` | Trình duyệt chặn gửi, thông báo bằng tiếng Việt | Trang không chuyển; lần lượt hiện "Vui lòng nhập thông tin này.", "Địa chỉ email không hợp lệ.", "Số điện thoại phải gồm 10-11 chữ số và bắt đầu bằng số 0.", "Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và chữ số."; sửa mật khẩu đúng thì hết lỗi | Đạt |
| TC_042 | Kiểm tra khi không có JavaScript | Gửi biểu mẫu thiếu dữ liệu bằng công cụ dòng lệnh (không chạy JS) | Máy chủ vẫn chặn và báo lỗi | Toàn bộ ca TC_010 đến TC_014 được chạy theo cách này, máy chủ đều chặn đúng | Đạt |

---

## G. TỔNG KẾT

| Nhóm | Số ca | Đạt | Không đạt |
|---|---|---|---|
| A. Đăng nhập và phiên làm việc | 10 | 10 | 0 |
| B. Chức năng sinh viên | 9 | 9 | 0 |
| C. Chức năng cán bộ | 9 | 9 | 0 |
| D. Báo cáo và kết xuất | 5 | 5 | 0 |
| E. Bảo mật | 9 | 9 | 0 |
| F. Giao diện | 3 | 3 | 0 |
| **Tổng** | **45** | **45** | **0** |

**Hai lỗi phát hiện và đã sửa trong quá trình kiểm thử**

**Lỗi 1 – phát hiện khi chạy TC_009.** Với dữ liệu không phải UTF-8 hợp lệ, hệ
thống báo sai thông điệp lỗi ("tiêu đề quá ngắn" thay vì "chưa nhập tiêu đề").
Nguyên nhân: hàm `preg_replace()` dùng cờ `/u` trả về `null` khi gặp chuỗi UTF-8
hỏng, khiến `clean_input()` trả về `null` thay vì chuỗi rỗng, và phép so sánh
nghiêm ngặt `=== ''` phía sau không bắt được trường hợp này. Đã sửa: loại bỏ
byte hỏng bằng `mb_convert_encoding()` trước khi lọc và bảo đảm hàm luôn trả về
chuỗi. Ca kiểm thử TC_039 được bổ sung để canh phòng lỗi này tái diễn.

**Lỗi 2 – phát hiện khi chạy TC_008.** Khi phiên hết hạn, hệ thống có đăng xuất
và chuyển hướng đúng, nhưng thông báo "Phiên làm việc đã hết hạn" không hiển
thị. Nguyên nhân: hàm `logout_user()` xóa luôn cookie phiên, nên thông báo vừa
được ghi vào phiên mới lập tức mất chỗ lưu. Đã sửa trong `check_session_timeout()`:
thay vì gọi `logout_user()`, chỉ xóa dữ liệu phiên và cấp ID phiên mới bằng
`session_regenerate_id(true)` — vẫn an toàn mà giữ được thông báo cho người dùng.
