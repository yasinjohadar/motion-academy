import BlogLayout from '@/Layouts/BlogLayout';
import { Link } from '@inertiajs/react';

export default function Show({ post, related = [], seo, schema }) {
    return (
        <BlogLayout seo={seo} schema={schema}>
            <article className="row justify-content-center">
                <div className="col-lg-9">
                    <div className="mb-3">
                        {post.category && (
                            <Link
                                href={route('blog.category', post.category.slug)}
                                className="badge text-bg-primary text-decoration-none"
                            >
                                {post.category.name}
                            </Link>
                        )}
                    </div>
                    <h1 className="display-6 fw-bold mb-3">{post.title}</h1>
                    <div className="d-flex flex-wrap gap-3 text-muted small mb-4">
                        <span>{post.author?.name}</span>
                        <span>{post.published_at_human}</span>
                        {post.reading_time && <span>{post.reading_time} دقائق قراءة</span>}
                        <span>{post.views_count} مشاهدة</span>
                    </div>

                    {post.featured_image_url && (
                        <img
                            src={post.featured_image_url}
                            alt={post.featured_image_alt || post.title}
                            className="img-fluid rounded-3 mb-4 w-100"
                            style={{ maxHeight: 420, objectFit: 'cover' }}
                        />
                    )}

                    {post.excerpt && <p className="lead text-secondary">{post.excerpt}</p>}

                    <div
                        className="blog-content bg-white rounded-3 p-4 shadow-sm"
                        dangerouslySetInnerHTML={{ __html: post.content || '' }}
                    />

                    {post.tags?.length > 0 && (
                        <div className="d-flex flex-wrap gap-2 mt-4">
                            {post.tags.map((tag) => (
                                <Link
                                    key={tag.id}
                                    href={route('blog.tag', tag.slug)}
                                    className="badge rounded-pill text-bg-light border text-decoration-none"
                                >
                                    #{tag.name}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </article>

            {related.length > 0 && (
                <section className="mt-5">
                    <h2 className="h4 mb-3">مقالات ذات صلة</h2>
                    <div className="row g-3">
                        {related.map((item) => (
                            <div className="col-md-4" key={item.id}>
                                <Link
                                    href={route('blog.show', item.slug)}
                                    className="card h-100 text-decoration-none text-dark border-0 shadow-sm"
                                >
                                    <div className="card-body">
                                        <h3 className="h6">{item.title}</h3>
                                        <p className="small text-muted mb-0">{item.excerpt}</p>
                                    </div>
                                </Link>
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </BlogLayout>
    );
}
