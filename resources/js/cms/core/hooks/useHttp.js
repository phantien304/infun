/**
 * useHttp.js
 * -----------------------------------------------------------
 * Hook wrapper cho request() — tự động bind navigate từ react-router-dom,
 * để xử lý options.data.is_push giống bản Vue 2.
 *
 * Cách dùng:
 *   const http = useHttp();
 *   http({ data: { url: 'product/list', is_push: true } });
 * -----------------------------------------------------------
 */

import { useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import request from '../services/http';

export default function useHttp() {
    const navigate = useNavigate();

    return useCallback(
        (options) => request(options, navigate),
        [navigate]
    );
}
