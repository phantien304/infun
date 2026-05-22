/**
 * PermissionDenied.jsx — convert từ permission-denied.vue
 * -----------------------------------------------------------
 * Trang 403. Nội dung và markup giữ nguyên.
 * <style scoped> chuyển thành inline style + CSS class (đơn giản).
 * -----------------------------------------------------------
 */

import React from 'react';

const styles = {
    cardHeader: {
        width: '100%',
        display: 'flex',
        justifyContent: 'center',
        background: 'none',
        borderBottom: 'none',
    },
    cardBody: {
        fontSize: 15,
        display: 'flex',
        justifyContent: 'center',
    },
};

export default function PermissionDenied() {
    return (
        <div>
            <div className="container mt-5">
                <div className="row">
                    <div className="card-header" style={styles.cardHeader}>
                        <h6>
                            Bạn không có quyền truy cập trang này. Vui lòng liên hệ
                            quản trị viên để biết thêm chi tiết
                        </h6>
                    </div>
                </div>
                <div className="row">
                    <div className="card-body" style={styles.cardBody}>
                        <img src="/cms/images/assets/403.png" alt="403" />
                    </div>
                </div>
            </div>
        </div>
    );
}
