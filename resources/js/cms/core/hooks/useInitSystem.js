/**
 * useInitSystem.js
 * -----------------------------------------------------------
 * Tương đương `initSystem` + `updateAppSettings` (Vuex actions cũ) +
 * lifecycle `created()` của app-wrapper.vue.
 *
 * Việc hook làm:
 *  1. Gọi API `/system/init` (đổi URL nếu backend khác)
 *  2. Gộp data trả về vào appSettings:
 *       - appSettings.config           = system.config
 *       - appSettings.languageDefault  = system.config.config_language
 *       - appSettings.languageTexts    = system.languageTexts
 *       - appSettings.languages        = system.languages
 *  3. Hiển thị loading global trong khi đang fetch
 *  4. Trả về { done, error } để component cha quyết định render gì
 *
 * Lưu ý: Code Vue 2 dùng `this.initSystem()` từ Vuex action. Ở React
 * em đặt logic trực tiếp trong hook để gọn — KHÔNG cần lưu `system`
 * vào store vì sau khi merge xong vào appSettings là dùng được hết.
 * -----------------------------------------------------------
 */

import { useEffect, useState } from 'react';
import useHttp from './useHttp';
import useLoading from './useLoading';
import useAppSettingsStore from '../stores/appSettingsStore';
import { alert as alertModal } from '../services/alert';

export default function useInitSystem() {
    const http = useHttp();
    const loading = useLoading();
    const setAppSettings = useAppSettingsStore((s) => s.setAppSettings);

    const [done, setDone] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;
        const inst = loading.open();

        http({ data: { url: '/system/init', method: 'get' } })
            .then((res) => {
                if (cancelled) return;
                const system = res?.data || {};
                setAppSettings({
                    config: system.config || {},
                    languageDefault: system.config?.config_language,
                    languageTexts: system.languageTexts || {},
                    languages: system.languages || [],
                });
                setDone(true);
            })
            .catch((err) => {
                if (cancelled) return;
                setError(err);
                alertModal(err?.message || 'Init system failed');
            })
            .finally(() => {
                inst.close();
            });

        return () => {
            cancelled = true;
            inst.close();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return { done, error };
}
