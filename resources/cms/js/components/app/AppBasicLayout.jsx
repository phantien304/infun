/**
 * AppBasicLayout.jsx — tương đương components/app-basic.vue cũ
 * -----------------------------------------------------------
 * Layout tối giản cho trang KHÔNG cần auth (vd: login).
 * Chỉ render <Outlet /> — không sidebar, không header.
 * -----------------------------------------------------------
 */

import React from 'react';
import { Outlet } from 'react-router-dom';

export default function AppBasicLayout() {
    return (
        <div className="auth-layout">
            <Outlet />
        </div>
    );
}
