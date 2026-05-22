/**
 * useTranslation.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 mixin:
 *
 *   methods: {
 *     $i(key) {
 *       if (this.currentLanguage) {
 *         if (this.appSettings && this.appSettings.languageTexts) {
 *           ...
 *         }
 *       }
 *       return key;
 *     }
 *   }
 *
 * → React hook useTranslation() trả về function t(key) (đổi tên từ $i
 *   cho hợp convention React — $i không dùng được làm tên hàm JS).
 *
 * Cách dùng:
 *   const t = useTranslation();
 *   <button>{t('Save')}</button>
 *
 * Giữ tương thích bản cũ: cũng export `i(key, appSettings, currentLanguage)`
 * dạng pure function, dùng được ngoài component (vd. trong service).
 * -----------------------------------------------------------
 */

import { useCallback } from 'react';
import { useAppSettings } from '../stores/appSettingsStore';
import { useCurrentLanguage } from '../stores/userStore';

/**
 * Pure version — dùng được ở mọi nơi (ngoài component).
 */
export function i(key, appSettings, currentLanguage) {
    if (!currentLanguage) return key;
    if (!appSettings || !appSettings.languageTexts) return key;

    // languageTexts có dạng object { [key]: text }. Tìm theo key.
    const texts = appSettings.languageTexts;
    if (Object.prototype.hasOwnProperty.call(texts, key)) {
        return texts[key];
    }
    return key;
}

/**
 * Hook version — auto inject appSettings + currentLanguage từ store.
 */
export default function useTranslation() {
    const appSettings = useAppSettings();
    const currentLanguage = useCurrentLanguage();

    return useCallback(
        (key) => i(key, appSettings, currentLanguage),
        [appSettings, currentLanguage]
    );
}
