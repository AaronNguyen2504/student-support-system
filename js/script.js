/* =============================================================================
   script.js - Xử lý phía trình duyệt
   =============================================================================
   Hệ Thống Quản Lý Yêu Cầu Hỗ Trợ Sinh Viên

   Các chức năng:
     1. Bật/tắt menu trên màn hình nhỏ
     2. Việt hóa thông báo lỗi của chức năng kiểm tra biểu mẫu có sẵn trong trình
        duyệt (các thuộc tính required, type="email", minlength, pattern).
        Máy chủ VẪN kiểm tra lại toàn bộ, vì JavaScript có thể bị tắt.
     3. Đếm số ký tự còn lại của ô nhập nội dung
     4. Chặn tệp đính kèm vượt 5MB ngay khi chọn
     5. Hỏi xác nhận trước các thao tác xóa
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* -------------------------------------------------------------------------
       1. MENU TRÊN MÀN HÌNH NHỎ
       ---------------------------------------------------------------------- */
    var navToggle = document.getElementById('navToggle');
    var mainNav   = document.getElementById('mainNav');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            var isOpen = mainNav.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    /* -------------------------------------------------------------------------
       2. THÔNG BÁO LỖI BIỂU MẪU BẰNG TIẾNG VIỆT
       Trình duyệt tự kiểm tra biểu mẫu, nhưng viết thông báo theo ngôn ngữ của
       trình duyệt. Đoạn này thay bằng câu tiếng Việt. Với lỗi sai mẫu (pattern),
       nội dung lấy từ thuộc tính title của ô nhập.
       ---------------------------------------------------------------------- */
    document.addEventListener('invalid', function (event) {
        var field    = event.target;
        var validity = field.validity;

        field.setCustomValidity(
            validity.valueMissing    ? 'Vui lòng nhập thông tin này.' :
            validity.typeMismatch    ? 'Địa chỉ email không hợp lệ.' :
            validity.tooShort        ? 'Nội dung phải có ít nhất ' + field.minLength + ' ký tự.' :
            validity.patternMismatch ? (field.title || 'Giá trị không đúng định dạng.') : ''
        );
    }, true);

    // Người dùng sửa lại ô nhập thì xóa thông báo cũ để trình duyệt kiểm tra lại
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            if (event.target.setCustomValidity) {
                event.target.setCustomValidity('');
            }
        }, true);
    });

    /* -------------------------------------------------------------------------
       3. ĐẾM SỐ KÝ TỰ CÒN LẠI
       Dùng cho ô nhập nội dung yêu cầu: <textarea data-counter="2000">
       ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-counter]').forEach(function (field) {
        var max     = parseInt(field.getAttribute('data-counter'), 10);
        var counter = document.createElement('div');
        counter.className = 'form-hint';
        field.parentNode.appendChild(counter);

        function update() {
            var remaining = max - field.value.length;
            counter.textContent = 'Còn lại ' + (remaining < 0 ? 0 : remaining) + ' / ' + max + ' ký tự';
            counter.style.color = remaining < 50 ? '#AE2431' : '';
        }

        field.addEventListener('input', update);
        update();
    });

    /* -------------------------------------------------------------------------
       4. CHẶN TỆP ĐÍNH KÈM QUÁ LỚN
       Định dạng tệp đã được giới hạn bằng thuộc tính accept của ô chọn tệp,
       ở đây chỉ còn kiểm tra dung lượng (HTML không có thuộc tính tương ứng).
       ---------------------------------------------------------------------- */
    var MAX_SIZE = 5 * 1024 * 1024; // 5MB, khớp với hằng MAX_FILE_SIZE của PHP

    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (file && file.size > MAX_SIZE) {
                alert('Dung lượng tệp là '
                    + (file.size / 1024 / 1024).toFixed(2)
                    + 'MB, vượt quá giới hạn 5MB. Vui lòng chọn tệp nhỏ hơn.');
                input.value = '';
            }
        });
    });

    /* -------------------------------------------------------------------------
       5. HỎI XÁC NHẬN TRƯỚC KHI XÓA
       Dùng: <button data-confirm="Bạn có chắc muốn xóa?">
       ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* -------------------------------------------------------------------------
       6. THAY CHO THUỘC TÍNH onclick/onchange TRONG HTML (để CSP chặn được script nội tuyến)
         <select data-submit-on-change>  tự gửi biểu mẫu khi đổi lựa chọn
         <button data-print>             mở hộp thoại in
         <body data-autoprint>           tự mở hộp thoại in khi tải xong trang
       ---------------------------------------------------------------------- */
    document.querySelectorAll('[data-submit-on-change]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    document.querySelectorAll('[data-print]').forEach(function (el) {
        el.addEventListener('click', function () { window.print(); });
    });

    if (document.body.hasAttribute('data-autoprint')) {
        window.print();
    }

    /* -------------------------------------------------------------------------
       7. BẢNG THÀNH DANH SÁCH THẺ TRÊN ĐIỆN THOẠI
       Gắn data-label (lấy từ tiêu đề cột) cho từng ô, rồi bật lớp .is-stacked;
       CSS (style.css, mốc 640px) dựa vào hai thứ này để xếp mỗi dòng thành một thẻ.
       Bảng nhỏ có lớp .table-auto giữ nguyên dạng bảng.
       ---------------------------------------------------------------------- */
    document.querySelectorAll('table.data-table:not(.table-auto)').forEach(function (table) {
        var labels = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
            return th.textContent.trim();
        });
        table.querySelectorAll('tbody tr').forEach(function (row) {
            Array.prototype.forEach.call(row.children, function (cell, i) {
                if (cell.hasAttribute('colspan')) { return; }   // dòng "không có dữ liệu"
                cell.setAttribute('data-label', labels[i] || '');
                if (labels[i] === 'Thao tác') { cell.classList.add('cell-actions'); }
                // Gói nội dung ô vào một phần tử để nhãn và nội dung xếp thành hai cột
                var wrap = document.createElement('div');
                wrap.className = 'cell';
                while (cell.firstChild) { wrap.appendChild(cell.firstChild); }
                cell.appendChild(wrap);
            });
        });
        table.classList.add('is-stacked');
    });

});
