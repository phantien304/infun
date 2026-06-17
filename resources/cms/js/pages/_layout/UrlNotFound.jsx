/**
 * UrlNotFound.jsx — convert từ url-not-found.vue
 * -----------------------------------------------------------
 * Trang 404. Markup giữ nguyên.
 * -----------------------------------------------------------
 */

import React from 'react';

const cardBodyStyle = {
    fontSize: 15,
    display: 'flex',
    justifyContent: 'center',
};

export default function UrlNotFound() {
    return (
        <div>
            <div className="container mt-5">
                <div className="row">
                    <div className="card-body" style={cardBodyStyle}>
                        <img src="/cms/images/assets/404.jpg" alt="404" />
                    </div>
                </div>
            </div>
        </div>
    );
}
