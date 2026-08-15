import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export default function MediaPicker({
    open,
    onClose,
    onSelect,
    multiple = false,
    title = 'اختيار من مكتبة الوسائط',
}) {
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [selected, setSelected] = useState([]);
    const [error, setError] = useState('');
    const fileRef = useRef(null);

    const load = useCallback(
        async (page = 1, searchValue = search) => {
            setLoading(true);
            setError('');
            try {
                const params = new URLSearchParams({ page: String(page) });
                if (searchValue) {
                    params.set('search', searchValue);
                }
                const res = await fetch(`${route('admin.media.picker')}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    throw new Error('تعذر تحميل المكتبة.');
                }
                const json = await res.json();
                const pageData = json.data || {};
                setItems(pageData.data || []);
                setMeta(pageData.meta || { current_page: 1, last_page: 1, total: 0 });
            } catch (e) {
                setError(e.message || 'خطأ غير متوقع');
            } finally {
                setLoading(false);
            }
        },
        [search],
    );

    useEffect(() => {
        if (open) {
            setSelected([]);
            load(1);
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const toggle = (item) => {
        if (multiple) {
            setSelected((prev) =>
                prev.some((x) => x.id === item.id)
                    ? prev.filter((x) => x.id !== item.id)
                    : [...prev, item],
            );
            return;
        }
        setSelected([item]);
    };

    const confirm = () => {
        if (selected.length === 0) {
            return;
        }
        onSelect?.(multiple ? selected : selected[0]);
        onClose?.();
    };

    const uploadFiles = async (files) => {
        if (!files?.length) {
            return;
        }
        setUploading(true);
        setError('');
        try {
            for (const file of files) {
                const body = new FormData();
                body.append('file', file);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const res = await fetch(route('admin.media.store'), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                    },
                    credentials: 'same-origin',
                    body,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(json.message || 'فشل الرفع');
                }
            }
            await load(1);
            router.reload({ only: ['media', 'flash'], preserveScroll: true });
        } catch (e) {
            setError(e.message || 'فشل الرفع');
        } finally {
            setUploading(false);
            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    };

    if (!open) {
        return null;
    }

    return (
        <div className="media-picker-overlay" role="dialog" aria-modal="true">
            <button type="button" className="media-picker-backdrop" onClick={onClose} aria-label="إغلاق" />
            <div className="media-picker-dialog">
                <div className="media-picker-header">
                    <h3>{title}</h3>
                    <button type="button" className="admin-btn admin-btn-ghost admin-btn-sm" onClick={onClose}>
                        إغلاق
                    </button>
                </div>

                <div className="media-picker-toolbar">
                    <input
                        type="search"
                        className="form-control"
                        placeholder="بحث..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                load(1, search);
                            }
                        }}
                    />
                    <button
                        type="button"
                        className="admin-btn admin-btn-ghost"
                        onClick={() => load(1, search)}
                        disabled={loading}
                    >
                        بحث
                    </button>
                    <button
                        type="button"
                        className="admin-btn admin-btn-primary"
                        disabled={uploading}
                        onClick={() => fileRef.current?.click()}
                    >
                        {uploading ? 'جاري الرفع...' : 'رفع جديد'}
                    </button>
                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/*"
                        multiple
                        hidden
                        onChange={(e) => uploadFiles(e.target.files)}
                    />
                </div>

                {error && <div className="admin-alert admin-alert-error">{error}</div>}

                <div className="media-grid media-grid--picker">
                    {loading && <div className="media-grid-empty">جاري التحميل...</div>}
                    {!loading && items.length === 0 && (
                        <div className="media-grid-empty">لا توجد صور.</div>
                    )}
                    {items.map((item) => {
                        const isSelected = selected.some((x) => x.id === item.id);
                        return (
                            <button
                                type="button"
                                key={item.id}
                                className={`media-card${isSelected ? ' is-selected' : ''}`}
                                onClick={() => toggle(item)}
                            >
                                <img src={item.thumb_url || item.url} alt={item.alt || item.filename} />
                                <span>{item.title || item.filename}</span>
                            </button>
                        );
                    })}
                </div>

                <div className="media-picker-footer">
                    <div className="text-muted small">
                        الصفحة {meta.current_page} / {meta.last_page} — {meta.total} ملف
                    </div>
                    <div className="d-flex gap-2">
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost admin-btn-sm"
                            disabled={meta.current_page <= 1 || loading}
                            onClick={() => load(meta.current_page - 1)}
                        >
                            السابق
                        </button>
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost admin-btn-sm"
                            disabled={meta.current_page >= meta.last_page || loading}
                            onClick={() => load(meta.current_page + 1)}
                        >
                            التالي
                        </button>
                        <button
                            type="button"
                            className="admin-btn admin-btn-primary"
                            disabled={selected.length === 0}
                            onClick={confirm}
                        >
                            اختيار
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
