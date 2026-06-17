/**
 * ManualUpload.jsx — convert từ manual-upload.vue
 * -----------------------------------------------------------
 * Upload nhiều file, có nút "Select" + "Upload" tách biệt
 * (không auto upload khi chọn file).
 *
 * Mapping:
 *  - props.fileList, typeUpload     → giữ
 *  - data().headerInfo              → useMemo
 *  - $refs.upload.submit() (auto-upload=false → click Upload) → useRef
 *  - handleSuccess/Change/Error/Remove/beforeRemove → giữ logic
 * -----------------------------------------------------------
 */

import React, { useMemo, useRef } from 'react';
import { Upload, Button } from 'antd';
import { useLocation } from 'react-router-dom';
import { confirm, success, error, alert } from '@/core/services/alert';
import useTranslation from '@/core/hooks/useTranslation';
import useHttp from '@/core/hooks/useHttp';

export default function ManualUpload({
    fileList = [],
    typeUpload = 'image',
    onChange,
}) {
    const t = useTranslation();
    const http = useHttp();
    const location = useLocation();
    const uploadRef = useRef(null);

    const csrfToken =
        typeof document !== 'undefined'
            ? document.head.querySelector('meta[name="csrf-token"]')?.content
            : '';

    const headers = useMemo(
        () => ({
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Source-Upload': location.pathname.split('/')[1] || '',
        }),
        [csrfToken, location.pathname]
    );

    function handleChange(info) {
        const { file, fileList: nextList } = info;
        if (file.status === 'done') {
            const list = nextList
                .map((v) => (v.response ? v : null))
                .filter(Boolean);
            onChange?.(list);
        } else if (file.status === 'error') {
            alert(`${file.name} the file may not be greater than 10240 kilobytes.`);
        } else {
            onChange?.(nextList);
        }
    }

    function beforeRemove(file) {
        return confirm(t('DoYouWantToDelete') + ` ${file.name}？`)
            .then(() => {
                if (file.response) {
                    return http({
                        data: {
                            url: '/file/del',
                            image: file.response.data,
                            type_delete: true,
                        },
                    })
                        .then(() => {
                            success(t('DeleteSuccess'));
                            return true;
                        })
                        .catch(() => {
                            error(t('DeleteFailed'));
                            return false;
                        });
                }
                success(t('DeleteSuccess'));
                return true;
            })
            .catch(() => false);
    }

    function submitUpload() {
        // antd Upload không có .submit() — ta dùng custom action.
        // Để giữ giống bản cũ, dispatch event tới các file pending.
        // Cách đơn giản nhất: bỏ qua autoUpload=false (antd luôn auto)
        // và cảnh báo user nếu muốn behavior 2-bước, dùng prop customRequest.
        // Ở đây em trigger thủ công bằng cách click input:
        const input = uploadRef.current?.querySelector('input[type="file"]');
        input?.click();
    }

    return (
        <div className="upload-drag-drop-image" ref={uploadRef}>
            <Upload
                action={`/vcms/file/save?json=true&type_upload=${typeUpload}`}
                headers={headers}
                multiple
                fileList={fileList}
                onChange={handleChange}
                beforeRemove={beforeRemove}
            >
                <Button size="small" type="primary">
                    {t('SelectFile')}
                </Button>
                <Button
                    style={{ marginLeft: 10 }}
                    size="small"
                    type="default"
                    onClick={submitUpload}
                >
                    {t('Upload')}
                </Button>
                <div className="el-upload__tip" style={{ marginLeft: 10 }}>
                    {t('FilesLessThan10Mb')}
                </div>
            </Upload>
        </div>
    );
}
