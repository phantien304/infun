/**
 * RequireAuth.jsx
 * -----------------------------------------------------------
 * Tương đương router.beforeEach(...) + meta.requiresAuth của Vue 2.
 *
 * Cách hoạt động:
 *  - Bọc các route cần auth: <RequireAuth><Page name="..." /></RequireAuth>
 *  - Nếu chưa có USERNAME trong localStorage → redirect /login + giữ returnUrl
 *  - Khác Vue 2: KHÔNG dùng router.push trong hook — dùng <Navigate replace>.
 *
 * Áp dụng cho cả 1 cây route (toàn bộ children của <App />):
 *   <Route element={<RequireAuth><AppLayout /></RequireAuth>}>
 *     <Route path="/banner/list" ... />
 *   </Route>
 * -----------------------------------------------------------
 */

import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import CONSTANTS from '@/core/utils/constants';

export default function RequireAuth({ children }) {
    const location = useLocation();
    const isAuthed =
        typeof localStorage !== 'undefined' &&
        !!localStorage.getItem(CONSTANTS.USERNAME);

    if (!isAuthed) {
        // returnUrl giữ trong location.state — login đọc lại sau khi đăng nhập
        return (
            <Navigate
                to="/login"
                replace
                state={{ returnUrl: location.pathname + location.search }}
            />
        );
    }
    return children;
}
