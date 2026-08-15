import { Head, Link, usePage } from '@inertiajs/react';

export default function BlogLayout({ children, seo = {}, schema = null }) {
    const { site } = usePage().props;
    const title = seo.title || seo['og:title'] || site?.name || 'المدونة';
    const description = seo.description || seo['og:description'] || '';
    const image = seo['og:image'] || seo.image || site?.logo_url || '';
    const canonical = seo.canonical || '';
    const robots = seo.robots || 'index,follow';

    return (
        <div className="blog-shell min-vh-100" style={{ background: 'linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%)' }}>
            <Head>
                <title>{title}</title>
                {description && <meta name="description" content={description} />}
                {seo.keywords && <meta name="keywords" content={seo.keywords} />}
                <meta name="robots" content={robots} />
                {canonical && <link rel="canonical" href={canonical} />}
                <meta property="og:title" content={seo['og:title'] || title} />
                {description && <meta property="og:description" content={seo['og:description'] || description} />}
                {image && <meta property="og:image" content={image} />}
                <meta property="og:type" content={seo['og:type'] || 'website'} />
                <meta property="og:locale" content={seo['og:locale'] || 'ar_SA'} />
                <meta name="twitter:card" content={seo['twitter:card'] || 'summary_large_image'} />
                <meta name="twitter:title" content={seo['twitter:title'] || title} />
                {(seo['twitter:description'] || description) && (
                    <meta name="twitter:description" content={seo['twitter:description'] || description} />
                )}
                {(seo['twitter:image'] || image) && (
                    <meta name="twitter:image" content={seo['twitter:image'] || image} />
                )}
                {schema && (
                    <script type="application/ld+json">{JSON.stringify(schema)}</script>
                )}
            </Head>

            <header className="border-bottom bg-white bg-opacity-75 backdrop-blur sticky-top">
                <div className="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <Link href={route('blog.index')} className="text-decoration-none fw-bold fs-4 text-dark">
                        {site?.name || 'المدونة'}
                    </Link>
                    <nav className="d-flex flex-wrap gap-3 align-items-center">
                        <Link href={route('blog.index')} className="text-decoration-none text-secondary">
                            الرئيسية
                        </Link>
                        <Link href={route('blog.search')} className="text-decoration-none text-secondary">
                            بحث
                        </Link>
                        <Link href={route('login')} className="btn btn-sm btn-primary">
                            لوحة التحكم
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="container py-4 py-md-5">{children}</main>

            <footer className="border-top py-4 mt-auto">
                <div className="container text-center text-muted small">
                    © {new Date().getFullYear()} {site?.name || 'المدونة'}
                </div>
            </footer>
        </div>
    );
}
