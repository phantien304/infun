<?php

return [
    'weight_class' => [
        'attributes' => attr([
            'language_code' => 'Ngôn ngữ',
            'value' => 'Giá trị',
            'title' => 'Tên',
            'unit' => 'Đơn vị',
        ])
    ],
    'length_class' => [
        'attributes' => attr([
            'language_code' => 'Ngôn ngữ',
            'value' => 'Giá trị',
            'title' => 'Tên',
            'unit' => 'Đơn vị',
        ])
    ],
    'language' => [
        'attributes' => attr([
            'code' => 'Ngôn ngữ',
            'name' => 'Tên',
            'priority' => 'Số thứ tự',
        ])
    ],
    'currency' => [
        'attributes' => attr([
            'title' => 'Tên',
            'unit' => 'Đơn vị',
            'value' => 'Giá trị',
            'code' => 'Mã tiền tệ',
            'symbol_left' => 'Symbol left',
            'symbol_right' => 'Symbol right',
            'decimal_place' => 'Decimal place',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'stock_status' => [
        'attributes' => attr([
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'tax_class' => [
        'attributes' => attr([
            'title' => 'Tên',
            'description' => 'Mô tả',
            'tax_rate_id' => 'Tax rate',
            'tax_class_id' => 'Tax class',
            'based' => 'Loại',
            'priority' => 'Thứ tự',
        ])
    ],
    'tax_rate' => [
        'attributes' => attr([
            'geo_zone_id' => 'Geo Zone',
            'name' => 'Tên',
            'rate' => 'Tỉ lệ',
            'type' => 'Loại',
            'tax_rate_to_user_groups' => 'Customer group',
        ])
    ],
    'country' => [
        'attributes' => attr([
            'name' => 'Tên',
            'iso_code_2' => 'Mã ISO 3166-1 alpha-2',
            'iso_code_3' => 'Mã ISO 3166-1 alpha-3',
            'postcode_required' => 'Postcode',
        ])
    ],
    'zone' => [
        'attributes' => attr([
            'country_id' => 'Quốc gia',
            'ghn_id' => 'GHN Id',
            'ghn_code' => 'GHN code',
            'vtp_id' => 'VTP Id',
            'vtp_code' => 'VTP code',
            'sort_order' => 'Số thứ tự',
            'name' => 'Tên',
            'language_code' => 'Ngôn ngữ',
        ])
    ],
    'geo_zone' => [
        'attributes' => attr([
            'name' => 'Tên',
            'description' => 'Mô tả',
            'country_id' => 'Quốc gia',
            'zone_id' => 'Khu vực',
        ])
    ],
    'district' => [
        'attributes' => attr([
            'name' => 'Tên',
            'zone_id' => 'Khu vực',
            'ghn_id' => 'Ghn Id',
            'code' => 'Ghn code',
            'vtp_id' => 'Vtp Id',
            'vtp_value' => 'Vtp value',
            'type' => 'Loại',
            'support_type' => 'Loại hỗ trỡ',
        ])
    ],
    'ward' => [
        'attributes' => attr([
            'name' => 'Tên',
            'zone_id' => 'Khu vực',
            'district_id' => 'Quận/Huyện',
            'ghn_id' => 'Ghn Id',
            'vtp_id' => 'Vtp Id',
            'name_vtp' => 'Tên Vtp',
            'name_ghn' => 'Tên Ghn',
        ])
    ],
    'skincare' => [
        'attributes' => attr([
            'name' => 'Tên',
            'icon' => 'Icon',
            'image_icon' => 'Image icon',
        ])
    ],
    'safety' => [
        'attributes' => attr([
            'name' => 'Tên',
            'background' => 'Background',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'effect' => [
        'attributes' => attr([
            'name' => 'Tên',
            'icon' => 'Icon',
            'image_icon' => 'Image Icon',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'user_group' => [
        'attributes' => attr([
            'approval' => 'Approval',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
            'description' => 'Mô tả',
        ])
    ],
    'role' => [
        'attributes' => attr([
            'name' => 'Tên',
            'display_name' => 'Tên hiển thị',
            'description' => 'Mô tả',
        ])
    ],
    'carrier' => [
        'attributes' => attr([
            'name' => 'Tên',
            'code' => 'Code',
            'image' => 'Image',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'carrier_order_status' => [
        'attributes' => attr([
            'carrier_id' => 'Đối tác vận chuyển',
            'code' => 'Code',
            'name' => 'Tên',
            'description' => 'Mô tả',
        ])
    ],
    'payment' => [
        'attributes' => attr([
            'code' => 'Code',
            'image' => 'Image',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'user' => [
        'attributes' => attr([
            'send_to' => 'To',
            'users' => 'Users',
            'message' => 'Message',
            'subject' => 'Subject',
            'user_group' => 'User Group',
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Mật khẩu',
            'full_name' => 'Họ tên',
            'address' => 'Địa chỉ',
            'birthday' => 'Ngày sinh',
            'sex' => 'Giới tính',
            'avatar' => 'Avatar',
            'newsletter' => 'Newsletter',
            'status' => 'Trạng thái',
            'type' => 'Loại tài khoản',
            'user_group_id' => 'User group',
            'nation_phone_code' => 'Mã vùng',
            'phone' => 'Số điện thoại',
            'role_id' => 'Role',
        ])
    ],
    'menu' => [
        'attributes' => attr([
            'title' => 'Tên',
            'position' => 'Position',
        ])
    ],
    'menu_value' => [
        'attributes' => attr([
            'menu_id' => 'Menu id',
            'item_id' => '',
            'parent_id' => 'Parent',
            'position' => 'Position',
            'type' => 'Type',
            'css' => 'Css',
            'html_custom' => 'Html custom',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Tên',
            'link' => 'Link',
        ])
    ],
    'coupon' => [
        'attributes' => attr([
            'name' => 'Tên',
            'code' => 'Mã',
            'type' => 'Type',
            'discount' => 'Discount',
            'logged' => 'Logged',
            'shipping' => 'Shipping',
            'total' => 'Total',
            'date_start' => 'Date Start',
            'date_end' => 'Date end',
            'uses_total' => 'Uses Total',
            'uses_customer' => 'Uses Customer',
        ])
    ],
    'voucher' => [
        'attributes' => attr([
            'order_id' => 'Đơn hàng',
            'code' => 'Mã',
            'from_name' => 'From Name',
            'from_email' => 'From Email',
            'to_name' => 'To Name',
            'to_email' => 'To Email',
            'voucher_theme_id' => 'Voucher Theme',
            'message' => 'Message',
            'amount' => 'Amount',
        ])
    ],
    'voucher_theme' => [
        'attributes' => attr([
            'image' => 'Image',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'order_status' => [
        'attributes' => attr([
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'blog' => [
        'attributes' => attr([
            'category_id' => 'Category',
            'viewed' => 'Viewed',
            'image' => 'Image',
            'featured' => 'Featured',
            'files' => 'Files',
            'author_id' => 'Author',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Title',
            'description' => 'Description',
            'content' => 'Content',
            'slug' => 'Slug',
            'tag' => 'Tag',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'blog_category' => [
        'attributes' => attr([
            'parent_id' => 'Category',
            'banner_id' => 'Banner',
            'image' => 'Image',
            'icon' => 'Icon',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Title',
            'description' => 'Description',
            'slug' => 'Slug',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'blog_tag' => [
        'attributes' => attr([
            'background' => 'Background',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Title',
            'description' => 'Description',
            'content' => 'Content',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'category' => [
        'attributes' => attr([
            'parent_id' => 'Parent',
            'sort_order' => 'Số thứ tự',
            'image' => 'Image',
            'money' => 'Money',
            'icon' => 'Icon',
            'image_icon' => 'Image icon',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Title',
            'description' => 'Description',
            'slug' => 'Slug',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'ingredient' => [
        'attributes' => attr([
            'name' => 'Name',
            'description' => 'Description',
            'warning' => 'Warning',
            'warning_text' => 'Warning Text',
        ])
    ],
    'banner' => [
        'attributes' => attr([
            'name' => 'Tên',
            'position' => 'Position',
            'page' => 'Page',
            'type' => 'Type',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'banner_value' => [
        'attributes' => attr([
            'banner_id' => 'Banner',
            'link' => 'Link',
            'sort_order' => 'Số thứ tự',
            'image' => 'Image',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Tên',
            'content' => 'Content',
        ])
    ],
    'information' => [
        'attributes' => attr([
            'banner_id' => 'Banner',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'title' => 'Title',
            'description' => 'Description',
            'content' => 'Content',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'review' => [
        'attributes' => attr([
            'product_id' => 'Product',
            'user_id' => 'User',
            'ip' => 'Ip',
            'email' => 'Email',
            'author' => 'Họ và tên',
            'text' => 'Nhận xét',
            'rating' => 'Đánh giá',
            'is_publish' => 'Publish'
        ])
    ],
    'manufacturer' => [
        'attributes' => attr([
            'name' => 'Tên',
            'image' => 'Image',
            'sort_order' => 'Số thứ tự',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ])
    ],
    'option' => [
        'attributes' => attr([
            'type' => 'Type',
            'sort_order' => 'Số thứ tự',
            'variation' => 'Variation',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Name',
            'name_display' => 'Name display',
        ])
    ],
    'option_value' => [
        'attributes' => attr([
            'option_id' => 'Option',
            'image' => 'Image',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'attribute' => [
        'attributes' => attr([
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Name',
        ])
    ],
    'attribute_value' => [
        'attributes' => attr([
            'icon' => 'Icon',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'filter' => [
        'attributes' => attr([
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Name',
        ])
    ],
    'filter_value' => [
        'attributes' => attr([
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
        ])
    ],
    'product_draft' => [
        'attributes' => attr([
            'product_id' => 'Sản phẩm',
            'name' => 'Tên',
            'description' => 'Description',
            'content' => 'Content',
            'user_id' => 'User',
        ])
    ],
    'product' => [
        'attributes' => attr([
            'model' => 'Model',
            'quantity' => 'Quantity',
            'stock_status_id' => 'Stock status',
            'badge' => 'Badge',
            'image' => 'Image',
            'manufacturer_id' => 'Manufacturer',
            'shipping' => 'Shipping',
            'link_sale' => 'Link sale',
            'price' => 'Price',
            'points' => 'Points',
            'tax_class_id' => 'Tax class',
            'date_available' => 'Date available',
            'weight' => 'Weight',
            'weight_class_id' => 'Weight class',
            'length' => 'Length',
            'width' => 'Width',
            'height' => 'Height',
            'length_class_id' => 'Length class',
            'subtract' => 'Subtract',
            'minimum' => 'Minimum',
            'sort_order' => 'Số thứ tự',
            'language_code' => 'Ngôn ngữ',
            'name' => 'Tên',
            'description' => 'Description',
            'content' => 'Content',
            'tag' => 'Tag',
            'slug' => 'Slug',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'meta_keyword' => 'Meta keyword',
        ])
    ],
    'product_attribute' => [
        'attributes' => attr([
            'attribute_id' => 'Attribute',
            'language_code' => 'Ngôn ngữ',
            'text' => 'Text',
        ])
    ],
    'product_discount' => [
        'attributes' => attr([
            'product_id' => 'Product',
            'user_group_id' => 'User group',
            'quantity' => 'Quantity',
            'priority' => 'Priority',
            'price' => 'Price',
            'date_start' => 'Price',
            'date_end' => 'Price',
        ])
    ],
    'product_specials' => [
        'attributes' => attr([
            'product_id' => 'Product',
            'user_group_id' => 'User group',
            'priority' => 'Priority',
            'price' => 'Price',
            'date_start' => 'Price',
            'date_end' => 'Price',
        ])
    ],
    'product_rewards' => [
        'attributes' => attr([
            'user_group_id' => 'User group',
            'points' => 'Points',
        ])
    ],
    'product_images' => [
        'attributes' => attr([
            'image' => 'Image',
            'sort_order' => 'Số thứ tự',
        ])
    ],
    'product_option' => [
        'attributes' => attr([
            'required' => 'Required',
            'children' => 'Variation',
            'option_values' => 'Option values',
            'option_value_variation' => 'OptionValue2',
            'image' => 'Image',
            'value' => 'Value',
            'sort_order' => 'Số thứ tự',
            'quantity' => 'Quantity',
            'subtract' => 'Subtract',
            'price_prefix' => 'Price prefix',
            'price' => 'Price',
            'points_prefix' => 'Points prefix',
            'points' => 'Points',
            'weight_prefix' => 'Weight prefix',
            'weight' => 'Weight',
        ])
    ],
    'orders' => [
        'attributes' => attr([
            'full_name' => 'Họ và tên',
            'invoice_no' => 'Invoice No',
            'telephone' => 'Số điện thoại',
            'country_id' => 'Quốc gia',
            'zone_id' => 'Thành phố/ Tỉnh',
            'district_id' => 'Quận/ Huyện',
            'ward_id' => 'Phường/ Xã',
            'address' => 'Địa chỉ',
            'email' => 'Email',
            'code_carrier' => 'Mã đơn hàng GHN',
            'order_status_id' => 'Trạng thái đơn hàng',
        ])
    ]
];
