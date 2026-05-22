/**
 * TinyMce.jsx — convert từ tiny-mce.vue
 * -----------------------------------------------------------
 * TinyMCE rich-text editor. Bản Vue 2 dùng @tinymce/tinymce-vue.
 * React dùng package chính thức @tinymce/tinymce-react.
 *
 * Cài đặt: npm i @tinymce/tinymce-react tinymce
 *
 * Mapping:
 *  - props.value, height, relative_urls       → giữ
 *  - data().plugins, toolbar                  → giữ
 *  - images_upload_handler (XHR thủ công)     → giữ logic
 *  - @input emit                              → onChange(content) prop
 *  - this.appSettings.storageDomain, urlCms, csrfToken → window.appSettings
 *
 * Lưu ý: TinyMCE 6+ tự load skins/plugins. Bản cũ import từng plugin
 * thủ công — em giữ lại pattern đó cho an toàn.
 * -----------------------------------------------------------
 */

import React from 'react';
import { Editor } from '@tinymce/tinymce-react';

// Self-hosted: import core + theme + plugin (giống bản cũ)
import 'tinymce/tinymce';
import 'tinymce/icons/default';
import 'tinymce/themes/silver';
import 'tinymce/plugins/advlist';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/link';
import 'tinymce/plugins/image';
import 'tinymce/plugins/media';
import 'tinymce/plugins/charmap';
import 'tinymce/plugins/preview';
import 'tinymce/plugins/hr';
import 'tinymce/plugins/searchreplace';
import 'tinymce/plugins/wordcount';
import 'tinymce/plugins/code';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/table';
import 'tinymce/plugins/insertdatetime';

const PLUGINS = [
    'wordcount', 'link', 'hr', 'autolink', 'searchreplace',
    'insertdatetime',
    'image', 'table', 'lists', 'advlist',
    'preview', 'code', 'fullscreen', 'charmap', 'media',
];

const TOOLBAR =
    'undo redo | styleselect | bold italic underline strikethrough | ' +
    'alignleft aligncenter alignright alignjustify | forecolor backcolor | ' +
    'numlist bullist | outdent indent | link image | ' +
    'removeformat fullscreen code wordcount';

function imagesUploadHandler(blobInfo, progress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.withCredentials = false;
        xhr.open(
            'POST',
            window.appSettings.urlCms + '/file/save?json=true&type_upload=image'
        );
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', window.appSettings.csrfToken);
        xhr.onload = () => {
            if (xhr.status !== 200) {
                reject('HTTP Error: ' + xhr.status);
                return;
            }
            try {
                const json = JSON.parse(xhr.responseText);
                if (!json || typeof json.data !== 'string') {
                    reject('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                resolve(json.data);
            } catch (e) {
                reject('Invalid JSON: ' + xhr.responseText);
            }
        };
        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        xhr.send(formData);
    });
}

export default function TinyMce({
    value = '',
    onChange,
    height = 300,
    relative_urls = true,
}) {
    return (
        <Editor
            value={value}
            onEditorChange={(content) => onChange?.(content)}
            init={{
                plugins: PLUGINS,
                toolbar: TOOLBAR,
                height,
                relative_urls,
                remove_script_host: false,
                automatic_uploads: true,
                images_upload_url:
                    (window.appSettings?.urlCms || '') +
                    '/file/save?json=true&type_upload=image',
                image_prepend_url:
                    (window.appSettings?.storageDomain || '') + '/',
                document_base_url: window.appSettings?.storageDomain || '',
                images_upload_handler: imagesUploadHandler,
                // Skin URL cho self-host (cần copy thư mục skins vào public)
                skin_url: '/cms/skins/ui/oxide',
                content_css: '/cms/skins/content/default/content.css',
            }}
        />
    );
}
