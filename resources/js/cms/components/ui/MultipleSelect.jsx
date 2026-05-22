/**
 * MultipleSelect.jsx — convert từ multiple-select.vue
 * -----------------------------------------------------------
 * Component này hiển thị:
 *  - Dropdown suggestions (filter theo keyword)
 *  - List selected items với nút xoá
 *
 * Mapping:
 *  - data().selections        → useState
 *  - computed.matches         → useMemo (filter theo keyword)
 *  - computed.openSuggestion  → tính trực tiếp trong render
 *  - $emit('change', list)    → onChange prop
 *
 * Lưu ý: bản Vue 2 KHÔNG sync selections từ prop khi prop đổi.
 * Em giữ behavior đó, nhưng nếu muốn fully-controlled thì có thể
 * truyền `selection` luôn dùng làm value (bỏ useState nội bộ).
 * -----------------------------------------------------------
 */

import React, { useMemo, useState } from 'react';

export default function MultipleSelect({
    suggestions = [],
    selection = [],
    keyword = '',
    opened = false,
    label = 'name',
    onChange,
}) {
    const [selections, setSelections] = useState(selection || []);

    const matches = useMemo(() => {
        return suggestions.filter((item) => {
            if (item.title) return item.title.indexOf(keyword) >= 0;
            if (item[label]) return item[label].indexOf(keyword) >= 0;
            return false;
        });
    }, [suggestions, keyword, label]);

    const openSuggestion = suggestions.length !== 0 && opened === true;

    function suggestionClick(index) {
        const picked = matches[index];
        const exists = selections.some(
            (s) => parseInt(s.id, 10) === parseInt(picked.id, 10)
        );
        if (exists) return;

        const objData = { id: picked.id };
        if (picked.title) objData.title = picked.title;
        else objData[label] = picked[label];

        const next = [...selections, objData];
        setSelections(next);
        onChange?.(next, false);
    }

    function deleteSelection(index) {
        const next = selections.slice();
        next.splice(index, 1);
        setSelections(next);
        onChange?.(next, false);
    }

    return (
        <div>
            <div className="multiple-select" style={{ position: 'relative' }}>
                <ul
                    className={'dropdown-menu' + (openSuggestion ? ' open' : '')}
                    style={{ width: '100%' }}
                >
                    {matches.map((s, index) => (
                        <li key={s.id || index} onClick={() => suggestionClick(index)}>
                            <span>{s.title || s[label]}</span>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="product-link scroll">
                <ul>
                    {selections.map((item, index) => (
                        <li key={item.id || index}>
                            <span>{item.title || item[label]}</span>
                            <span
                                className="float-right"
                                onClick={() => deleteSelection(index)}
                            >
                                <i className="mdi mdi-close-circle" />
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
