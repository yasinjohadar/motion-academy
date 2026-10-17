import HeaderSidebar from '@/Components/Admin/HeaderSidebar';
import { useEffect } from 'react';
import Footer from './Footer';
import Header from './Header';
import Sidebar from './Sidebar';
import { bindOverlayClose, bindSwitcherControls, initThemeAttributes } from './themeControls';

export default function AdminLayout({ children }) {
    useEffect(() => {
        document.body.classList.add('admin-portal');
        initThemeAttributes();
        bindOverlayClose();
        bindSwitcherControls();

        const loader = document.getElementById('loader');
        if (loader) {
            loader.classList.add('d-none');
            loader.style.display = 'none';
        }

        return () => {
            document.body.classList.remove('admin-portal');
        };
    }, []);

    return (
        <>
            <div id="loader">
                <img src="/assets/images/media/loader.svg" alt="" />
            </div>

            <div className="page admin-portal-page">
                <Header />
                <Sidebar />
                {children}
                <Footer />
            </div>

            {/* Opened by the header's hamburger through Bootstrap's delegated
                data-bs-toggle handler, so it must keep the id `header-sidebar`. */}
            <HeaderSidebar />
        </>
    );
}
