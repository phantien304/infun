{{--
    About Us section trang chủ — image trái + text phải, 50/50 trên md+,
    stack vertical trên mobile. Class theme `.title.style-3`, `.text-9` giữ
    nguyên.

    ẢNH: trỏ tới /client/images/banner/Foodsafe-1.png — đường dẫn KHÔNG TỒN
    TẠI (thư mục public/client/ không có trong repo, chắc sót lại từ demo
    khác). Tạm thời rơi về ảnh placeholder qua onerror để không vỡ layout;
    CẦN thay bằng ảnh thật (xưởng/sản phẩm dấu khắc của In&Fun) — tốt nhất
    lấy từ CMS (getConfigDb hoặc field ảnh riêng) thay vì hard-code path,
    để đổi ảnh không phải sửa code.
--}}
<div class="container mx-auto max-w-7xl px-4">
    <section class="text-center mt-50 mb-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <div>
                <img src="/client/images/banner/Foodsafe-1.png"
                    onerror="this.onerror=null;this.src='https://picsum.photos/seed/infun-about/636/480'"
                    class="rounded-lg w-full max-w-[636px] h-auto mx-auto" alt="Xưởng sản xuất In&amp;Fun">
            </div>
            <div class="xl:text-left">
                <h3 class="title style-3 text-9">Về chúng tôi</h3>
                <p>In&Fun chuyên gia công các sản phẩm dấu khắc, in ấn và thiết kế theo yêu cầu, giúp thương hiệu của
                    bạn tỏa sáng trên mọi chất liệu: giấy kraft, vải canvas, hộp carton, hay bao bì sản phẩm. Với công
                    nghệ hiện đại và sự tỉ mỉ trong từng chi tiết, In&Fun không chỉ sản xuất công cụ đóng dấu chất lượng
                    mà còn giúp bạn kể câu chuyện thương hiệu một cách ấn tượng và khác biệt.</p>
            </div>
        </div>
    </section>
</div>
