    <!-- Start Switcher -->
    <div class="offcanvas offcanvas-end theme-switcher-canvas" tabindex="-1" id="switcher-canvas" aria-labelledby="offcanvasRightLabel">
        <div class="theme-switcher-header">
            <div class="theme-switcher-header-content">
                <span class="theme-switcher-header-icon">
                    <i class="ri-palette-line"></i>
                </span>
                <div>
                    <h5 class="theme-switcher-title" id="offcanvasRightLabel">إعدادات العرض</h5>
                    <p class="theme-switcher-subtitle">خصّص مظهر لوحة التحكم</p>
                </div>
            </div>
            <button type="button" class="theme-switcher-close" data-bs-dismiss="offcanvas" aria-label="إغلاق">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div class="offcanvas-body theme-switcher-body">
            {{-- الوضع الليلي / النهاري --}}
            <section class="theme-switcher-section">
                <h6 class="theme-switcher-label">
                    <i class="ri-contrast-2-line"></i>
                    وضع العرض
                </h6>
                <div class="theme-mode-grid">
                    <label class="theme-mode-card" for="switcher-light-theme">
                        <input class="visually-hidden" type="radio" name="theme-style" id="switcher-light-theme" checked>
                        <span class="theme-mode-preview theme-mode-light">
                            <i class="ri-sun-line"></i>
                        </span>
                        <span class="theme-mode-name">نهاري</span>
                    </label>
                    <label class="theme-mode-card" for="switcher-dark-theme">
                        <input class="visually-hidden" type="radio" name="theme-style" id="switcher-dark-theme">
                        <span class="theme-mode-preview theme-mode-dark">
                            <i class="ri-moon-clear-line"></i>
                        </span>
                        <span class="theme-mode-name">ليلي</span>
                    </label>
                </div>
            </section>

            {{-- اللون الرئيسي --}}
            <section class="theme-switcher-section">
                <h6 class="theme-switcher-label">
                    <i class="ri-paint-brush-line"></i>
                    اللون الرئيسي
                </h6>
                <div class="theme-colors-row">
                    <label class="theme-color-swatch" for="switcher-primary" title="أزرق">
                        <input class="color-input color-primary-1" type="radio" name="theme-primary" id="switcher-primary">
                    </label>
                    <label class="theme-color-swatch" for="switcher-primary1" title="تركواز">
                        <input class="color-input color-primary-2" type="radio" name="theme-primary" id="switcher-primary1">
                    </label>
                    <label class="theme-color-swatch" for="switcher-primary2" title="بنفسجي">
                        <input class="color-input color-primary-3" type="radio" name="theme-primary" id="switcher-primary2">
                    </label>
                    <label class="theme-color-swatch" for="switcher-primary3" title="أخضر">
                        <input class="color-input color-primary-4" type="radio" name="theme-primary" id="switcher-primary3">
                    </label>
                    <label class="theme-color-swatch" for="switcher-primary4" title="أحمر">
                        <input class="color-input color-primary-5" type="radio" name="theme-primary" id="switcher-primary4">
                    </label>
                    <div class="theme-color-custom pickr-container-primary"></div>
                </div>
            </section>

            {{-- شكل القائمة --}}
            <section class="theme-switcher-section">
                <h6 class="theme-switcher-label">
                    <i class="ri-layout-grid-line"></i>
                    شكل القائمة
                </h6>
                <div class="theme-layout-grid">
                    <label class="theme-layout-card" for="switcher-default-menu">
                        <input class="visually-hidden" type="radio" name="sidemenu-layout-styles" id="switcher-default-menu" checked>
                        <i class="ri-layout-right-2-line"></i>
                        <span>كاملة</span>
                    </label>
                    <label class="theme-layout-card" for="switcher-closed-menu">
                        <input class="visually-hidden" type="radio" name="sidemenu-layout-styles" id="switcher-closed-menu">
                        <i class="ri-menu-fold-line"></i>
                        <span>مطوية</span>
                    </label>
                    <label class="theme-layout-card" for="switcher-icon-overlay">
                        <input class="visually-hidden" type="radio" name="sidemenu-layout-styles" id="switcher-icon-overlay">
                        <i class="ri-menu-line"></i>
                        <span>أيقونات</span>
                    </label>
                </div>
            </section>

            {{-- خيارات مخفية مطلوبة للسكربت --}}
            <div class="d-none" aria-hidden="true">
                <input type="radio" name="direction" id="switcher-rtl" checked>
                <input type="radio" name="direction" id="switcher-ltr">
                <input type="radio" name="navigation-style" id="switcher-vertical" checked>
                <input type="radio" name="navigation-style" id="switcher-horizontal">
                <input type="radio" name="navigation-menu-styles" id="switcher-menu-click">
                <input type="radio" name="navigation-menu-styles" id="switcher-menu-hover">
                <input type="radio" name="navigation-menu-styles" id="switcher-icon-click">
                <input type="radio" name="navigation-menu-styles" id="switcher-icon-hover">
                <input type="radio" name="sidemenu-layout-styles" id="switcher-icontext-menu">
                <input type="radio" name="sidemenu-layout-styles" id="switcher-detached">
                <input type="radio" name="sidemenu-layout-styles" id="switcher-double-menu">
                <input type="radio" name="page-styles" id="switcher-regular" checked>
                <input type="radio" name="page-styles" id="switcher-classic">
                <input type="radio" name="page-styles" id="switcher-modern">
                <input type="radio" name="layout-width" id="switcher-full-width" checked>
                <input type="radio" name="layout-width" id="switcher-boxed">
                <input type="radio" name="menu-positions" id="switcher-menu-fixed" checked>
                <input type="radio" name="menu-positions" id="switcher-menu-scroll">
                <input type="radio" name="header-positions" id="switcher-header-fixed" checked>
                <input type="radio" name="header-positions" id="switcher-header-scroll">
                <input type="radio" name="page-loader" id="switcher-loader-disable" checked>
                <input type="radio" name="page-loader" id="switcher-loader-enable">
                <input type="radio" name="menu-colors" id="switcher-menu-light" checked>
                <input type="radio" name="menu-colors" id="switcher-menu-dark">
                <input type="radio" name="menu-colors" id="switcher-menu-primary">
                <input type="radio" name="menu-colors" id="switcher-menu-gradient">
                <input type="radio" name="menu-colors" id="switcher-menu-transparent">
                <input type="radio" name="header-colors" id="switcher-header-light" checked>
                <input type="radio" name="header-colors" id="switcher-header-dark">
                <input type="radio" name="header-colors" id="switcher-header-primary">
                <input type="radio" name="header-colors" id="switcher-header-gradient">
                <input type="radio" name="header-colors" id="switcher-header-transparent">
                <input type="radio" name="theme-background" id="switcher-background">
                <input type="radio" name="theme-background" id="switcher-background1">
                <input type="radio" name="theme-background" id="switcher-background2">
                <input type="radio" name="theme-background" id="switcher-background3">
                <input type="radio" name="theme-background" id="switcher-background4">
                <input type="radio" name="theme-background" id="switcher-bg-img">
                <input type="radio" name="theme-background" id="switcher-bg-img1">
                <input type="radio" name="theme-background" id="switcher-bg-img2">
                <input type="radio" name="theme-background" id="switcher-bg-img3">
                <input type="radio" name="theme-background" id="switcher-bg-img4">
                <div class="theme-container-primary"></div>
                <div class="theme-container-background"></div>
                <div class="pickr-container-background"></div>
            </div>
        </div>

        <div class="theme-switcher-footer">
            <a href="javascript:void(0);" id="reset-all" class="theme-switcher-reset">
                <i class="ri-refresh-line"></i>
                إعادة ضبط الإعدادات
            </a>
        </div>
    </div>
    <!-- End Switcher -->
