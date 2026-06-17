/**
 * resources/js/cms/router/index.jsx
 * -----------------------------------------------------------
 * Convert từ router/index.js (Vue Router) sang React Router v6.
 *
 *   - index.js                          → file này
 *   - _import_async.js, _import_sync.js → pageLoader.jsx
 *   - router.beforeEach(requiresAuth)   → RequireAuth.jsx
 *   - components/app                    → <AppLayout />
 *   - components/app-basic              → <AppBasicLayout />
 *
 * Để gọn ~140 routes lặp pattern CRUD (list/add/:id), em dùng helper
 * `crudRoutes(basePath, pageFolder)`. Mỗi entity chỉ cần khai báo 1 dòng
 * trong mảng CRUD_ENTITIES. Sau khi build, kết quả tương đương file gốc.
 *
 * Path gốc Vue 2 dùng base='vcms' → đã đổi sang basename="/vcms" ở app.jsx.
 * -----------------------------------------------------------
 */

import React, { lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Page from './pageLoader';
import RequireAuth from './RequireAuth';

// --- Layouts (tương ứng components/app và components/app-basic) ---
const AppLayout = lazy(() => import('@/components/app/AppLayout'));
const AppBasicLayout = lazy(() => import('@/components/app/AppBasicLayout'));

/**
 * Sinh 3 route CRUD chuẩn cho 1 entity:
 *   /xxx/list  → pageFolder/index
 *   /xxx/add   → pageFolder/form
 *   /xxx/:id   → pageFolder/form
 *
 * Trả về mảng React element. React Router v6 NHẬN mảng <Route> làm con
 * trực tiếp của <Routes> (mỗi element có key) — giống cách Vue 2 spread
 * children, không cần Fragment.
 */
function crudRoutes(basePath, pageFolder) {
    return [
        <Route
            key={basePath + '-list'}
            path={'/' + basePath + '/list'}
            element={<Page name={pageFolder + '/index'} />}
        />,
        <Route
            key={basePath + '-add'}
            path={'/' + basePath + '/add'}
            element={<Page name={pageFolder + '/form'} />}
        />,
        <Route
            key={basePath + '-edit'}
            path={'/' + basePath + '/:id'}
            element={<Page name={pageFolder + '/form'} />}
        />,
    ];
}

/**
 * Bảng entity dùng pattern CRUD chuẩn (list/add/:id).
 * [URL prefix, page folder]
 * Để thêm entity mới: chỉ cần thêm 1 dòng vào đây.
 */
const CRUD_ENTITIES = [
    ['information', 'information'],
    ['banner', 'banner'],
    ['currency', 'currency'],
    ['weight-class', 'weightClass'],
    ['length-class', 'lengthClass'],
    ['tax-class', 'taxClass'],
    ['tax-rate', 'taxRate'],
    ['effect', 'effect'],
    ['skincare', 'skincare'],
    ['safety', 'safety'],
    ['ingredient', 'ingredient'],
    ['country', 'resources/country'],
    ['geo-zone', 'geoZone'],
    ['zone', 'resources/zone'],
    ['province', 'resources/province'],
    ['district', 'resources/district'],
    ['ward', 'resources/ward'],
    ['order', 'order'],
    ['order-status', 'orderStatus'],
    ['payment', 'payment'],
    ['shipping', 'shipping'],
    ['blog', 'blog'],
    ['blog-tag', 'blogTag'],
    ['blog-category', 'blogCategory'],
    ['category', 'category'],
    ['product', 'product'],
    ['store-review', 'storeReview'],
    ['product-draft', 'productDraft'],
    ['manufacturer', 'manufacturer'],
    ['review', 'review'],
    ['option', 'option'],
    ['filter', 'filter'],
    ['attribute', 'attribute'],
    ['stock-status', 'stockStatus'],
    ['carrier', 'carrier'],
    ['carrier-order-status', 'carrierOrderStatus'],
    ['menu', 'menu'],
    ['coupon', 'coupon'],
    ['voucher-theme', 'voucherTheme'],
    ['voucher', 'voucher'],
    ['language', 'language'],
    ['role', 'role'],
    ['user', 'user'],
    ['user-group', 'userGroup'],
];

export default function AppRoutes() {
    return (
        <Routes>
            {/* =========================================================
                Cây 1: App layout — TOÀN BỘ route yêu cầu đăng nhập.
                Vue 2: { path: '/', component: App, children: [...] }
                ========================================================= */}
            <Route
                element={
                    <RequireAuth>
                        <AppLayout />
                    </RequireAuth>
                }
            >
                {/* "/" redirect tới /setting/detail (giống redirect:{name:'settingDetail'}) */}
                <Route index element={<Navigate to="/setting/detail" replace />} />

                <Route path="/logout" element={<Page name="auth/logout" />} />

                {/* --- CRUD entities (sinh từ helper) --- */}
                {CRUD_ENTITIES.flatMap(([base, folder]) => crudRoutes(base, folder))}

                {/* --- Order có thêm /view/:id ngoài CRUD --- */}
                <Route path="/order/view/:id" element={<Page name="order/view" />} />

                {/* --- Contact: chỉ list + :id (không có /add) --- */}
                <Route path="/contact/list" element={<Page name="contact/index" />} />
                <Route path="/contact/:id" element={<Page name="contact/form" />} />

                {/* --- Mail: chỉ /send --- */}
                <Route path="/mail/send" element={<Page name="mail/form" />} />

                {/* --- Report: chỉ /list --- */}
                <Route path="/report/list" element={<Page name="report/index" />} />

                {/* --- Setting: chỉ /detail --- */}
                <Route path="/setting/detail" element={<Page name="setting/detail" />} />

                {/* --- Layout fallback (404, 403) --- */}
                <Route path="/url-not-found" element={<Page name="_layout/url-not-found" />} />
                <Route path="/permission-denied" element={<Page name="_layout/permission-denied" />} />
            </Route>

            {/* =========================================================
                Cây 2: AppBasic layout — KHÔNG cần auth (chỉ /login).
                Vue 2: { path: '/', component: AppBasic, children: [{path:'/login'}] }
                ========================================================= */}
            <Route element={<AppBasicLayout />}>
                <Route path="/login" element={<Page name="auth/login" />} />
            </Route>

            {/* Bất kỳ URL nào không match → về /url-not-found */}
            <Route path="*" element={<Navigate to="/url-not-found" replace />} />
        </Routes>
    );
}
