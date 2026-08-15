import { Link, router, usePage } from '@inertiajs/react';
import NotificationBell from '@/Components/Admin/NotificationBell';
import { toggleSidemenu, toggleThemeMode } from './themeControls';

export default function Header() {
    const { auth, site } = usePage().props;
    const user = auth.user;
    const siteName = site?.site_name || 'لوحة التحكم';
    const siteInitial = siteName.trim().charAt(0) || 'ل';

    const logout = (e) => {
        e.preventDefault();
        router.post(route('logout'));
    };

    const onToggleSidebar = (e) => {
        e.preventDefault();
        toggleSidemenu();
    };

    const onToggleTheme = (e) => {
        e.preventDefault();
        toggleThemeMode();
    };

    return (
        <header className="app-header">
            <div className="main-header-container container-fluid">
                <div className="header-content-left">
                    <div className="header-element">
                        <div className="horizontal-logo">
                            <Link
                                href={route('admin.dashboard')}
                                className="header-logo admin-brand-text"
                                title={siteName}
                            >
                                <span className="admin-brand-text__full">{siteName}</span>
                                <span className="admin-brand-text__mark" aria-hidden="true">
                                    {siteInitial}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <div className="header-element">
                        <a
                            aria-label="إظهار / إخفاء القائمة"
                            title="إظهار / إخفاء القائمة"
                            className="sidemenu-toggle header-link admin-sidebar-toggle"
                            href="#"
                            onClick={onToggleSidebar}
                        >
                            <span className="admin-sidebar-toggle__icon" aria-hidden="true">
                                <span />
                                <span />
                                <span />
                            </span>
                        </a>
                        <div className="main-header-center d-none d-lg-block">
                            <input
                                className="form-control admin-header-search"
                                placeholder="إكتب للبحث..."
                                type="search"
                                style={{ borderRadius: 5 }}
                            />
                            <button className="btn" type="button">
                                <i className="fa fa-search d-none d-md-block" />
                            </button>
                        </div>
                    </div>
                </div>

                <div className="header-content-right">
                    <div className="header-element Search-element d-block d-lg-none">
                        <a
                            href="javascript:void(0);"
                            className="header-link dropdown-toggle"
                            data-bs-auto-close="outside"
                            data-bs-toggle="dropdown"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 -960 960 960"
                                className="header-link-icon"
                            >
                                <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                            </svg>
                        </a>
                        <ul className="main-header-dropdown dropdown-menu dropdown-menu-end Search-element-dropdown">
                            <li>
                                <div className="input-group w-100 p-2">
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Search...."
                                    />
                                    <div className="btn btn-primary">
                                        <i className="fa fa-search" aria-hidden="true" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div className="header-element header-theme-mode">
                        <a
                            href="#"
                            className="header-link layout-setting admin-theme-toggle"
                            onClick={onToggleTheme}
                            title="تبديل الوضع"
                            aria-label="تبديل الوضع الفاتح / الداكن"
                        >
                            <span className="light-layout admin-theme-toggle__icon admin-theme-toggle__icon--moon">
                                <i className="ri-moon-clear-fill" />
                            </span>
                            <span className="dark-layout admin-theme-toggle__icon admin-theme-toggle__icon--sun">
                                <i className="ri-sun-fill" />
                            </span>
                        </a>
                    </div>

                    <div className="header-element messages-dropdown">
                        <a
                            href="javascript:void(0);"
                            className="header-link dropdown-toggle"
                            data-bs-auto-close="outside"
                            data-bs-toggle="dropdown"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="header-link-icon"
                                height="24px"
                                viewBox="0 0 24 24"
                                width="24px"
                                fill="currentColor"
                            >
                                <path d="M0 0h24v24H0V0z" fill="none" />
                                <path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z" />
                            </svg>
                            <span className="pulse-danger" />
                        </a>
                        <div className="main-header-dropdown dropdown-menu dropdown-menu-end main-header-message">
                            <div className="menu-header-content bg-primary text-fixed-white">
                                <div className="d-flex align-items-center justify-content-between">
                                    <h6 className="mb-0 fs-15 fw-semibold text-fixed-white">
                                        الرسائل
                                    </h6>
                                    <span className="badge rounded-pill bg-warning pt-1 text-fixed-black">
                                        تحديد الكل كمقروء
                                    </span>
                                </div>
                                <p className="dropdown-title-text subtext mb-0 text-fixed-white op-6 pb-0 fs-12">
                                    لديك 0 رسائل جديدة
                                </p>
                            </div>
                            <div>
                                <hr className="dropdown-divider" />
                            </div>
                            <ul className="list-unstyled mb-0" id="header-cart-items-scroll">
                                <li className="dropdown-item text-center py-3">
                                    <p className="text-muted mb-0">لا توجد رسائل جديدة</p>
                                </li>
                            </ul>
                            <div className="text-center dropdown-footer">
                                <a
                                    href="javascript:void(0);"
                                    className="text-primary fs-13"
                                >
                                    عرض الكل
                                </a>
                            </div>
                        </div>
                    </div>

                    <NotificationBell />

                    <div className="header-element header-fullscreen">
                        <a
                            href="javascript:void(0);"
                            className="header-link"
                            onClick={(e) => {
                                e.preventDefault();
                                if (typeof window.openFullscreen === 'function') {
                                    window.openFullscreen();
                                }
                            }}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="full-screen-open full-screen-icon header-link-icon"
                                height="24px"
                                viewBox="0 0 24 24"
                                width="24px"
                                fill="currentColor"
                            >
                                <path d="M0 0h24v24H0V0z" fill="none" />
                                <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z" />
                            </svg>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="full-screen-close full-screen-icon header-link-icon d-none"
                                fill="currentColor"
                                height="24"
                                viewBox="0 -960 960 960"
                                width="24"
                            >
                                <path d="M320-200v-120H200v-80h200v200h-80Zm240 0v-200h200v80H640v120h-80ZM200-560v-80h120v-120h80v200H200Zm360 0v-200h80v120h120v80H560Z" />
                            </svg>
                        </a>
                    </div>

                    <div className="header-element header-sidebar">
                        <a
                            href="javascript:void(0);"
                            className="header-link"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#header-sidebar"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="header-link-icon"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <line x1="3" y1="12" x2="21" y2="12" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <line x1="3" y1="18" x2="21" y2="18" />
                            </svg>
                        </a>
                    </div>

                    <div className="header-element headerProfile-dropdown">
                        <a
                            href="javascript:void(0);"
                            className="header-link dropdown-toggle"
                            id="mainHeaderProfile"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                        >
                            <img
                                src="/assets/images/faces/default-avatar.jpg"
                                alt="صورة المستخدم"
                                width="37"
                                height="37"
                                className="rounded-circle"
                            />
                        </a>
                        <ul
                            className="main-header-dropdown dropdown-menu pt-0 header-profile-dropdown dropdown-menu-end main-profile-menu"
                            aria-labelledby="mainHeaderProfile"
                        >
                            <li>
                                <div className="main-header-profile bg-primary menu-header-content text-fixed-white">
                                    <div className="my-auto">
                                        <h6 className="mb-0 lh-1 text-fixed-white">
                                            {user?.name}
                                        </h6>
                                        <span className="fs-11 op-7 lh-1">
                                            {user?.email}
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <Link
                                    className="dropdown-item d-flex"
                                    href={route('admin.profile.edit')}
                                >
                                    <i className="bx bx-user-circle fs-18 me-2 op-7" />
                                    الملف الشخصي
                                </Link>
                            </li>
                            <li>
                                <a
                                    className="dropdown-item d-flex"
                                    href="#"
                                    onClick={logout}
                                >
                                    <i className="bx bx-log-out fs-18 me-2 op-7" />
                                    تسجيل الخروج
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div className="header-element">
                        <a
                            href="javascript:void(0);"
                            className="header-link switcher-icon"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#switcher-canvas"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="header-link-icon"
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                            >
                                <path d="M12 16c2.206 0 4-1.794 4-4s-1.794-4-4-4-4 1.794-4 4 1.794 4 4 4zm0-6c1.084 0 2 .916 2 2s-.916 2-2 2-2-.916-2-2 .916-2 2-2z" />
                                <path d="m2.845 16.136 1 1.73c.531.917 1.809 1.261 2.73.73l.529-.306A8.1 8.1 0 0 0 9 19.402V20c0 1.103.897 2 2 2h2c1.103 0 2-.897 2-2v-.598a8.132 8.132 0 0 0 1.896-1.111l.529.306c.923.53 2.198.188 2.731-.731l.999-1.729a2.001 2.001 0 0 0-.731-2.732l-.505-.292a7.718 7.718 0 0 0 0-2.224l.505-.292a2.002 2.002 0 0 0 .731-2.732l-.999-1.729c-.531-.92-1.808-1.265-2.731-.732l-.529.306A8.1 8.1 0 0 0 15 4.598V4c0-1.103-.897-2-2-2h-2c-1.103 0-2 .897-2 2v.598a8.132 8.132 0 0 0-1.896 1.111l-.529-.306c-.924-.531-2.2-.187-2.731.732l-.999 1.729a2.001 2.001 0 0 0 .731 2.732l.505.292a7.683 7.683 0 0 0 0 2.223l-.505.292a2.003 2.003 0 0 0-.731 2.733zm3.326-2.758A5.703 5.703 0 0 1 6 12c0-.462.058-.926.17-1.378a.999.999 0 0 0-.47-1.108l-1.123-.65.998-1.729 1.145.662a.997.997 0 0 0 1.188-.142 6.071 6.071 0 0 1 2.384-1.399A1 1 0 0 0 11 5.3V4h2v1.3a1 1 0 0 0 .708.956 6.083 6.083 0 0 1 2.384 1.399.999.999 0 0 0 1.188.142l1.144-.661 1 1.729-1.124.649a1 1 0 0 0-.47 1.108c.112.452.17.916.17 1.378 0 .461-.058.925-.171 1.378a1 1 0 0 0 .471 1.108l1.123.649-.998 1.729-1.145-.661a.996.996 0 0 0-1.188.142 6.071 6.071 0 0 1-2.384 1.399A1 1 0 0 0 13 18.7l.002 1.3H11v-1.3a1 1 0 0 0-.708-.956 6.083 6.083 0 0 1-2.384-1.399.992.992 0 0 0-1.188-.141l-1.144.662-1-1.729 1.124-.651a1 1 0 0 0 .471-1.108z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </header>
    );
}
