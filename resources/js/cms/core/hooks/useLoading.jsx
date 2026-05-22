/**
 * useLoading.jsx
 * -----------------------------------------------------------
 * Mapping từ Vue 2 (element-ui Loading.service):
 *
 *   Vue 2:
 *     const loading = this.$loading({ lock: true });
 *     loading.close();
 *
 *   React:
 *     // 1) Wrap App bằng <LoadingProvider> 1 lần
 *     // 2) Trong component:
 *     const loading = useLoading();
 *     const instance = loading.open();   // hiện spinner
 *     instance.close();                  // tắt spinner
 *
 * Đặc điểm: GIỮ NGUYÊN API .open() / .close() để giống bản cũ nhất.
 *
 * Cài đặt: npm i antd
 * -----------------------------------------------------------
 */

import React, { createContext, useCallback, useContext, useRef, useState } from 'react';
import { Spin } from 'antd';

const LoadingContext = createContext(null);

/**
 * Bọc app:
 *   <LoadingProvider>
 *     <App />
 *   </LoadingProvider>
 */
export function LoadingProvider({ children }) {
    // Đếm số lần "open" để hỗ trợ nhiều request đồng thời.
    // Khi tất cả đều close() thì spinner mới ẩn.
    const [count, setCount] = useState(0);
    const counter = useRef(0);

    const open = useCallback(() => {
        counter.current += 1;
        setCount(counter.current);

        let closed = false;
        return {
            close: () => {
                if (closed) return;
                closed = true;
                counter.current = Math.max(0, counter.current - 1);
                setCount(counter.current);
            },
        };
    }, []);

    return (
        <LoadingContext.Provider value={{ open }}>
            {children}
            {count > 0 && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(255,255,255,0.6)',
                        zIndex: 9999,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <Spin size="large" />
                </div>
            )}
        </LoadingContext.Provider>
    );
}

/**
 * Hook để dùng trong component.
 * Trả về object có hàm open() (giống Loading.service của element-ui).
 */
export default function useLoading() {
    const ctx = useContext(LoadingContext);
    if (!ctx) {
        throw new Error(
            'useLoading must be used inside <LoadingProvider>. Wrap your App with it.'
        );
    }
    return ctx;
}
