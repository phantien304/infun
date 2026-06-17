/**
 * pageLoader.jsx
 * -----------------------------------------------------------
 * Helper tương đương _import_async.js + _import_sync.js cũ.
 *
 * Cơ chế:
 *  - Vue 2 dùng require.context('../components/', true, /.vue$/)
 *    để scan toàn bộ file .vue trong components.
 *  - React/Vite dùng import.meta.glob('../pages/...') để scan tương tự
 *    và tự lazy-load.
 *
 * Cách dùng:
 *   <Route path="/banner/list" element={<Page name="banner/index" />} />
 *
 * Nếu page không tồn tại → render UrlNotFound (giống _layout/url-not-found).
 * -----------------------------------------------------------
 */

import React, { Suspense, lazy } from 'react';
import { Spin } from 'antd';

// Vite scan tất cả file .jsx trong pages/ tại build time.
// LƯU Ý: pattern phải là literal string — không truyền biến vào.
const modules = import.meta.glob('../pages/**/*.jsx');

// Map: 'banner/index' → lazy component
const pageMap = {};
for (const path in modules) {
    // '/resources/cms/js/pages/banner/index.jsx' → 'banner/index'
    // Khi Vite resolve, path sẽ là '../pages/banner/index.jsx'
    const key = path.replace(/^\.\.\/pages\//, '').replace(/\.jsx$/, '');
    pageMap[key] = lazy(modules[path]);
}

function PageFallback() {
    return (
        <div style={{ padding: 48, textAlign: 'center' }}>
            <Spin />
        </div>
    );
}

function PageNotFound({ name }) {
    return (
        <div style={{ padding: 32 }}>
            <h3>Page not found</h3>
            <p>
                Không tìm thấy file: <code>resources/cms/js/pages/{name}.jsx</code>
            </p>
        </div>
    );
}

/**
 * Component render 1 page theo "tên" (giống _import('banner/index') cũ).
 *
 * @param {Object} props
 * @param {string} props.name - đường dẫn tương đối trong pages/ (không có .jsx)
 *                              vd: 'banner/index', 'resources/country/form'
 */
export default function Page({ name }) {
    const Comp = pageMap[name];
    if (!Comp) {
        // eslint-disable-next-line no-console
        console.warn('[router] Page not found:', name);
        return <PageNotFound name={name} />;
    }
    return (
        <Suspense fallback={<PageFallback />}>
            <Comp />
        </Suspense>
    );
}
