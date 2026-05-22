/**
 * constants.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2: gần như giữ nguyên (đây là static config).
 * Cách dùng trong React component:
 *
 *   import CONSTANTS from '@/utils/constants';
 *   localStorage.getItem(CONSTANTS.ACCESS_TOKEN);
 * -----------------------------------------------------------
 */

let area = 'CMS';
let suffix = '';

// window.appSettings vẫn được inject sẵn (qua <script> trong index.html)
// giống bản Vue 2. Không thay đổi cơ chế này.
if (typeof window !== 'undefined' && window.appSettings) {
    area = window.appSettings.area;
    suffix = '_' + area;
}

const CONSTANTS = {
    ACCESS_TOKEN: 'AUTH_TOKEN' + suffix,
    LANG: 'LANG' + suffix,
    USERNAME: 'USERNAME' + suffix,
    ACCOUNT: 'ACCOUNT' + suffix,
    PERMISSIONS: 'PERMISSIONS' + suffix,
    USER_PERMISSIONS: 'USER_PERMISSIONS' + suffix,
    CURRENT_VERSION: 'CURRENT_VERSION' + suffix,
    MENU_MODE: 'MENU_MODE' + suffix,
    ACCESS_PERMISSION: 'ACCESS_PERMISSION' + suffix,
    ICON_UPLOAD: '/cms/images/assets/file-upload.jpg',
    NO_IMG: '/cms/images/assets/no-img.png',
    POSITION_MENU: ['top', 'bottom'],
    VARIATION_ONE: 1,
    VARIATION_TWO: 2,
};

export default CONSTANTS;
