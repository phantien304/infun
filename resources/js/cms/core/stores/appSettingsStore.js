/**
 * appSettingsStore.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2:
 *
 *   import {mapGetters} from 'vuex'
 *   computed: {
 *     ...mapGetters(['appSettings']),
 *     listLanguages() { ... },
 *     languageDefault() { ... }
 *   }
 *
 * → React + Zustand:
 *   - state: appSettings
 *   - selectors: listLanguages, languageDefault
 *
 * Zustand là state-management cho React, đơn giản hơn Redux/Vuex.
 * Cách hoạt động: 1 file = 1 store. Component subscribe bằng hook.
 *
 * Cài đặt:  npm i zustand
 *
 * Cách dùng trong component:
 *
 *   import { useAppSettings, useListLanguages } from '@/stores/appSettingsStore';
 *
 *   function MyComp() {
 *     const appSettings = useAppSettings();
 *     const listLanguages = useListLanguages();
 *     ...
 *   }
 * -----------------------------------------------------------
 */

import { create } from 'zustand';

// Lấy appSettings từ window khi load (đồng nhất với bản Vue 2).
const initialAppSettings =
    (typeof window !== 'undefined' && window.appSettings) || {};

const useAppSettingsStore = create((set) => ({
    appSettings: initialAppSettings,

    /**
     * Setter để cập nhật appSettings sau khi gọi API lấy config (nếu có).
     */
    setAppSettings: (next) =>
        set((state) => ({ appSettings: { ...state.appSettings, ...next } })),
}));

// -------- Selector hooks (giống "computed" của Vue) --------

/** Trả nguyên appSettings object */
export const useAppSettings = () =>
    useAppSettingsStore((s) => s.appSettings);

/** Danh sách ngôn ngữ chưa bị xoá mềm */
export const useListLanguages = () =>
    useAppSettingsStore((s) =>
        (s.appSettings.languages || []).filter((i) => i.deleted_at === null)
    );

/** Ngôn ngữ mặc định */
export const useLanguageDefault = () =>
    useAppSettingsStore((s) => s.appSettings.languageDefault);

/**
 * rowNum trong Vue 2 là computed phụ thuộc this.objSearch.
 * Ở React, objSearch là local state của component, nên hàm này
 * nhận objSearch làm tham số (KHÔNG đặt trong store nữa).
 */
export function calcRowNum(objSearch) {
    if (!objSearch) return 0;
    return (objSearch.pageIndex - 1) * objSearch.pageSize;
}

export default useAppSettingsStore;
