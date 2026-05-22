/**
 * Photo.jsx — convert từ photo.vue
 * -----------------------------------------------------------
 * Upload 1 ảnh (drag-drop). Bản Vue 2 dùng el-upload, file React
 * dùng antd Upload (Dragger).
 *
 * Mapping:
 *  - props.src, width, height, index, showDelete → giữ
 *  - computed.displayImage → useMemo (logic build URL preview giữ nguyên)
 *  - methods.getFiles / beforeUpload / deleteImage → giữ logic
 *  - $emit('change', url, index) → onChange prop
 *  - this.$confirm/$error/$success → confirm/error/success từ alert.js
 *  - this.$http → useHttp()
 *  - this.$route.path → useLocation()
 * -----------------------------------------------------------
 */

import React, { useMemo, useState } from 'react';
import { Upload } from 'antd';
import { useLocation } from 'react-router-dom';
import CONSTANTS from '@/core/utils/constants';
import { confirm, error, success } from '@/core/services/alert';
import useTranslation from '@/core/hooks/useTranslation';
import useHttp from '@/core/hooks/useHttp';
import { useAppSettings } from '@/core/stores/appSettingsStore';

const { Dragger } = Upload;

export default function Photo({
    src = '',
    width = '100%',
    height = '500px',
    index = null,
    showDelete = false,
    onChange,
}) {
    const t = useTranslation();
    const http = useHttp();
    const appSettings = useAppSettings();
    const location = useLocation();

    const [srcUpload, setSrcUpload] = useState(src);

    const hasImage = useMemo(() => {
        if (!src) return false;
        if (src.indexOf(CONSTANTS.ICON_UPLOAD) !== -1) return false;
        return true;
    }, [src]);

    const displayImage = useMemo(() => {
        let _src = '';
        if (srcUpload) {
            _src = srcUpload;
        } else {
            return (appSettings.storageDomain || '') + CONSTANTS.ICON_UPLOAD;
        }
        if (
            _src.lastIndexOf('http') === -1 &&
            _src.lastIndexOf('data:image/') === -1
        ) {
            _src = (appSettings.storageDomain || '') + '/' + _src;
        }
        const domain = appSettings.storageDomain || '';
        if (
            (_src.lastIndexOf(domain) !== -1 ||
                _src.lastIndexOf('thaomoc.com') !== -1) &&
            _src.lastIndexOf('?') === -1
        ) {
            const w = parseInt(width, 10);
            const h = parseInt(height, 10);
            if (h === 0 && w > 0) _src += `?w=${w}&mode=crop`;
            else if (w === 0 && h > 0) _src += `?h=${h}&mode=crop`;
            else if (w > 0 && h > 0) _src += `?w=${w}&h=${h}&mode=crop`;
        }
        return _src;
    }, [srcUpload, appSettings, width, height]);

    function handleBeforeUpload(file) {
        const isLt2M = file.size / 1024 / 1024 < 2;
        if (!isLt2M) error(t('ImageMoreThan2MB'));
        return isLt2M || Upload.LIST_IGNORE;
    }

    function handleChange(info) {
        const { file } = info;
        if (file.status === 'error') {
            error(t('UploadImageFail'));
            return;
        }
        if (file.response && file.response.data) {
            setSrcUpload(file.response.data);
            onChange?.(file.response.data, index);
        }
    }

    function handleDelete() {
        confirm(t('DoYouWantToDeleteImage')).then(() => {
            http({
                data: {
                    url: '/file/del',
                    image: srcUpload,
                    type_delete: true,
                },
            })
                .then((res) => {
                    if (res.success) {
                        setSrcUpload('');
                        onChange?.('', index);
                        return success(t('DeleteImageSuccess'));
                    }
                    return error(t('DeleteImageFailed'));
                })
                .catch(() => error(t('DeleteImageFailed')));
        });
    }

    const csrfToken =
        typeof document !== 'undefined'
            ? document.head.querySelector('meta[name="csrf-token"]')?.content
            : '';

    return (
        <div
            className="upload-drag-drop-image"
            style={{ position: 'relative' }}
        >
            <Dragger
                action="/vcms/file/save?json=true&type_upload=image"
                multiple={false}
                showUploadList={false}
                headers={{
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Source-Upload': location.pathname.split('/')[1] || '',
                }}
                beforeUpload={handleBeforeUpload}
                onChange={handleChange}
            >
                <img
                    src={displayImage}
                    alt="upload"
                    className="photo-preview"
                    style={{ width, height }}
                />
            </Dragger>

            {showDelete && hasImage && (
                <i
                    className="mdi mdi-close-circle"
                    onClick={handleDelete}
                    style={{
                        position: 'absolute',
                        right: 0,
                        top: 0,
                        fontSize: 15,
                        background: 'rgb(207, 207, 207)',
                        padding: 10,
                        zIndex: 99999,
                        cursor: 'pointer',
                    }}
                />
            )}
        </div>
    );
}
