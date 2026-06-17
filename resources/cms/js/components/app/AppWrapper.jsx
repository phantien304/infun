/**
 * AppWrapper.jsx — convert từ app-wrapper.vue + entry.vue
 * -----------------------------------------------------------
 * 2 file Vue 2 này thực ra rất nhỏ:
 *   - entry.vue       → chỉ render <app-wrapper/>
 *   - app-wrapper.vue → init system, hiển thị loading, render router-view
 *
 * Em gộp thành 1 component AppWrapper. Component này bao quanh router
 * trong app.jsx, đảm nhiệm fetch config trước khi router render.
 *
 * Mapping:
 *   - data().done            → state.done từ useInitSystem
 *   - created() initSystem   → useInitSystem hook
 *   - mapActions(initSystem, updateAppSettings) → đặt logic trong useInitSystem
 *   - <router-view/>         → {children} (router nằm ngoài AppWrapper)
 *   - v-if="done"            → done && children
 * -----------------------------------------------------------
 */

import React from 'react';
import useInitSystem from '@/core/hooks/useInitSystem';

export default function AppWrapper({ children }) {
    const { done, error } = useInitSystem();

    if (error) {
        return (
            <div style={{ padding: 32, textAlign: 'center' }}>
                <h4>Không khởi tạo được hệ thống</h4>
                <p>{error.message || 'Vui lòng thử lại'}</p>
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => window.location.reload()}
                >
                    Tải lại
                </button>
            </div>
        );
    }

    if (!done) {
        // Loading overlay đã được useLoading hiển thị; ở đây giấu UI cho gọn
        return null;
    }

    return <div id="app-entry">{children}</div>;
}
