/**
 * FormSearch.jsx — gộp form_search.vue + form_search_no_language.vue
 * -----------------------------------------------------------
 * 2 file Vue 2 gốc gần như giống hệt nhau, chỉ khác có/không
 * dropdown language. Em gộp thành 1 component với prop `showLanguage`.
 *
 *  Vue 2:                      |  React:
 *  v-model="objAction.deleted_at" → value/onChange controlled bởi parent
 *  v-model="objSearch.keyword"    → value/onChange controlled bởi parent
 *  v-on:click="changeStatus(objAction)" → onClick={() => changeStatus(objAction)}
 *  v-on:click="func"            → onClick={onSearch}
 *  router-link :to="{name: nameAdd}" → <Link to={addPath}>
 *  {{$i(...)}}                  → {t(...)}
 *
 * API thay đổi nhỏ:
 *   - prop `nameAdd` (route name Vue) → `addPath` (URL path React).
 *   - prop `func` đổi tên `onSearch` cho rõ.
 *   - parent quản lý state objSearch / objAction (controlled).
 * -----------------------------------------------------------
 */

import React from 'react';
import { Link } from 'react-router-dom';
import useTranslation from '@/core/hooks/useTranslation';
import { useListLanguages } from '@/core/stores/appSettingsStore';

export default function FormSearch({
    addPath,
    objAction,
    setObjAction,
    objSearch,
    setObjSearch,
    onSearch,
    changeStatus,
    showLanguage = true,
}) {
    const t = useTranslation();
    const listLanguages = useListLanguages();

    const setAction = (patch) => setObjAction({ ...objAction, ...patch });
    const setSearch = (patch) => setObjSearch({ ...objSearch, ...patch });

    return (
        <div className="row" id="form-search">
            {/* --- Cụm Action (trái) --- */}
            <div className="col-xl-3">
                <div className="row">
                    <div className="col-xl-4">
                        <select
                            className="form-control"
                            value={objAction.deleted_at}
                            onChange={(e) => setAction({ deleted_at: e.target.value })}
                        >
                            <option value="-1">{t('Select')}</option>
                            <option value="1">{t('Recover')}</option>
                            <option value="0">{t('Delete')}</option>
                        </select>
                    </div>
                    <div className="col-xl-6">
                        <input
                            className="btn btn-secondary"
                            type="button"
                            onClick={() => changeStatus(objAction)}
                            value={t('Save')}
                        />
                    </div>
                </div>
            </div>

            {/* --- Cụm Search (phải) --- */}
            <div className="col-xl-9">
                <div className="row">
                    <div className={showLanguage ? 'col-xl-2' : 'col-xl-4'} />

                    <div className="col-xl-4">
                        <input
                            type="text"
                            className="form-control"
                            placeholder={t('Keyword')}
                            value={objSearch.keyword || ''}
                            onChange={(e) => setSearch({ keyword: e.target.value })}
                        />
                    </div>

                    {showLanguage && (
                        <div className="col-xl-2">
                            <select
                                className="form-control"
                                value={objSearch.language_code || ''}
                                onChange={(e) =>
                                    setSearch({ language_code: e.target.value })
                                }
                            >
                                <option value="">{t('SelectLanguage')}</option>
                                {listLanguages.map((item) => (
                                    <option key={item.code} value={item.code}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="col-xl-2">
                        <select
                            className="form-control"
                            value={objSearch.deleted_at}
                            onChange={(e) =>
                                setSearch({ deleted_at: parseInt(e.target.value, 10) })
                            }
                        >
                            <option value={-1}>{t('ShowAll')}</option>
                            <option value={1}>{t('Active')}</option>
                            <option value={0}>{t('DeActive')}</option>
                        </select>
                    </div>

                    <div className="col-xl-1">
                        <input
                            className="btn btn-info"
                            type="button"
                            onClick={onSearch}
                            value={t('Search')}
                        />
                    </div>

                    <div className="col-xl-1">
                        <Link to={addPath} className="btn btn-primary">
                            {t('Add')}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
