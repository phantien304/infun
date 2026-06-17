/**
 * AppLayout.jsx — convert từ app.vue
 * -----------------------------------------------------------
 * Layout chính của CMS (sau khi đăng nhập). Gồm:
 *   1. Topbar trái: Logo
 *   2. Topbar phải: Avatar dropdown (Website / DeleteCache / Logout)
 *   3. Menu toggle (đổi class body 'enlarged')
 *   4. <MenuLeft /> (sidebar)
 *   5. <Outlet />  (router-view của child routes)
 *   6. Modal DeleteCache
 *
 * Mapping từ Vue 2:
 *   - this.currentUser            → useCurrentUser()
 *   - this.appSettings            → useAppSettings()
 *   - v-click-outside             → useClickOutside(ref, fn)
 *   - this.$confirm / $error / $success → confirm/error/success
 *   - this.$loading               → useLoading()
 *   - this.$http                  → useHttp()
 *   - this.$router.push           → useNavigate()
 *   - this.$router.go(0)          → window.location.reload()
 *   - localStorage MENU_MODE      → useState + useEffect
 *   - keep-alive MenuLeft         → React giữ state mặc định, không cần
 *   - <modal>                     → <Modal /> wrapper antd
 *
 * Lưu ý: bản Vue 2 có `v-if="currentUser"` — nếu không có user thì
 * không render gì. RequireAuth đã redirect /login nếu thiếu, nên chỗ này
 * em vẫn giữ guard cho an toàn.
 * -----------------------------------------------------------
 */

import React, { useEffect, useRef, useState } from 'react';
import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useCurrentUser } from '@/core/stores/userStore';
import { useAppSettings } from '@/core/stores/appSettingsStore';
import useTranslation from '@/core/hooks/useTranslation';
import useHttp from '@/core/hooks/useHttp';
import useLoading from '@/core/hooks/useLoading';
import useClickOutside from '@/core/hooks/useClickOutside';
import useUserStore from '@/core/stores/userStore';
import CONSTANTS from '@/core/utils/constants';
import { confirm, error, success } from '@/core/services/alert';
import MenuLeft from './MenuLeft';
import Modal from '@/components/ui/Modal';
import Avatar from '@/components/ui/Avatar';

export default function AppLayout() {
    const currentUser = useCurrentUser();
    const appSettings = useAppSettings();
    const t = useTranslation();
    const http = useHttp();
    const loading = useLoading();
    const navigate = useNavigate();
    const logoutStore = useUserStore((s) => s.logout);

    const [showMenuUser, setShowMenuUser] = useState(false);
    const [popupChangeCache, setPopupChangeCache] = useState(false);
    const [menuMode, setMenuMode] = useState(() => {
        try {
            return localStorage.getItem(CONSTANTS.MENU_MODE) || '';
        } catch (e) {
            return '';
        }
    });

    // v-click-outside="closeAccountSetting" trên ul.navbar-right
    const dropdownRef = useRef(null);
    useClickOutside(dropdownRef, () => setShowMenuUser(false));

    // watch menuMode → thêm/xoá class 'enlarged' trên <body>
    useEffect(() => {
        const body = document.getElementsByTagName('body')[0];
        if (menuMode === 'enlarged') body.classList.add('enlarged');
        else body.classList.remove('enlarged');
    }, [menuMode]);

    function toggleMenuMode() {
        const next = menuMode === 'enlarged' ? '' : 'enlarged';
        setMenuMode(next);
        try {
            localStorage.setItem(CONSTANTS.MENU_MODE, next);
        } catch (e) {
            // ignore
        }
    }

    function deleteCache() {
        confirm(t('DoYouWantDelete')).then(() => {
            const inst = loading.open();
            http({ data: { url: '/setting/del' } })
                .then((res) => {
                    inst.close();
                    if (res.success) {
                        setPopupChangeCache(false);
                        success(t(res.message)).then(() => {
                            // $router.go(0) → reload trang
                            window.location.reload();
                        });
                    }
                })
                .catch((err) => {
                    inst.close();
                    error(t(err?.message || 'ErrorAction'));
                });
        });
    }

    function logOut() {
        return http({
            data: { method: 'get', url: '/logout' },
        })
            .then(() => {
                try {
                    localStorage.removeItem(CONSTANTS.USERNAME);
                    localStorage.removeItem(CONSTANTS.MENU_MODE);
                } catch (e) {}
                logoutStore?.();
                navigate('/login');
            })
            .catch((err) => {
                alert(err?.message || 'Logout failed');
            });
    }

    // v-if="currentUser" — render trống nếu không có user
    if (!currentUser) return null;

    const configLogo = appSettings?.config?.config_logo || '';

    return (
        <div id="app-full">
            <div id="wrapper">
                {/* ---------------- Topbar ---------------- */}
                <div className="topbar">
                    <div className="topbar-left">
                        <Link to="/" className="logo">
                            <span>
                                <img src={'/' + configLogo} alt="" height="50" />
                            </span>
                            <i>
                                <img src={'/' + configLogo} alt="" height="40" />
                            </i>
                        </Link>
                    </div>
                    <nav className="navbar-custom">
                        <ul
                            ref={dropdownRef}
                            className="navbar-right d-flex list-inline float-right mb-0"
                        >
                            <li className="dropdown notification-list">
                                <div className="dropdown notification-list nav-pro-img">
                                    <a
                                        className="dropdown-toggle nav-link arrow-none waves-effect nav-user"
                                        href="javascript:;"
                                        role="button"
                                        onClick={() => setShowMenuUser((v) => !v)}
                                    >
                                        <Avatar
                                            username={currentUser || 'M'}
                                            size={40}
                                        />
                                    </a>
                                    {showMenuUser && (
                                        <div className="dropdown-menu dropdown-menu-right profile-dropdown display-b">
                                            <a
                                                className="dropdown-item"
                                                href="/"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <i className="mdi mdi-google-chrome m-r-5" />{' '}
                                                {t('Website')}
                                            </a>
                                            <a
                                                className="dropdown-item"
                                                href="javascript:void(0);"
                                                onClick={() => setPopupChangeCache(true)}
                                            >
                                                <i className="mdi mdi-brightness-5" />{' '}
                                                {t('DeleteCache')}
                                            </a>
                                            <div className="dropdown-divider" />
                                            <a
                                                className="dropdown-item text-danger"
                                                href="javascript:void(0);"
                                                onClick={logOut}
                                            >
                                                <i className="mdi mdi-power text-danger" />{' '}
                                                {t('Logout')}
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </li>
                        </ul>
                        <ul className="list-inline menu-left mb-0">
                            <li className="float-left">
                                <button
                                    type="button"
                                    className="button-menu-mobile open-left waves-effect"
                                    onClick={toggleMenuMode}
                                >
                                    <i className="mdi mdi-menu" />
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>

                {/* ---------------- Sidebar ---------------- */}
                <MenuLeft />

                {/* ---------------- Content ---------------- */}
                <div className="content-page">
                    <div className="content">
                        <div className="container-fluid">
                            <Outlet />
                        </div>
                    </div>
                </div>
            </div>

            {/* ---------------- Modal Delete Cache ---------------- */}
            {popupChangeCache && (
                <Modal
                    open={popupChangeCache}
                    title={t('TitleDeleteCache')}
                    width={325}
                    footer={false}
                    onClose={() => setPopupChangeCache(false)}
                >
                    <div className="form-group" style={{ textAlign: 'center' }}>
                        <span
                            className="btn btn-danger w-md waves-effect waves-light"
                            onClick={deleteCache}
                        >
                            {t('DeleteCache')}
                        </span>
                    </div>
                </Modal>
            )}
        </div>
    );
}
