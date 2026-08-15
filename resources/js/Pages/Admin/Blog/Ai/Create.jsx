import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Create({ categories = [], tags = [], flash }) {
    const { errors: pageErrors } = usePage().props;
    const [generating, setGenerating] = useState(false);
    const [saving, setSaving] = useState(false);

    const generateForm = useForm({
        topic: '',
        content_length: 'medium',
        tone: 'professional',
        language: 'ar',
        category_id: '',
        generate_seo: true,
        generate_og: true,
        generate_twitter: true,
        generate_schema: true,
    });

    const draftForm = useForm({
        title: '',
        slug: '',
        excerpt: '',
        content: '',
        blog_category_id: '',
        tag_ids: [],
        status: 'draft',
        language: 'ar',
        meta_title: '',
        meta_description: '',
        meta_keywords: '',
        canonical_url: '',
        focus_keyword: '',
        focus_keyword_synonyms: '',
        robots_meta: 'index,follow',
        is_indexable: true,
        is_followable: true,
        og_title: '',
        og_description: '',
        og_image: '',
        og_type: 'article',
        og_locale: 'ar_SA',
        twitter_card: 'summary_large_image',
        twitter_title: '',
        twitter_description: '',
        twitter_image: '',
        twitter_creator: '',
        schema_type: 'BlogPosting',
        schema_headline: '',
        schema_description: '',
        schema_image: '',
        schema_author_name: '',
        schema_author_url: '',
        is_featured: false,
        reading_time: '',
    });

    const generateErrors = { ...pageErrors, ...generateForm.errors };
    const draftErrors = { ...pageErrors, ...draftForm.errors };
    const aiDraft = flash?.ai_draft;

    useEffect(() => {
        if (!aiDraft) {
            return;
        }

        draftForm.setData({
            ...draftForm.data,
            title: aiDraft.title || '',
            slug: aiDraft.slug || '',
            excerpt: aiDraft.excerpt || '',
            content: aiDraft.content || '',
            blog_category_id: aiDraft.blog_category_id || generateForm.data.category_id || '',
            language: aiDraft.language || generateForm.data.language || 'ar',
            meta_title: aiDraft.meta_title || '',
            meta_description: aiDraft.meta_description || '',
            meta_keywords: aiDraft.meta_keywords || '',
            canonical_url: aiDraft.canonical_url || '',
            focus_keyword: aiDraft.focus_keyword || '',
            focus_keyword_synonyms: aiDraft.focus_keyword_synonyms || '',
            robots_meta: aiDraft.robots_meta || 'index,follow',
            is_indexable: aiDraft.is_indexable ?? true,
            is_followable: aiDraft.is_followable ?? true,
            og_title: aiDraft.og_title || '',
            og_description: aiDraft.og_description || '',
            og_image: aiDraft.og_image || '',
            og_type: aiDraft.og_type || 'article',
            og_locale: aiDraft.og_locale || 'ar_SA',
            twitter_card: aiDraft.twitter_card || 'summary_large_image',
            twitter_title: aiDraft.twitter_title || '',
            twitter_description: aiDraft.twitter_description || '',
            twitter_image: aiDraft.twitter_image || '',
            twitter_creator: aiDraft.twitter_creator || '',
            schema_type: aiDraft.schema_type || 'BlogPosting',
            schema_headline: aiDraft.schema_headline || '',
            schema_description: aiDraft.schema_description || '',
            schema_image: aiDraft.schema_image || '',
            schema_author_name: aiDraft.schema_author_name || '',
            schema_author_url: aiDraft.schema_author_url || '',
            reading_time: aiDraft.reading_time || '',
        });
    }, [aiDraft]);

    const toggleTag = (tagId) => {
        const current = draftForm.data.tag_ids || [];
        if (current.includes(tagId)) {
            draftForm.setData(
                'tag_ids',
                current.filter((id) => id !== tagId),
            );
        } else {
            draftForm.setData('tag_ids', [...current, tagId]);
        }
    };

    const generate = (e) => {
        e.preventDefault();
        setGenerating(true);
        generateForm.post(route('admin.blog-ai.generate'), {
            preserveScroll: true,
            onFinish: () => setGenerating(false),
        });
    };

    const saveDraft = (e) => {
        e.preventDefault();
        setSaving(true);
        draftForm.post(route('admin.blog-ai.store'), {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AdminUiPage
            title="توليد بالذكاء الاصطناعي"
            subtitle="أنشئ مسودة مقال ثم راجعها واحفظها كمسودة"
            flash={flash}
            actions={
                <Link href={route('admin.blog-posts.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للمقالات
                </Link>
            }
        >
            <div className="row g-4">
                <div className="col-lg-5">
                    <AdminPanel title="خيارات التوليد" icon="ri-robot-2-line">
                        <form onSubmit={generate}>
                                    <div className="row g-3">
                                        <div className="col-12">
                                            <label className="form-label">الموضوع</label>
                                            <textarea
                                                rows={4}
                                                className={`form-control${generateErrors.topic ? ' is-invalid' : ''}`}
                                                value={generateForm.data.topic}
                                                onChange={(e) =>
                                                    generateForm.setData('topic', e.target.value)
                                                }
                                                placeholder="اكتب موضوع المقال أو الفكرة الرئيسية..."
                                            />
                                            {generateErrors.topic && (
                                                <div className="invalid-feedback">
                                                    {generateErrors.topic}
                                                </div>
                                            )}
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">طول المحتوى</label>
                                            <select
                                                className="form-select"
                                                value={generateForm.data.content_length}
                                                onChange={(e) =>
                                                    generateForm.setData(
                                                        'content_length',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="short">قصير</option>
                                                <option value="medium">متوسط</option>
                                                <option value="long">طويل</option>
                                            </select>
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">النبرة</label>
                                            <select
                                                className="form-select"
                                                value={generateForm.data.tone}
                                                onChange={(e) =>
                                                    generateForm.setData('tone', e.target.value)
                                                }
                                            >
                                                <option value="professional">احترافية</option>
                                                <option value="friendly">ودية</option>
                                                <option value="technical">تقنية</option>
                                                <option value="casual">عادية</option>
                                                <option value="formal">رسمية</option>
                                            </select>
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">اللغة</label>
                                            <select
                                                className="form-select"
                                                value={generateForm.data.language}
                                                onChange={(e) =>
                                                    generateForm.setData('language', e.target.value)
                                                }
                                            >
                                                <option value="ar">العربية</option>
                                                <option value="en">English</option>
                                            </select>
                                        </div>
                                        <div className="col-md-6">
                                            <label className="form-label">التصنيف</label>
                                            <select
                                                className="form-select"
                                                value={generateForm.data.category_id}
                                                onChange={(e) =>
                                                    generateForm.setData(
                                                        'category_id',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">بدون</option>
                                                {categories.map((category) => (
                                                    <option key={category.id} value={category.id}>
                                                        {category.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="col-12 d-flex flex-column gap-2">
                                            <label className="d-inline-flex align-items-center gap-2 mb-0">
                                                <input
                                                    type="checkbox"
                                                    checked={generateForm.data.generate_seo}
                                                    onChange={(e) =>
                                                        generateForm.setData(
                                                            'generate_seo',
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                توليد SEO
                                            </label>
                                            <label className="d-inline-flex align-items-center gap-2 mb-0">
                                                <input
                                                    type="checkbox"
                                                    checked={generateForm.data.generate_og}
                                                    onChange={(e) =>
                                                        generateForm.setData(
                                                            'generate_og',
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                توليد Open Graph
                                            </label>
                                            <label className="d-inline-flex align-items-center gap-2 mb-0">
                                                <input
                                                    type="checkbox"
                                                    checked={generateForm.data.generate_twitter}
                                                    onChange={(e) =>
                                                        generateForm.setData(
                                                            'generate_twitter',
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                توليد تويتر
                                            </label>
                                            <label className="d-inline-flex align-items-center gap-2 mb-0">
                                                <input
                                                    type="checkbox"
                                                    checked={generateForm.data.generate_schema}
                                                    onChange={(e) =>
                                                        generateForm.setData(
                                                            'generate_schema',
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                توليد Schema
                                            </label>
                                        </div>
                                        <div className="col-12">
                                            <button
                                                type="submit"
                                                className="admin-btn admin-btn-primary w-100"
                                                disabled={generating || generateForm.processing}
                                            >
                                                <i className="ri-robot-2-line" />
                                                {generating ? 'جاري التوليد...' : 'توليد المسودة'}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                    </AdminPanel>
                </div>

                <div className="col-lg-7">
                    <AdminPanel title="معاينة المسودة" icon="ri-file-text-line">
                                    {!aiDraft && (
                                        <div className="text-muted border rounded-3 p-4 text-center">
                                            لم يتم توليد مسودة بعد. املأ الخيارات ثم اضغط «توليد
                                            المسودة».
                                        </div>
                                    )}

                                    {aiDraft && (
                                        <>
                                            <div className="border rounded-3 p-3 mb-4 bg-light">
                                                <h4 className="mb-2">{aiDraft.title || '—'}</h4>
                                                {aiDraft.excerpt && (
                                                    <p className="text-muted mb-3">
                                                        {aiDraft.excerpt}
                                                    </p>
                                                )}
                                                <div
                                                    className="blog-ai-preview"
                                                    dangerouslySetInnerHTML={{
                                                        __html: aiDraft.content || '',
                                                    }}
                                                />
                                            </div>

                                            <form onSubmit={saveDraft}>
                                                <div className="row g-3">
                                                    <div className="col-md-8">
                                                        <label className="form-label">العنوان</label>
                                                        <input
                                                            type="text"
                                                            className={`form-control${draftErrors.title ? ' is-invalid' : ''}`}
                                                            value={draftForm.data.title}
                                                            onChange={(e) =>
                                                                draftForm.setData(
                                                                    'title',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                        {draftErrors.title && (
                                                            <div className="invalid-feedback">
                                                                {draftErrors.title}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="col-md-4">
                                                        <label className="form-label">التصنيف</label>
                                                        <select
                                                            className="form-select"
                                                            value={draftForm.data.blog_category_id}
                                                            onChange={(e) =>
                                                                draftForm.setData(
                                                                    'blog_category_id',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        >
                                                            <option value="">بدون</option>
                                                            {categories.map((category) => (
                                                                <option
                                                                    key={category.id}
                                                                    value={category.id}
                                                                >
                                                                    {category.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label">المقتطف</label>
                                                        <textarea
                                                            rows={2}
                                                            className="form-control"
                                                            value={draftForm.data.excerpt}
                                                            onChange={(e) =>
                                                                draftForm.setData(
                                                                    'excerpt',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label">المحتوى</label>
                                                        <textarea
                                                            rows={10}
                                                            className={`form-control${draftErrors.content ? ' is-invalid' : ''}`}
                                                            value={draftForm.data.content}
                                                            onChange={(e) =>
                                                                draftForm.setData(
                                                                    'content',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            dir="rtl"
                                                        />
                                                        {draftErrors.content && (
                                                            <div className="invalid-feedback">
                                                                {draftErrors.content}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="col-12">
                                                        <label className="form-label">الوسوم</label>
                                                        <div className="border rounded-3 p-3 d-flex flex-wrap gap-3">
                                                            {tags.map((tag) => (
                                                                <label
                                                                    key={tag.id}
                                                                    className="d-inline-flex align-items-center gap-2 mb-0"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={(
                                                                            draftForm.data
                                                                                .tag_ids || []
                                                                        ).includes(tag.id)}
                                                                        onChange={() =>
                                                                            toggleTag(tag.id)
                                                                        }
                                                                    />
                                                                    {tag.name}
                                                                </label>
                                                            ))}
                                                        </div>
                                                    </div>
                                                    <div className="col-12 d-flex gap-2">
                                                        <button
                                                            type="submit"
                                                            className="admin-btn admin-btn-primary"
                                                            disabled={
                                                                saving || draftForm.processing
                                                            }
                                                        >
                                                            <i className="ri-save-line" />
                                                            {saving
                                                                ? 'جاري الحفظ...'
                                                                : 'حفظ كمسودة'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="admin-btn admin-btn-ghost"
                                                            onClick={() =>
                                                                router.visit(
                                                                    route('admin.blog-ai.create'),
                                                                )
                                                            }
                                                        >
                                                            مسح المعاينة
                                                        </button>
                                                    </div>
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
