/**
 * MenuLeft.jsx — convert từ menu_left.vue
 * -----------------------------------------------------------
 * Sidebar có menu lồng nhau (1 cấp). Click vào nhóm cha thì xổ children;
 * click vào lá thì navigate. Active item lưu trong sessionStorage giống
 * bản cũ.
 *
 * Mapping:
 *   - data().activeMenu, currentLink     → useState
 *   - data().menuLeft                    → import từ menuConfig.js
 *   - <router-link :to="{name}">          → <NavLink to={path}>
 *   - @click="activate"                  → onClick
 *   - {{ $i(label) }}                    → {t(label)}
 *   - sessionStorage.getItem/setItem     → giữ nguyên
 *   - created() / mounted()              → useEffect(() => {}, [])
 * -----------------------------------------------------------
 */

import React, { useEffect, useState } from 'react';
import { NavLink } from 'react-router-dom';
import useTranslation from '@/core/hooks/useTranslation';
import MENU from './menuConfig';

export default function MenuLeft() {
    const t = useTranslation();

    const [activeMenu, setActiveMenu] = useState('');
    const [checkActiveParent, setCheckActiveParent] = useState(false);
    const [currentLink, setCurrentLink] = useState('');

    // Restore trạng thái menu từ sessionStorage khi mount (giống created() cũ)
    useEffect(() => {
        const root = sessionStorage.getItem('root');
        const link = sessionStorage.getItem('current_link');
        if (root) {
            setActiveMenu(root);
            setCheckActiveParent(true);
        }
        if (link) setCurrentLink(link);
    }, []);

    function activate(id) {
        if (activeMenu === id) {
            setCheckActiveParent((v) => !v);
        } else {
            setCheckActiveParent(true);
            setActiveMenu(id);
        }
    }

    function handleClickLink(path, rootId) {
        sessionStorage.setItem('root', rootId);
        sessionStorage.setItem('current_link', path);
        setCurrentLink(path);
    }

    return (
        <div className="left side-menu">
            <div className="slimscroll-menu" id="remove-scroll">
                <div id="sidebar-menu">
                    <ul className="metismenu" id="side-menu">
                        {MENU.map((item) => {
                            const hasChildren = item.children && item.children.length > 0;
                            const expanded = activeMenu === item.id && checkActiveParent;

                            return (
                                <li key={item.id}>
                                    {hasChildren ? (
                                        <a
                                            href="javascript:void(0);"
                                            onClick={() => activate(item.id)}
                                        >
                                            <i className={item.icon} />
                                            <span>
                                                {t(item.label)}
                                                <span className="float-right menu-arrow">
                                                    <i
                                                        className={
                                                            'mdi mdi-chevron-right' +
                                                            (expanded ? ' mdi-rotate-90' : '')
                                                        }
                                                    />
                                                </span>
                                            </span>
                                        </a>
                                    ) : (
                                        <NavLink
                                            to={item.path}
                                            onClick={() => handleClickLink(item.path, item.id)}
                                            className={({ isActive }) =>
                                                isActive || currentLink === item.path ? 'active' : ''
                                            }
                                        >
                                            <i className={item.icon} />
                                            <span>{t(item.label)}</span>
                                        </NavLink>
                                    )}

                                    {hasChildren && (
                                        <ul
                                            className={
                                                'submenu collapse' + (expanded ? ' in' : '')
                                            }
                                        >
                                            {item.children.map((child) => (
                                                <li
                                                    key={child.path}
                                                    className={
                                                        currentLink === child.path ? 'active' : ''
                                                    }
                                                >
                                                    <NavLink
                                                        to={child.path}
                                                        onClick={() =>
                                                            handleClickLink(child.path, item.id)
                                                        }
                                                    >
                                                        <span>{t(child.label)}</span>
                                                    </NavLink>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </div>
                <div className="clearfix" />
            </div>
        </div>
    );
}
