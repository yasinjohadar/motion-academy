import BlogLayout from '@/Layouts/BlogLayout';
import { Link } from '@inertiajs/react';

export default function Category({ category, posts, seo }) {
    return (
        <BlogLayout seo={seo}>
            <h1 className="display-6 fw-bold mb-2">{category.name}</h1>
            {category.description && <p className="text-muted mb-4">{category.description}</p>}
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
            </div>
            {posts.links && (
                <div className="d-flex flex-wrap gap-2 mt-4">
                    {posts.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`btn btn-sm ${link.active ? 'btn-primary' : 'btn-outline-secondary'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </div>
            )}
        </BlogLayout>
    );
}
