/**
 * http.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2:
 *   Vue.prototype.$http = request
 *   window.$http = request
 *
 * React không có `Vue.prototype`, nên ta xuất `request` như 1 function
 * thuần — component nào cần thì import trực tiếp HOẶC dùng hook `useHttp`.
 *
 *   import http from '@/services/http';
 *   http({ data: { url: 'product/list' } }).then(...)
 *
 * Hoặc:
 *   const http = useHttp();
 *   http({ data: { url: 'product/list' } }).then(...)
 *
 * Lưu ý:
 *  - Trong Vue 2: this.$router.push() được gọi trực tiếp.
 *    Trong React: dùng react-router-dom, ta xử lý redirect bằng
 *    window.location hoặc truyền `navigate` từ useNavigate() khi cần.
 *  - Đã chuyển 2 biến module-level (path, query, isPushToUrl) thành
 *    closure trong từng request để tránh race condition khi nhiều
 *    request đồng thời.
 * -----------------------------------------------------------
 */

import axios from 'axios';
import CONSTANTS from '../utils/constants';

const client = axios.create({
    baseURL: window.appSettings?.urlCms || '',
});

function processData(response) {
    return response.data;
}

/**
 * Hàm request chính. Giữ nguyên signature options.data như bản Vue 2
 * để các API call cũ không phải sửa.
 *
 * @param {Object} options
 * @param {Object} options.data - payload: { url, m, fn, method, is_push, ... }
 * @param {Function} [navigate] - optional, từ useNavigate() để push route
 * @returns {Promise}
 */
const request = function (options, navigate) {
    client.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    // Đẩy 3 biến này vào closure thay vì module-level (an toàn hơn)
    const isPushToUrl = options.data.is_push;
    let path = '';
    let query = '';

    let fnUrl = '';
    if (options.data.url && options.data.url !== '') {
        path = fnUrl = options.data.url;
        delete options.data.url;
    } else {
        path = fnUrl = options.data.m + '/' + options.data.fn;
    }
    fnUrl = fnUrl + '?json=true';

    const method = options.data.method ?? 'post';
    if (method === 'get') {
        delete options.data.method;
        query = Object.keys(options.data)
            .map((key) => key + '=' + options.data[key])
            .join('&');
        fnUrl = query ? fnUrl + '&' + query : fnUrl;
    }

    const _opts = {
        method,
        formData: false,
        url: fnUrl,
    };
    Object.assign(_opts, options);

    if (_opts.formData) {
        client.defaults.headers.common['Content-Type'] = 'multipart/form-data';
        const form_data = new FormData();
        for (const key in _opts.data) {
            form_data.append(key, _opts.data[key]);
        }
        _opts.data = form_data;
    }

    function isSuccess(response) {
        if (isPushToUrl) {
            // Nếu có navigate (useNavigate hook) thì dùng, không thì fallback
            // sang window.history (giữ URL không reload trang).
            const fullPath = path + '?' + query;
            if (navigate) {
                navigate(fullPath);
            } else {
                window.history.pushState({}, '', fullPath);
            }
        }
        return response.data.success;
    }

    return client(_opts)
        .then((res) =>
            isSuccess(res)
                ? Promise.resolve(processData(res))
                : Promise.reject(processData(res))
        )
        .catch((err) => {
            const res = err.response;
            if (!res) {
                // network error
                return Promise.reject({ message: 'NetworkError', error: err });
            }
            if (res.status === 401) {
                localStorage.removeItem(CONSTANTS.USERNAME);
                window.location.href =
                    window.appSettings.urlCms +
                    '/login?returnUrl=' +
                    path;
            }
            if (res.status === 403) {
                window.location.href =
                    window.appSettings.urlCms + '/permission-denied';
                return Promise.reject(processData(res));
            }
            return Promise.reject(processData(res));
        });
};

// Vẫn expose ra window để các đoạn code legacy (nếu có) còn hoạt động
if (typeof window !== 'undefined') {
    window.$http = request;
}

export default request;
