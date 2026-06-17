/**
 * useClickOutside.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 directive:
 *
 *   const clickOutside = { bind(el, binding) {...}, unbind() {...} }
 *   // Usage: <div v-click-outside="handler">
 *
 * → React không có "directive", thay bằng hook. Hook nhận:
 *   - ref: React ref tới element
 *   - handler: callback khi click ra ngoài
 *   - options.bubble: nếu true → luôn gọi handler (giống modifier .bubble)
 *
 * Cách dùng:
 *
 *   function MyDropdown() {
 *     const ref = useRef(null);
 *     useClickOutside(ref, () => setOpen(false));
 *     return <div ref={ref}>...</div>;
 *   }
 * -----------------------------------------------------------
 */

import { useEffect } from 'react';

export default function useClickOutside(ref, handler, options = {}) {
    const { bubble = false } = options;

    useEffect(() => {
        if (typeof handler !== 'function') {
            console.warn(
                '[useClickOutside] handler is not a function — bỏ qua.'
            );
            return;
        }

        const listener = (e) => {
            const el = ref.current;
            if (!el) return;

            // bubble = true: luôn chạy handler (giống .bubble modifier)
            // bubble = false: chỉ chạy khi click NGOÀI element
            if (bubble || (!el.contains(e.target) && el !== e.target)) {
                handler(e);
            }
        };

        document.addEventListener('click', listener);
        return () => {
            document.removeEventListener('click', listener);
        };
    }, [ref, handler, bubble]);
}
