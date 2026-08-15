import BlogLayout from '@/Layouts/BlogLayout';
import { Link } from '@inertiajs/react';

function PostCard({ post }) {
    return (
        <article className="card h-100 border-0 shadow-sm">
            {post.featured_image_url && (
                <Link href={route('blog.show', post.slug)}>
                    <img
                        src={post.featured_image_url}
                        alt={post.featured_image_alt || post.title}
                        className="card-img-top"
                        style={{ height: 180, objectFit: 'cover' }}
                    />
                </Link>
            )}
            <div className="card-body d-flex flex-column">
                {post.category && (
                    <Link
                        href={route('blog.category', post.category.slug)}
                        className="small text-primary text-decoration-none mb-2"
                    >
                        {post.category.name}
                    </Link>
                )}
                <h2 className="h5">
                    <Link href={route('blog.show', post.slug)} className="text-decoration-none text-dark">
                        {post.title}
                    </Link>
                </h2>
                <p className="text-muted small flex-grow-1">{post.excerpt}</p>
                <div className="d-flex justify-content-between small text-muted">
                    <span>{post.published_at_human}</span>
                    <span>{post.reading_time ? `${post.reading_time} د` : ''}</span>
                </div>
            </div>
        </article>
    );
}

export default function Index({ posts, featured = [], categories = [], seo }) {
    return (
        <BlogLayout seo={seo}>
            <div className="mb-4">
                <h1 className="display-6 fw-bold mb-2">المدونة</h1>
                <p className="text-muted mb-0">مقالات ونصائح وأخبار المنصة</p>
            </div>

            {categories.length > 0 && (
                <div className="d-flex flex-wrap gap-2 mb-4">
                    {categories.map((cat) => (
                        <Link
                            key={cat.id}
                            href={route('blog.category', cat.slug)}
                            className="badge rounded-pill text-bg-light border text-decoration-none px-3 py-2"
                        >
                            {cat.name}
                        </Link>
                    ))}
                </div>
            )}

            {featured.length > 0 && (
                <section className="mb-5">
                    <h2 className="h4 mb-3">مميزة</h2>
                    <div className="row g-3">
                        {featured.map((post) => (
                            <div className="col-md-4" key={post.id}>
                                <PostCard post={post} />
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section>
                <h2 className="h4 mb-3">أحدث المقالات</h2>
                <div className="row g-3">
                    {(posts.data || []).map((post) => (
                        <div className="col-md-4" key={post.id}>
                            <PostCard post={post} />
                        </div>
                    ))}
                    {(posts.data || []).length === 0 && (
                        <div className="col-12 text-muted">لا توجد مقالات منشورة بعد.</div>
                    )}
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
                            ) : (
                                <span
                                    key={i}
                                    className="btn btn-sm btn-outline-secondary disabled"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                )}
            </section>
        </BlogLayout>
    );
}
