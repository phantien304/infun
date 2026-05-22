/**
 * Pager.jsx — wrapper antd Pagination, giữ API giống pager.vue cũ
 * -----------------------------------------------------------
 * Bản Vue 2 tự viết logic phân trang (~120 dòng, tính ellipsis...).
 * antd Pagination đã làm sẵn — em chỉ wrap để giữ tên prop quen thuộc.
 *
 * Mapping:
 *   - props.total, pageIndex, pageSize    → giữ nguyên (antd dùng current+pageSize+total)
 *   - props.showNumber                    → bỏ luôn (antd hiển thị mặc định)
 *   - props.showNextPrev                  → simple=false (mặc định antd có prev/next)
 *   - @change                             → onChange prop
 *   - @update:pageIndex                   → cũng qua onChange (parent setState)
 *
 * Khác Vue 2:
 *   - Hiển thị page 1 khi total <= pageSize: antd ẩn, mình giữ vẫn ẩn (giống cũ).
 * -----------------------------------------------------------
 */

import React from 'react';
import { Pagination } from 'antd';

export default function Pager({
    total = 0,
    pageIndex = 1,
    pageSize = 20,
    onChange,
}) {
    if (total <= pageSize) return null;

    return (
        <Pagination
            current={pageIndex}
            pageSize={pageSize}
            total={total}
            onChange={(page) => onChange?.(page)}
            showSizeChanger={false}
        />
    );
}
