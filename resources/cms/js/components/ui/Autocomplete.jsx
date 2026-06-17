/**
 * Autocomplete.jsx — convert từ autocomplete.vue
 * -----------------------------------------------------------
 * Remote select với debounce search. Bản cũ dùng el-select của Element UI.
 * React thay bằng antd Select với `showSearch` + `filterOption={false}`.
 *
 * Mapping:
 *  - props.value, defaultValue, method, keyword, code, label, placeholder,
 *    returnValue, allowCreate, params → giữ
 *  - watch.value                         → useEffect
 *  - data().objData (pageIndex, pageSize, deleted_at) → state
 *  - methods.getData / remoteMethod      → useCallback + setTimeout
 *  - $emit('change') + $emit('input')    → onChange prop
 *
 * Yêu cầu: method là function nhận {pageIndex, pageSize, deleted_at, [keyword]}
 *          và trả Promise<{data: [...]}> (giống bản cũ).
 * -----------------------------------------------------------
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Select } from 'antd';
import useTranslation from '@/core/hooks/useTranslation';
import { error as alertError } from '@/core/services/alert';

export default function Autocomplete({
    value,
    defaultValue,
    method,
    keyword = 'name',
    code = 'id',
    label = 'name',
    placeholder = '',
    returnValue = 'Object',
    allowCreate = false,
    params,
    onChange,
}) {
    const t = useTranslation();

    const [data, setData] = useState(value ?? null);
    const [list, setList] = useState(defaultValue ? [defaultValue] : []);
    const [loading, setLoading] = useState(false);

    const objDataRef = useRef({
        pageIndex: 1,
        pageSize: 10,
        deleted_at: 1,
        [keyword]: '',
    });

    useEffect(() => {
        setData(value ?? null);
    }, [value]);

    const fetchList = useCallback(() => {
        setLoading(true);
        if (params) Object.assign(objDataRef.current, params);
        method(objDataRef.current)
            .then((res) => setList(res.data || []))
            .catch((err) => alertError(t(err?.message || 'Error')))
            .finally(() => setLoading(false));
    }, [method, params, t]);

    const timerRef = useRef(null);

    function handleSearch(query) {
        if (!query) return;
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            objDataRef.current[keyword] = query;
            fetchList();
        }, 500);
    }

    function handleChange(val) {
        // antd Select trả về val theo `value` prop của Option
        let out = val;
        if (returnValue === 'Object') {
            out = list.find((item) => item[code] === val) || val;
        }
        setData(out);
        onChange?.(out);
    }

    return (
        <Select
            showSearch
            allowClear
            filterOption={false}
            placeholder={placeholder}
            loading={loading}
            value={
                data == null
                    ? undefined
                    : returnValue === 'Object'
                      ? data?.[code]
                      : data
            }
            onSearch={handleSearch}
            onFocus={fetchList}
            onChange={handleChange}
            mode={allowCreate ? 'tags' : undefined}
            style={{ width: '100%' }}
            options={list.map((v) => ({
                value: v[code],
                label: v[label],
                key: 'remote' + v[label] + v[code],
            }))}
        />
    );
}
