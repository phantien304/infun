/**
 * resources/js/cms/bootstrap.js
 * -----------------------------------------------------------
 * Tương đương bootstrap.js cũ. Cấu hình axios global trước khi
 * app khởi động:
 *  - Đặt header CSRF token (Laravel meta name="csrf-token")
 *  - Đặt baseURL từ window.appSettings.urlCms (giống Vue 2)
 *  - withCredentials true (giữ session cookie của Laravel)
 * -----------------------------------------------------------
 */

import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// CSRF token từ <meta name="csrf-token" content="...">
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.warn(
        '[cms] CSRF token not found: <meta name="csrf-token"> chưa có trong blade.'
    );
}

// baseURL — sẽ được service/http.js đọc lại từ window.appSettings,
// nhưng set ở đây để các axios call thuần (không qua http.js) cũng dùng được
if (typeof window !== 'undefined' && window.appSettings?.urlCms) {
    window.axios.defaults.baseURL = window.appSettings.urlCms;
}
