/**
 * useCrudActions.js
 * -----------------------------------------------------------
 * Mapping từ Vue 2 mixin `functionCommon.js`. Đây là phần phức tạp nhất
 * vì mixin Vue 2 truy cập this.* (objSearch, objForm, loader, errors, getData,
 * getDetail, $router, ...). Trong React không có `this`, ta dùng pattern:
 *
 *   const crud = useCrudActions({
 *     objSearch, setObjSearch,
 *     objForm,   setObjForm,
 *     setLoader,
 *     setErrors,
 *     getData,
 *     getDetail,
 *   });
 *
 *   crud.saveDataCms('/product', 'product.list', 'product.edit');
 *
 * Hook nhận về 1 "context object" và trả 7 hàm tương ứng mixin cũ:
 *   changeStatus, updateSelected, sort, pageChange, getDetail, getDataList, saveDataCms.
 *
 * Mỗi hàm dùng:
 *  - http()       → useHttp (auto navigate)
 *  - confirm()    → alert.confirm  (Modal.confirm của antd)
 *  - error()      → alert.error
 *  - success()    → alert.success
 *  - loading.open() → useLoading (Spin overlay)
 *  - t()          → useTranslation ($i cũ)
 *  - navigate()   → react-router-dom v6
 * -----------------------------------------------------------
 */

import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import useHttp from './useHttp';
import useLoading from './useLoading';
import useTranslation from './useTranslation';
import { confirm, error, success } from '../services/alert';

export default function useCrudActions(ctx) {
    const {
        objSearch,
        setObjSearch,
        objForm,
        setObjForm,
        setLoader,
        setErrors,
        getData, // function load list
        getDetail, // function load 1 item
    } = ctx;

    const http = useHttp();
    const loading = useLoading();
    const t = useTranslation();
    const navigate = useNavigate();

    return useMemo(() => {
        /**
         * Toggle xoá mềm / khôi phục.
         * Khác Vue 2: thay vì sửa trực tiếp objData.deleted_at (mutate),
         * ta clone object để giữ pattern immutable của React.
         */
        function changeStatus(objData, url) {
            const willDelete =
                objData.deleted_at == null || objData.deleted_at == 0;
            const confirmKey = willDelete
                ? 'DoYouWantToDeleteItem'
                : 'DoYouWantToRecover';
            const newDeleted = willDelete ? 0 : 1;
            const oldDeleted = objData.deleted_at;

            confirm(t(confirmKey)).then(() => {
                setLoader?.(false);
                http({
                    data: {
                        url: url + '/del',
                        ...objData,
                        deleted_at: newDeleted,
                    },
                })
                    .then(() => {
                        success(t('Successful'));
                        getData?.();
                    })
                    .catch((err) => {
                        setLoader?.(true);
                        // rollback
                        objData.deleted_at = oldDeleted;
                        return error(t(err?.message || 'ErrorAction'));
                    });
            }).catch(() => {
                /* user cancel — không làm gì */
            });
        }

        function updateSelected(objData, url) {
            confirm(t('DoYouWantUpdateSelected')).then(() => {
                const inst = loading.open();
                setLoader?.(false);
                http({
                    data: {
                        url,
                        update_selected: true,
                        ...objData,
                    },
                })
                    .then((res) => {
                        inst.close();
                        getData?.();
                        return success(t(res.message));
                    })
                    .catch((err) => {
                        inst.close();
                        setLoader?.(true);
                        return error(t(err?.message || 'ErrorAction'));
                    });
            }).catch(() => {});
        }

        function sort(sortField) {
            if (!objSearch || !setObjSearch) return;
            const nextOrder = objSearch.order === 'asc' ? 'desc' : 'asc';
            setObjSearch({ ...objSearch, sort: sortField, order: nextOrder });
            setLoader?.(false);
            // getData sẽ chạy ở useEffect phụ thuộc objSearch (recommended)
            // hoặc gọi trực tiếp:
            getData?.();
        }

        function pageChange(pageNum) {
            if (!objSearch || !setObjSearch) return;
            setObjSearch({ ...objSearch, pageIndex: pageNum });
            setLoader?.(false);
            getData?.();
        }

        /**
         * Lấy detail (khi vào trang edit).
         * Lưu ý: trong React, đừng tự setObjForm bằng res.data nếu component
         * tự handle. Hàm này giữ logic bản cũ cho ai muốn dùng nhanh.
         */
        function getDetailAction(url, extraData = {}) {
            const inst = loading.open();
            if (objForm?.id > 0) {
                http({
                    data: {
                        url: url + '/' + objForm.id,
                        method: 'get',
                        ...extraData,
                    },
                })
                    .then((res) => {
                        setObjForm?.(res.data);
                        setLoader?.(true);
                        inst.close();
                    })
                    .catch((err) => {
                        inst.close();
                        setLoader?.(true);
                        return error(t(err?.message || 'ErrorAction'));
                    });
            } else {
                inst.close();
                setLoader?.(true);
            }
        }

        function getDataList(func) {
            const inst = loading.open();
            func(objSearch)
                .then(() => {
                    inst.close();
                    setLoader?.(true);
                })
                .catch((err) => {
                    inst.close();
                    setLoader?.(true);
                    return error(t(err?.message || 'ErrorAction'));
                });
        }

        /**
         * Lưu form. Khi tạo mới xong:
         *   - edit=false  → push về trang list (routeList)
         *   - edit=true   → push về trang edit (routeEdit/:id) rồi load detail
         * routeList / routeEdit là PATH (react-router v6), không phải name.
         */
        function saveDataCms(url, routeList, routeEdit, edit = false) {
            confirm(t('DoYouWantSave')).then(() => {
                const inst = loading.open();
                setLoader?.(false);
                setErrors?.([]);
                http({
                    data: {
                        url: url + '/save',
                        ...objForm,
                    },
                })
                    .then((res) => {
                        inst.close();
                        success(t(res.message)).then(() => {
                            if (!edit) {
                                return navigate(routeList);
                            }
                            if (objForm.id > 0) {
                                getDetail?.();
                                return;
                            }
                            const newId = res.data;
                            setObjForm?.({ ...objForm, id: newId });
                            // routeEdit ví dụ: '/product/edit/' (nối id vào sau)
                            navigate(routeEdit.replace(':id', newId));
                            getDetail?.();
                        });
                    })
                    .catch((err) => {
                        inst.close();
                        setLoader?.(true);
                        setErrors?.(err?.message || []);
                        return error(t('ErrorSaveAction'));
                    });
            }).catch(() => {});
        }

        return {
            changeStatus,
            updateSelected,
            sort,
            pageChange,
            getDetail: getDetailAction,
            getDataList,
            saveDataCms,
        };
    }, [
        objSearch,
        setObjSearch,
        objForm,
        setObjForm,
        setLoader,
        setErrors,
        getData,
        getDetail,
        http,
        loading,
        t,
        navigate,
    ]);
}
