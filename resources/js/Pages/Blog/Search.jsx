import BlogLayout from '@/Layouts/BlogLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Search({ posts, q = '', seo }) {
    const [query, setQuery] = useState(q);

    const submit = (e) => {
        e.preventDefault();
        router.get(route('blog.search'), { q: query }, { preserveState: true });
    };

    return (
        <BlogLayout seo={seo}>
            <h1 className="display-6 fw-bold mb-3">بحث في المدونة</h1>
            <form onSubmit={submit} className="row g-2 mb-4">
                <div className="col-md-9">
                    <input
                        className="form-control form-control-lg"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="ابحث عن مقال..."
                    />
                </div>
                <div className="col-md-3">
                    <button type="submit" className="btn btn-primary btn-lg w-100">
                        بحث
                    </button>
                </div>
            </form>

            <div className="row g-3">
                {(posts.data || []).map((post) => (
                    <div className="col-md-4" key={post.id}>
                        <Link
                            href={route('blog.show', post.slug)}
                            className="card h-100 text-decoration-none text-dark border-0 shadow-sm"
                        >
                            <div className="card-body">
                                <h2 className="h5">{post.title}</h2>
                                <p className="small text-muted">{post.excerpt}</p>
                            </div>
                        </Link>
                    </div>
                ))}
                {(posts.data || []).length === 0 && (
                    <div className="col-12 text-muted">لا توجد نتائج.</div>
                )}
            </div>
        </BlogLayout>
    );
}
