/**
 * Avatar.jsx — convert từ avatar.vue
 * -----------------------------------------------------------
 * Render avatar: nếu có `src` thì hiện image; không thì hiện initials
 * với màu nền random (deterministic theo độ dài username).
 *
 * Mapping:
 *   - computed.style       → useMemo
 *   - computed.userInitial → useMemo
 *   - this.appSettings     → useAppSettings()
 *   - $emit('avatar-initials')      → prop onInitials (optional)
 *
 * Lưu ý: bản Vue 2 có hành vi này.username = "H V" khi trống — đây
 * là side-effect trong computed (anti-pattern). React không cho mutate
 * props, nên em thay bằng default value an toàn.
 * -----------------------------------------------------------
 */

import React, { useEffect, useMemo } from 'react';
import { useAppSettings } from '@/core/stores/appSettingsStore';

const DEFAULT_BG_COLORS = [
    '#F44336', '#FF4081', '#9C27B0', '#673AB7',
    '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4', '#009688',
    '#4CAF50', '#8BC34A', '#CDDC39', '#FFC107',
    '#FF9800', '#FF5722', '#795548', '#9E9E9E', '#607D8B',
];

function initial(username) {
    const parts = username.split(/[ -]/);
    let initials = '';
    for (const p of parts) initials += p.charAt(0);
    if (initials.length > 3 && /[A-Z]/.test(initials)) {
        initials = initials.replace(/[a-z]+/g, '');
    }
    return initials.substr(0, 3).toUpperCase();
}

function randomBackgroundColor(seed, colors) {
    return colors[seed % colors.length];
}

function lightenColor(hex, amt) {
    let usePound = false;
    if (hex[0] === '#') {
        hex = hex.slice(1);
        usePound = true;
    }
    const num = parseInt(hex, 16);
    let r = (num >> 16) + amt;
    if (r > 255) r = 255;
    else if (r < 0) r = 0;
    let b = ((num >> 8) & 0x00ff) + amt;
    if (b > 255) b = 255;
    else if (b < 0) b = 0;
    let g = (num & 0x0000ff) + amt;
    if (g > 255) g = 255;
    else if (g < 0) g = 0;
    return (usePound ? '#' : '') + (g | (b << 8) | (r << 16)).toString(16);
}

export default function Avatar({
    username,
    initials: initialsProp,
    backgroundColor,
    color,
    customStyle,
    size = 50,
    src,
    rounded = true,
    lighten = 90,
    onInitials,
}) {
    const appSettings = useAppSettings();
    const safeUsername = username && username !== '' ? username : 'H V';

    const userInitial = useMemo(
        () => initialsProp || initial(safeUsername),
        [initialsProp, safeUsername]
    );

    useEffect(() => {
        if (onInitials) onInitials(safeUsername, userInitial);
    }, [safeUsername, userInitial, onInitials]);

    const style = useMemo(() => {
        const isImage = !!src;
        const base = {
            width: size + 'px',
            height: size + 'px',
            borderRadius: rounded ? '50%' : 0,
            textAlign: 'center',
            verticalAlign: 'middle',
        };

        if (isImage) {
            let source = src;
            if (source.lastIndexOf('http') === -1) {
                source = (appSettings.storageDomain || '') + '/' + source;
                const domain = appSettings.storageDomain || '';
                if (
                    (source.lastIndexOf(domain) !== -1 ||
                        source.lastIndexOf('thaomoc.com') !== -1) &&
                    source.lastIndexOf('?') === -1
                ) {
                    source += `?w=${size}&h=${size}&mode=crop`;
                }
            }
            return {
                ...base,
                background: `url('${source}') no-repeat scroll 0% 0% / ${size}px ${size}px content-box border-box transparent`,
            };
        }

        const bg = backgroundColor || randomBackgroundColor(safeUsername.length, DEFAULT_BG_COLORS);
        const fg = color || lightenColor(bg, lighten);

        return {
            ...base,
            backgroundColor: bg,
            font: `${Math.floor(size / 3)}px/100px Helvetica, Arial, sans-serif`,
            fontWeight: 'normal',
            color: fg,
            lineHeight: `${size + Math.floor(size / 20)}px`,
        };
    }, [src, size, rounded, backgroundColor, color, lighten, safeUsername, appSettings]);

    return (
        <div
            className="dekiru-avatar-wrapper"
            style={{ ...style, ...(customStyle || {}) }}
            title={safeUsername}
        >
            {!src && <span>{userInitial}</span>}
        </div>
    );
}
