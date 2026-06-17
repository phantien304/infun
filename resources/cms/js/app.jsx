/**
 * resources/js/cms/app.jsx
 * -----------------------------------------------------------
 * Entry point của React app — tương đương app.js + entry.vue cũ.
 *
 * Thứ tự bọc (ngoài → trong):
 *   <StrictMode>
 *     <BrowserRouter basename="/vcms">    ← router
 *       <LoadingProvider>                 ← global loading spinner
 *         <AppWrapper>                    ← = app-wrapper.vue (init system)
 *           <AppRoutes />                 ← router config (AppLayout/AppBasicLayout)
 *
 * basename="/vcms" khớp với base: 'vcms' của Vue Router cũ.
 * -----------------------------------------------------------
 */

import './bootstrap';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { LoadingProvider } from '@/core/hooks/useLoading';
import AppWrapper from '@/components/app/AppWrapper';
import AppRoutes from '@/router';

function Root() {
    return (
        <BrowserRouter basename="/vcms">
            <LoadingProvider>
                <AppWrapper>
                    <AppRoutes />
                </AppWrapper>
            </LoadingProvider>
        </BrowserRouter>
    );
}

const container = document.getElementById('cms-app');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Root />
        </React.StrictMode>
    );
} else {
    console.warn('[cms] #cms-app element not found in DOM');
}

export default Root;
