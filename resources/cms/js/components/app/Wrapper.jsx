/**
 * Wrapper.jsx — convert từ wrapper.vue
 * -----------------------------------------------------------
 * Page wrapper: header (title + sapo) + card body chứa children.
 *
 * Mapping:
 *   - <slot/>           → {children}
 *   - props.title       → props.title
 *   - props.sapo        → props.sapo
 *   - v-if="title"      → {title && (...)}
 * -----------------------------------------------------------
 */

import React from 'react';

export default function Wrapper({
    title = 'Tiêu đề',
    sapo = 'Mô tả',
    children,
}) {
    return (
        <div>
            {title && (
                <div className="row">
                    <div className="col-sm-12">
                        <div className="page-title-box">
                            <h4 className="page-title">{title}</h4>
                            {sapo && (
                                <ol className="breadcrumb">
                                    <li className="breadcrumb-item active">{sapo}</li>
                                </ol>
                            )}
                        </div>
                    </div>
                </div>
            )}
            <div className="card m-b-20">
                <div className="card-body">{children}</div>
            </div>
        </div>
    );
}
