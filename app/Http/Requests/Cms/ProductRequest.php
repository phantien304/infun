<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'model' => 'required|string|max:255',
            // QUAN TRỌNG: khác với lỗi "excludeUnvalidatedArrayKeys" ở các
            // field mảng bên dưới, đây là lý do ĐƠN GIẢN HƠN nhưng còn
            // nghiêm trọng hơn — $validator->validated() (Illuminate\
            // Validation\Validator::validated()) chỉ lặp qua đúng những KEY
            // đã có rule khai báo (`foreach ($this->getRules() as $key =>
            // ...)`). Suốt từ đầu, TOÀN BỘ field phẳng cấp product (upc, ean,
            // jan, isbn, mpn, location, badge, manufacturer_id, tax_class_id,
            // stock_status_id, shipping, is_add_cart, is_custom, is_review,
            // length/width/height, length_class_id, weight, weight_class_id,
            // points, sort_order, date_available, link_sale, image, sku...)
            // KHÔNG có rule nào cả ⇒ không bao giờ xuất hiện trong
            // $request->validated() ⇒ ProductWriteService::save() (dùng
            // array_key_exists($f, $data) để quyết định có set hay không)
            // không bao giờ ghi các field này xuống DB, dù frontend gửi đúng
            // giá trị người dùng nhập/chọn. Phát hiện qua báo lỗi thực tế:
            // gõ UPC không lưu được — kiểm tra thêm thì stock_status_id/
            // weight_class_id/length_class_id của sản phẩm mới tạo cũng bị
            // null/0 dù form có giá trị mặc định hợp lệ. Bắt buộc khai rule
            // (dù chỉ nullable) cho MỌI field phẳng thì validated() mới giữ
            // lại — đây là rule bắt buộc phải có, không phải tối ưu thêm.
            'sku'              => 'nullable|string|max:255',
            'upc'              => 'nullable|string|max:255',
            'ean'              => 'nullable|string|max:255',
            'jan'              => 'nullable|string|max:255',
            'isbn'             => 'nullable|string|max:255',
            'mpn'              => 'nullable|string|max:255',
            'location'         => 'nullable|string|max:255',
            'image'            => 'nullable|string',
            'badge'            => 'nullable|string|max:255',
            'date_available'   => 'nullable|string',
            'link_sale'        => 'nullable|string',
            'link_sale_custom' => 'nullable',
            'manufacturer_id'  => 'nullable|integer',
            'tax_class_id'     => 'nullable|integer',
            'stock_status_id'  => 'nullable|integer',
            'shipping'         => 'nullable',
            'is_add_cart'      => 'nullable',
            'is_custom'        => 'nullable',
            'is_review'        => 'nullable',
            'length'           => 'nullable',
            'width'            => 'nullable',
            'height'           => 'nullable',
            'length_class_id'  => 'nullable|integer',
            'weight'           => 'nullable',
            'weight_class_id'  => 'nullable|integer',
            'points'           => 'nullable',
            'sort_order'       => 'nullable',

            'product_descriptions'                  => 'array',
            'product_descriptions.*.language_code'  => 'required|string|max:11',
            // QUAN TRỌNG: FormRequest::validated() bật
            // $validator->excludeUnvalidatedArrayKeys = true theo mặc định.
            // Hễ đã có ÍT NHẤT 1 rule con dạng "product_descriptions.*.xxx",
            // validated() sẽ LOẠI BỎ mọi field con KHÔNG có rule tương ứng —
            // dù field đó có mặt trong request và không lỗi validate gì. Trước
            // đây chỉ khai báo language_code + name ⇒ description/content/
            // meta_title/meta_description/meta_keyword bị validated() âm thầm
            // xoá khỏi $request->validated(), khiến mọi lần Save đều xoá mất
            // mô tả sản phẩm (product_descriptions.*.description = null) dù
            // frontend gửi đúng dữ liệu. Phải khai đủ rule (dù chỉ nullable)
            // cho TẤT CẢ field con thì validated() mới giữ lại chúng.
            'product_descriptions.*.description'      => 'nullable|string',
            'product_descriptions.*.content'           => 'nullable|string',
            'product_descriptions.*.tag'                => 'nullable|string',
            'product_descriptions.*.meta_title'         => 'nullable|string|max:255',
            'product_descriptions.*.meta_description'   => 'nullable|string',
            'product_descriptions.*.meta_keyword'       => 'nullable|string',

            'product_categories'  => 'array',
            'product_filters'     => 'array',
            'product_related'     => 'array',
            'product_ingredients' => 'array',
            'product_attributes'  => 'array',
            'product_images'      => 'array',
            // 'product_discounts' đã bỏ (2026-08-02): product_discount table
            // dropped, chiết khấu giờ nằm trong product_variants.*.discounts.*
            // (xem rule bên dưới) — theo variant, không còn theo product.
            'product_rewards'     => 'array',
            'product_options'     => 'array',
            'product_variants'    => 'array',
            'product_variants.*.option_value_ids' => 'array',
            // Cùng lý do như trên: trước đây CHỈ có rule cho option_value_ids
            // ⇒ validated() xoá sạch price/on_hand/sku/... của MỌI variant
            // trong MỌI lần Save (bug xoá giá/kho phát hiện ở sản phẩm test
            // id 500000, xác nhận qua log storage/logs/rcms — validated_variants
            // chỉ còn mỗi option_value_ids). Khai đủ rule (nullable, backend
            // ProductVariantWriter tự ép kiểu/validate lại lần nữa) cho toàn
            // bộ field variant để validated() không còn loại bỏ chúng.
            'product_variants.*.id'                  => 'nullable',
            'product_variants.*.price'               => 'nullable',
            'product_variants.*.regular_price'       => 'nullable',
            'product_variants.*.sku'                 => 'nullable|string|max:255',
            'product_variants.*.minimum'             => 'nullable',
            'product_variants.*.sort_order'          => 'nullable',
            'product_variants.*.is_default'          => 'nullable',
            'product_variants.*.on_hand'             => 'nullable',
            'product_variants.*.inventory_policy'    => 'nullable',
            // Đa kho — product/Option.jsx gửi ma trận variant × kho. Cùng lý do
            // "excludeUnvalidatedArrayKeys" ghi ở trên: thiếu field con nào ở
            // đây thì field đó bị validated() lặng lẽ xoá khỏi mọi row stocks.
            // 'on_hand'/'inventory_policy' phẳng phía trên vẫn giữ nguyên làm
            // fallback tương thích ngược (xem ProductVariantWriter::syncVariantStocks).
            'product_variants.*.stocks'                        => 'nullable|array',
            'product_variants.*.stocks.*.warehouse_id'         => 'nullable|integer',
            'product_variants.*.stocks.*.on_hand'              => 'nullable',
            'product_variants.*.stocks.*.inventory_policy'     => 'nullable',
            'product_variants.*.special_price'       => 'nullable',
            'product_variants.*.special_priority'    => 'nullable',
            'product_variants.*.special_date_start'  => 'nullable|string',
            'product_variants.*.special_date_end'    => 'nullable|string',
            // Chiết khấu theo số lượng (quantity-tier), theo variant — thay
            // product_discounts cũ (product-level). Mảng lồng 2 cấp
            // (product_variants.*.discounts.*.xxx): PHẢI khai đủ rule cho
            // TỪNG field con, y hệt lý do đã ghi ở trên cho product_variants —
            // thiếu bất kỳ field nào ở đây sẽ bị validated() lặng lẽ xoá.
            'product_variants.*.discounts'                     => 'nullable|array',
            'product_variants.*.discounts.*.id'                => 'nullable',
            'product_variants.*.discounts.*.user_group_id'     => 'nullable|integer',
            'product_variants.*.discounts.*.quantity'          => 'nullable|integer|min:1',
            'product_variants.*.discounts.*.priority'          => 'nullable|integer',
            'product_variants.*.discounts.*.price'             => 'nullable',
            'product_variants.*.discounts.*.date_start'        => 'nullable|string',
            'product_variants.*.discounts.*.date_end'          => 'nullable|string',
        ];

        foreach ((array) $this->input('product_descriptions', []) as $i => $item) {
            $rules["product_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
