<?php

/*
|--------------------------------------------------------------------------
| Theme storefront — nhiều shop, MỘT codebase
|--------------------------------------------------------------------------
| Mục đích: chạy nhiều bản demo giao diện khác nhau cho khách xem mà không
| phải fork repo hay mỗi shop một nhánh git. Một deployment phục vụ nhiều
| hostname, mỗi hostname ra một giao diện; vá backend thì cả đàn cùng nhận.
|
| Cách hoạt động: view namespace `web` được đăng ký với NHIỀU đường dẫn —
| thư mục theme trước, `resources/web/views` sau. Laravel dò theo thứ tự,
| không thấy thì rơi xuống base. Nên MỘT THEME CHỈ CHỨA FILE NÓ GHI ĐÈ
| (thường 10-15 file), không phải bản sao của 113 file base.
|
| Xem docs/THEME-SYSTEM.md để biết cách thêm theme mới.
*/

return [

    /*
    | Theme mặc định khi không khớp host nào. `null` = dùng thẳng base
    | (resources/web/views), tức hành vi y hệt trước khi có hệ theme.
    */
    'active' => env('THEME', null),

    /*
    | Bản đồ hostname → theme. Đây là thứ cho phép gửi khách nhiều đường
    | link cùng trỏ một máy chủ:
    |
    |   aurora.demo.infun.co  → theme aurora
    |   nova.demo.infun.co    → theme nova
    |
    | Khớp chính xác hostname (không phân biệt hoa thường, đã bỏ cổng).
    | Host không có trong bảng → rơi về 'active'.
    */
    'hosts' => [
        // 'aurora.demo.infun.co' => 'aurora',
        // 'nova.demo.infun.co'   => 'nova',
    ],

    /*
    | Cho phép ép theme bằng query `?theme=aurora`.
    |
    | BẬT ở staging để khách bấm thử qua lại giữa các mẫu mà không cần DNS.
    | TẮT ở production: mỗi giá trị theme là một biến thể URL mới, mà
    | CachePage lấy query làm khoá cache — bật lên ở prod là mở đường cho
    | crawler sinh vô hạn key cache.
    */
    'allow_query_override' => (bool) env('THEME_ALLOW_QUERY', false),

    /*
    | Danh sách theme hợp lệ. Allowlist chứ không quét thư mục: giá trị
    | theme đến từ query/host là input người dùng, nếu đem ghép thẳng vào
    | đường dẫn thì mở cửa cho path traversal.
    */
    'available' => [
        'aurora',
    ],

    /*
    | Thư mục gốc chứa các theme.
    */
    'path' => resource_path('themes'),

    /*
    | Metadata hiển thị cho popup/dropdown chọn theme ở phía khách xem
    | (share/_theme_popup.blade.php, share/_theme_switcher.blade.php và bản
    | inline trong header của aurora). Key 'default' ứng với base (khi
    | ThemeManager::current() === null), các key khác khớp với 'available'
    | ở trên. Thiếu key nào thì UI tự rơi về giá trị mặc định hợp lý
    | (ucfirst tên theme, swatch xám) — không bắt buộc khai đủ khi thêm
    | theme mới.
    */
    'labels' => [
        'default' => 'Mặc định',
        'aurora'  => 'Aurora',
    ],

    'descriptions' => [
        'default' => 'Giao diện gốc của hệ thống.',
        'aurora'  => 'Bento hero, flash sale, sàn TMĐT đa ngành hàng.',
    ],

    'swatches' => [
        'default' => '#f4a883',
        'aurora'  => '#e8452c',
    ],

    /*
    | Khối dữ liệu mỗi theme cần ở trang chủ.
    |
    | Đây là câu trả lời cho "controller có nên theo theme không": KHÔNG —
    | vẫn một HomeController, nhưng nó hỏi theme đang chạy cần khối nào rồi
    | chỉ truy vấn đúng khối đó. Theme base không khai gì nên không phải trả
    | thêm một query nào cho các khối của aurora.
    |
    | Merge kiểu Magento layout XML (<referenceContainer> add/remove theo
    | parent-theme), KHÔNG phải danh sách phẳng: mỗi theme chỉ khai PHẦN
    | KHÁC BIỆT so với cha ('extends', mặc định là 'base' nếu không khai),
    | ThemeManager gộp đệ quy (cha - remove + add). Theme mới muốn giống
    | aurora trừ 1-2 khối thì 'extends' => 'aurora' + 'remove' thay vì copy
    | lại toàn bộ 'add'. Xem ThemeManager::wants()/resolvedBlocks().
    |
    | Ví dụ theme 'nova' kế thừa 'aurora', bỏ flash_sale:
    |   'nova' => ['extends' => 'aurora', 'remove' => ['flash_sale']],
    |
    | Thêm khối mới: khai tên ở đây + xử lý trong HomeController::index().
    */
    'blocks' => [
        // Khối bật sẵn cho MỌI theme (kể cả theme base = null) trừ khi theme
        // đó tự 'remove'. Hiện chưa có khối nào bật mặc định ở base.
        'base' => [],

        'aurora' => [
            'add' => ['categories', 'flash_sale', 'latest'],
        ],
    ],

];
