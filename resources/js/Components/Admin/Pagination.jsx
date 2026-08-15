import { router } from '@inertiajs/react';

/**
 * Pagination footer for Laravel paginators.
 *
 * Extracted because every list page in the app had hand-rolled this same block; the
 * observability screens use it rather than adding five more copies.
 *
 * Expects a paginator payload with `links`, `from`, `to` and `total`.
 */
export default function Pagination({ paginator, preserveScroll = true }) {
    if (!paginator?.links || paginator.links.length <= 3) {
        return null;
    }

    return (
        <div className="admin-table-footer">
            <div className="admin-table-meta">
                عرض {paginator.from} إلى {paginator.to} من أصل {paginator.total}
            </div>
            <div className="d-flex gap-2 flex-wrap">
                {paginator.links.map((link, index) => (
                    <button
                        key={index}
                        type="button"
                        className={`admin-btn ${link.active ? 'admin-btn-primary' : 'admin-btn-ghost'}`}
                        disabled={!link.url}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        onClick={() =>
                            link.url && router.get(link.url, {}, { preserveScroll, preserveState: true })
                        }
                    />
                ))}
            </div>
        </div>
    );
}
