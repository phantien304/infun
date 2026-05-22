/**
 * useDateLocale.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 mixin:
 *
 *   methods: {
 *     $getDateLocale(date, format = "DD-MM-YYYY") { ... }
 *   }
 *
 * → Trong React, không có mixin. Ta xuất:
 *   - getDateLocale(date, format) : pure function (dùng ở đâu cũng được)
 *   - useDateLocale()             : hook trả về function trên
 *
 * Cách dùng:
 *   import { getDateLocale } from '@/hooks/useDateLocale';
 *   getDateLocale('2026-05-13'); // "13-05-2026"
 *
 *   // hoặc trong component:
 *   const getDate = useDateLocale();
 *   getDate(item.created_at);
 * -----------------------------------------------------------
 */

import moment from 'moment';

export function getDateLocale(date, format = 'DD-MM-YYYY') {
    return moment(date).format(format);
}

export default function useDateLocale() {
    return getDateLocale;
}
