{{--
    JS của theme cũ — TÁCH RA từ share/footer.blade.php.

    Gồm jQuery, Bootstrap JS, lib.js và style.js. `style.js` là nơi khởi tạo
    slick carousel (gallery sản phẩm, slider trang chủ), ma trận chọn biến thể,
    tính giá real-time theo data-price, và kiểm tồn kho trước khi thêm giỏ.

    VÌ SAO TÁCH: theme ghi đè share/footer.blade.php sẽ mất luôn khối script
    này. Triệu chứng không phải lỗi JS mà là "giao diện vỡ": ảnh gallery xếp
    dọc thay vì thành slider, chọn biến thể không đổi giá, nút mua hàng không
    kiểm tồn. Rất dễ đi tìm nguyên nhân ở CSS.

    Theme nào ghi đè footer mà vẫn dùng markup của base cho gallery/biến thể
    thì PHẢI @include file này — hoặc ở footer của theme, hoặc ở @section('script')
    của riêng trang cần (cách này nhẹ hơn: chỉ trang đó tải jQuery).
--}}
<script type="text/javascript" src="{!! publicUrl('web/js/jquery-3.6.0.min.js') !!}"></script>
{{--
    Bootstrap JS giữ trong giai đoạn coexistence — page chưa convert vẫn cần
    Modal/Dropdown/Collapse cũ. Gỡ ở Phase 7 sau khi mọi page Bootstrap
    interactivity được thay bằng Alpine.
--}}
<script type="text/javascript" src="{!! publicUrl('web/js/bootstrap.min.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/lib.js') !!}"></script>
<script type="text/javascript" src="{!! publicUrl('web/js/style.js?v=' . getConfigDb('config_theme_version')) !!}"></script>
