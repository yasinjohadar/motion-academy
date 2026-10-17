import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

export default function Index({ media, filters = {}, flash }) {
    const { can } = useCan();
    const items = media?.data || [];
    const meta = media?.meta || {};
    const links = media?.links || [];
    const fileRef = useRef(null);
    const [selectedId, setSelectedId] = useState(items[0]?.id || null);
    const [dragOver, setDragOver] = useState(false);

    const selected = useMemo(() => items.find((item) => item.id === selectedId) || null, [items, selectedId]);

    const metaForm = useForm({
        title: selected?.title || '',
        alt: selected?.alt || '',
        caption: selected?.caption || '',
        description: selected?.description || '',
    });

    const selectItem = (item) => {
        setSelectedId(item.id);
        metaForm.setData({
            title: item.title || '',
            alt: item.alt || '',
            caption: item.caption || '',
            description: item.description || '',
        });
    };

    const uploadFiles = (fileList) => {
        const files = Array.from(fileList || []);
        if (!files.length || !can('admin.media.create')) {
            return;
        }

        files.reduce((chain, file) => {
            return chain.then(
                () =>
                    new Promise((resolve) => {
                        router.post(
                            route('admin.media.store'),
                            { file },
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onFinish: () => resolve(),
                            },
                        );
                    }),
            );
        }, Promise.resolve());
    };

    const saveMeta = (e) => {
        e.preventDefault();
        if (!selected?.can?.update) {
            return;
        }
        metaForm.put(route('admin.media.update', selected.id), { preserveScroll: true });
    };

    const removeSelected = () => {
        if (!selected?.can?.delete) {
            return;
        }
        if (!window.confirm(`حذف «${selected.filename}»؟`)) {
            return;
        }
        router.delete(route('admin.media.destroy', selected.id), {
            preserveScroll: true,
            onSuccess: () => setSelectedId(null),
        });
    };

    const copyUrl = async () => {
        if (!selected?.url) {
            return;
        }
        try {
            await navigator.clipboard.writeText(selected.url);
        } catch {
            // ignore
        }
    };

    return (
        <AdminUiPage
            title="مكتبة الوسائط"
            eyebrow="Media Studio"
            eyebrowIcon="ri-gallery-line"
            subtitle="ارفع الصور، عالجها تلقائياً (WebP + مقاسات)، وأعد استخدامها في كل النظام."
            chips={[{ icon: 'ri-image-line', label: 'الملفات', value: meta.total ?? items.length }]}
            flash={flash}
            actions={
                can('admin.media.create') ? (
                    <button
                        type="button"
                        className="admin-btn admin-btn-primary"
                        onClick={() => fileRef.current?.click()}
                    >
                        <i className="ri-upload-2-line" />
                        رفع صور
                    </button>
                ) : null
            }
        >
            <input
                ref={fileRef}
                type="file"
                accept="image/*"
                multiple
                hidden
                onChange={(e) => uploadFiles(e.target.files)}
            />

            <div className="row g-3">
                <div className="col-lg-8">
                    <AdminPanel title="المكتبة" icon="ri-grid-line">
                        <form
                            className="media-studio-filters mb-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const data = new FormData(e.currentTarget);
                                router.get(
                                    route('admin.media.index'),
                                    {
                                        search: data.get('search') || '',
                                        mime: data.get('mime') || '',
                                    },
                                    { preserveState: true },
                                );
                            }}
                        >
                            <input
                                name="search"
                                className="form-control"
                                defaultValue={filters.search || ''}
                                placeholder="بحث بالاسم أو العنوان..."
                            />
                            <select name="mime" className="form-select" defaultValue={filters.mime || ''}>
                                <option value="">كل الأنواع</option>
                                <option value="image/webp">WebP</option>
                                <option value="image/jpeg">JPEG</option>
                                <option value="image/png">PNG</option>
                                <option value="image/gif">GIF</option>
                                <option value="image/svg+xml">SVG</option>
                            </select>
                            <button type="submit" className="admin-btn admin-btn-ghost">
                                تصفية
                            </button>
                        </form>

                        {can('admin.media.create') && (
                            <div
                                className={`media-dropzone${dragOver ? ' is-dragover' : ''}`}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragOver(true);
                                }}
                                onDragLeave={() => setDragOver(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragOver(false);
                                    uploadFiles(e.dataTransfer.files);
                                }}
                            >
                                اسحب الصور وأفلتها هنا للرفع والمعالجة التلقائية
                            </div>
                        )}

                        <div className="media-grid mt-3">
                            {items.length === 0 && (
                                <div className="media-grid-empty">لا توجد وسائط بعد. ابدأ بالرفع.</div>
                            )}
                            {items.map((item) => (
                                <button
                                    type="button"
                                    key={item.id}
                                    className={`media-card${selectedId === item.id ? ' is-selected' : ''}`}
                                    onClick={() => selectItem(item)}
                                >
                                    <img src={item.thumb_url || item.url} alt={item.alt || item.filename} />
                                    <span>{item.title || item.filename}</span>
                                </button>
                            ))}
                        </div>

                        {links.length > 3 && (
                            <div className="d-flex flex-wrap gap-2 mt-3">
                                {links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        className={`admin-btn admin-btn-sm ${link.active ? 'admin-btn-primary' : 'admin-btn-ghost'}`}
                                        disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        onClick={() =>
                                            link.url && router.get(link.url, {}, { preserveState: true })
                                        }
                                    />
                                ))}
                            </div>
                        )}
                    </AdminPanel>
                </div>

                <div className="col-lg-4">
                    <AdminPanel title="التفاصيل" icon="ri-information-line">
                        {!selected && <p className="text-muted mb-0">اختر صورة لعرض تفاصيلها.</p>}
                        {selected && (
                            <>
                                <div className="media-detail-preview mb-3">
                                    <img src={selected.url} alt={selected.alt || selected.filename} />
                                </div>
                                <div className="small text-muted mb-3">
                                    <div>{selected.filename}</div>
                                    <div>
                                        {selected.width || '—'}×{selected.height || '—'} ·{' '}
                                        {selected.human_size} · {selected.mime}
                                    </div>
                                    <div dir="ltr" className="text-break">
                                        {selected.url}
                                    </div>
                                </div>

                                <form onSubmit={saveMeta} className="d-flex flex-column gap-2">
                                    <div>
                                        <label className="form-label">العنوان</label>
                                        <input
                                            className="form-control"
                                            value={metaForm.data.title}
                                            onChange={(e) => metaForm.setData('title', e.target.value)}
                                            disabled={!selected?.can?.update}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">النص البديل</label>
                                        <input
                                            className="form-control"
                                            value={metaForm.data.alt}
                                            onChange={(e) => metaForm.setData('alt', e.target.value)}
                                            disabled={!selected?.can?.update}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">التعليق</label>
                                        <input
                                            className="form-control"
                                            value={metaForm.data.caption}
                                            onChange={(e) => metaForm.setData('caption', e.target.value)}
                                            disabled={!selected?.can?.update}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">الوصف</label>
                                        <textarea
                                            className="form-control"
                                            rows={3}
                                            value={metaForm.data.description}
                                            onChange={(e) => metaForm.setData('description', e.target.value)}
                                            disabled={!selected?.can?.update}
                                        />
                                    </div>

                                    <div className="admin-actions">
                                        {selected?.can?.update && (
                                            <button
                                                type="submit"
                                                className="admin-btn admin-btn-primary"
                                                disabled={metaForm.processing}
                                            >
                                                حفظ
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className="admin-btn admin-btn-ghost"
                                            onClick={copyUrl}
                                        >
                                            نسخ الرابط
                                        </button>
                                        {selected?.can?.delete && (
                                            <button
                                                type="button"
                                                className="admin-btn admin-btn-danger"
                                                onClick={removeSelected}
                                            >
                                                حذف
                                            </button>
                                        )}
                                    </div>
                                </form>
                            </>
                        )}
                    </AdminPanel>
                </div>
            </div>
        </AdminUiPage>
    );
}
