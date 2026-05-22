/**
 * alert.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 (element-ui MessageBox) sang Ant Design (Modal):
 *
 *   Vue 2                | React (Ant Design)
 *   ---------------------+--------------------
 *   this.$alert(msg)     | alert(msg)
 *   this.$error(msg)     | error(msg)
 *   this.$success(msg)   | success(msg)
 *   this.$confirm(msg)   | confirm(msg)
 *
 * Đều trả về Promise giống bản cũ — vẫn xài được:
 *   confirm('Xoá?').then(() => doDelete()).catch(() => {});
 *
 * Cài đặt:
 *   npm i antd
 *
 * Import 1 lần ở App.jsx:
 *   import 'antd/dist/reset.css';
 * -----------------------------------------------------------
 */

import { Modal } from 'antd';

/**
 * Wrap Modal API của antd để trả về Promise giống MessageBox.
 * @param {Function} fn - Modal.info / Modal.error / Modal.success
 * @param {string} msg
 * @param {string} [title]
 * @returns {Promise}
 */
function _showAlert(fn, msg, title) {
    return new Promise((resolve) => {
        fn({
            title: title,
            content: msg,
            okText: 'Đồng ý',
            onOk: () => resolve(),
        });
    });
}

export function alert(msg, title) {
    return _showAlert(Modal.info, msg, title || 'Thông báo');
}

export function error(msg, title) {
    return _showAlert(Modal.error, msg, title || 'Lỗi');
}

export function success(msg, title) {
    return _showAlert(Modal.success, msg, title || 'Thành công');
}

/**
 * Confirm dialog: resolve khi user bấm Đồng ý, reject khi bấm Hủy
 * (giống behavior của Vue 2 element-ui).
 */
export function confirm(msg, title) {
    return new Promise((resolve, reject) => {
        Modal.confirm({
            title: title || 'Xác nhận',
            content: msg,
            okText: 'Đồng ý',
            cancelText: 'Hủy',
            onOk: () => resolve(),
            onCancel: () => reject(new Error('cancelled')),
        });
    });
}

// Default export tiện cho ai muốn import 1 cục:
//   import alertService from '@/services/alert';
//   alertService.confirm(...);
export default { alert, error, success, confirm };
