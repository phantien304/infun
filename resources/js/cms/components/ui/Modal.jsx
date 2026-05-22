/**
 * Modal.jsx — wrapper antd Modal, giữ API giống modal.vue cũ
 * -----------------------------------------------------------
 * Bản Vue 2 tự viết modal Bootstrap (~80 dòng). Em thay bằng antd Modal
 * — gọn hơn, có sẵn animation, focus trap, escape key, click outside.
 *
 * Mapping API cũ → mới (KHÔNG đổi tên prop chính):
 *   - title              → giữ
 *   - width              → giữ
 *   - footer             → giữ (boolean hoặc ReactNode)
 *   - size: 'large'|'huge' → mapped sang width (lg=900, huge='90%')
 *   - noCloseBackground  → maskClosable (đảo)
 *   - <slot name="body"> → children
 *   - <slot name="footer">→ footer prop
 *   - @close emit        → onClose prop
 * -----------------------------------------------------------
 */

import React from 'react';
import { Modal as AntModal } from 'antd';
import useTranslation from '@/core/hooks/useTranslation';

const SIZE_TO_WIDTH = {
    large: 900,
    huge: '90%',
};

export default function Modal({
    open = true,
    title = 'Thông báo',
    width = 600,
    size = '',
    noCloseBackground = false,
    footer = true,
    onClose,
    children,
    footerSlot, // custom footer content nếu cần
    outside, // slot="outside" cũ — render bên ngoài modal
}) {
    const t = useTranslation();

    const finalWidth = size ? SIZE_TO_WIDTH[size] || width : width;

    // footer: nếu false → ẩn; nếu true → mặc định nút Close; nếu là node → render node
    let footerProp = null;
    if (footer === false) {
        footerProp = null;
    } else if (footerSlot) {
        footerProp = footerSlot;
    } else {
        footerProp = (
            <button
                type="button"
                className="btn btn-secondary"
                onClick={onClose}
            >
                {t('Close')}
            </button>
        );
    }

    return (
        <>
            <AntModal
                open={open}
                title={title}
                width={finalWidth}
                maskClosable={!noCloseBackground}
                onCancel={onClose}
                footer={footerProp}
                centered
            >
                {children}
            </AntModal>
            {outside}
        </>
    );
}
