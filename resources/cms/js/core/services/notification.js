/**
 * notification.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 (element-ui Notification) sang Ant Design:
 *
 *   Vue 2                  | React (Ant Design)
 *   -----------------------+--------------------
 *   this.$notify(msg)      | notify(msg)
 *   this.$notifySuccess()  | notifySuccess(msg)
 *   this.$notifyError()    | notifyError(msg)
 *
 * Không cần Promise (notification chỉ là toast).
 * -----------------------------------------------------------
 */

import React from 'react';
import { notification } from 'antd';

export function notify(message, title, type = 'info') {
    const fn = notification[type] || notification.info;
    fn({
        message: title || 'Thông báo',
        description: message,
    });
}

export function notifySuccess(message, title) {
    notification.success({
        message: title || 'Thành công',
        description: message,
    });
}

/**
 * notifyError — element-ui hỗ trợ dangerouslyUseHTMLString.
 * antd nhận ReactNode trong description; nếu thấy string có HTML
 * thì wrap bằng React.createElement('span', { dangerouslySetInnerHTML }).
 */
export function notifyError(message, title) {
    let description = message;
    if (typeof message === 'string' && /<[a-z][\s\S]*>/i.test(message)) {
        description = React.createElement('span', {
            dangerouslySetInnerHTML: { __html: message },
        });
    }

    notification.error({
        message: title || 'Lỗi',
        description,
    });
}

export default { notify, notifySuccess, notifyError };
