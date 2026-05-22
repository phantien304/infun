/**
 * DatePicker.jsx — wrapper antd DatePicker, thay datepicker.vue
 * -----------------------------------------------------------
 * Bản Vue 2 dùng vuejs-datepicker. antd DatePicker đầy đủ tính năng
 * và đã cài sẵn — không cần thêm package.
 *
 * Format output cũ: 'd/M/yyyy' (vd "13/5/2026"). Em giữ tương thích.
 *
 * Props giữ:
 *   - value      → controlled
 *   - onChange   → emit lên parent (string format "d/M/yyyy")
 *   - format     → format hiển thị (mặc định 'DD-MM-YYYY' giống cũ)
 *   - clearButton → allowClear
 *   - disabled-dates (to: yesterday) → disabledDate
 *
 * Yêu cầu: npm i dayjs (antd v5 dùng dayjs làm date engine)
 * -----------------------------------------------------------
 */

import React from 'react';
import { DatePicker as AntDatePicker } from 'antd';
import dayjs from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';

dayjs.extend(customParseFormat);

export default function DatePicker({
    value,
    onChange,
    format = 'DD-MM-YYYY',
    clearButton = true,
    placeholder,
    disableBeforeToday = false,
}) {
    // value vào có thể là Date hoặc string "d/M/yyyy" — chuyển sang dayjs
    let dayjsValue = null;
    if (value) {
        if (typeof value === 'string') {
            dayjsValue = dayjs(value, ['D/M/YYYY', 'DD-MM-YYYY', format]);
            if (!dayjsValue.isValid()) dayjsValue = dayjs(value);
        } else {
            dayjsValue = dayjs(value);
        }
    }

    return (
        <AntDatePicker
            value={dayjsValue && dayjsValue.isValid() ? dayjsValue : null}
            format={format}
            allowClear={clearButton}
            placeholder={placeholder || 'Chọn ngày'}
            disabledDate={
                disableBeforeToday
                    ? (current) => current && current < dayjs().startOf('day')
                    : undefined
            }
            onChange={(d) => {
                if (!d) {
                    onChange?.('');
                    return;
                }
                // Giữ format output cũ: "d/M/yyyy"
                const out = `${d.date()}/${d.month() + 1}/${d.year()}`;
                onChange?.(out);
            }}
        />
    );
}
