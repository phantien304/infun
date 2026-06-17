/**
 * userStore.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2:
 *
 *   computed: {
 *     ...mapGetters(['currentUser', 'currentLanguage'])
 *   }
 *
 * → Zustand store cho currentUser + currentLanguage.
 *
 * Cách dùng:
 *   const currentUser = useCurrentUser();
 *   const currentLanguage = useCurrentLanguage();
 *
 *   // Set sau khi login:
 *   useUserStore.getState().setCurrentUser(username);
 * -----------------------------------------------------------
 */

import { create } from 'zustand';
import CONSTANTS from '../utils/constants';

// Đọc lại currentUser từ localStorage để khi F5 vẫn nhận diện user đăng nhập.
// Khớp với pattern Vue 2: app.vue có v-if="currentUser" — currentUser phải
// có sẵn ngay từ render đầu tiên (không đợi initSystem).
const initialUser =
    (typeof localStorage !== 'undefined' &&
        localStorage.getItem(CONSTANTS.USERNAME)) ||
    null;

const useUserStore = create((set) => ({
    currentUser: initialUser,
    currentLanguage:
        (typeof localStorage !== 'undefined' &&
            localStorage.getItem(CONSTANTS.LANG)) ||
        null,

    setCurrentUser: (user) => {
        // Đồng bộ vào localStorage để RequireAuth + reload trang thấy được
        if (typeof localStorage !== 'undefined') {
            if (user) localStorage.setItem(CONSTANTS.USERNAME, user);
            else localStorage.removeItem(CONSTANTS.USERNAME);
        }
        set({ currentUser: user });
    },

    setCurrentLanguage: (lang) => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(CONSTANTS.LANG, lang);
        }
        set({ currentLanguage: lang });
    },

    /** Logout — clear toàn bộ user state + localStorage liên quan */
    logout: () => {
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem(CONSTANTS.USERNAME);
            localStorage.removeItem(CONSTANTS.ACCESS_TOKEN);
            localStorage.removeItem(CONSTANTS.PERMISSIONS);
        }
        set({ currentUser: null });
    },
}));

// -------- Selector hooks --------
export const useCurrentUser = () => useUserStore((s) => s.currentUser);
export const useCurrentLanguage = () => useUserStore((s) => s.currentLanguage);

export default useUserStore;
