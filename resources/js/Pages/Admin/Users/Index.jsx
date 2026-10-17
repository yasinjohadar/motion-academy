import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import EnrollStudentModal from '@/Components/Admin/EnrollStudentModal';
import ImpersonationLinkModal from '@/Components/Admin/ImpersonationLinkModal';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

export default function Index({ users, filters, flash }) {
    const { auth } = usePage().props;
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [copiedEmail, setCopiedEmail] = useState(null);
    const [userToDelete, setUserToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [userToOpen, setUserToOpen] = useState(null);
    const closeImpersonation = useCallback(() => setUserToOpen(null), []);
    const [userToEnroll, setUserToEnroll] = useState(null);
    const closeEnroll = useCallback(() => setUserToEnroll(null), []);

    useEffect(() => {
        const current = filters.query || '';
        if (query === current) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(route('admin.users.index'), query ? { query } : {}, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [query, filters.query]);

    const copyEmail = async (email) => {
        try {
            await navigator.clipboard.writeText(email);
            setCopiedEmail(email);
            setTimeout(() => setCopiedEmail(null), 1500);
        } catch {
            const input = document.createElement('input');
            input.value = email;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            setCopiedEmail(email);
            setTimeout(() => setCopiedEmail(null), 1500);
        }
    };

    const confirmDelete = () => {
        if (!userToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.users.destroy', userToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setUserToDelete(null);
            },
        });
    };

    return (
        <AdminUiPage
            title="كافة المستخدمين"
            subtitle="إدارة حسابات النظام مع الاسم العربي والإنكليزي ورقم الهاتف"
            flash={flash}
            actions={
                can('admin.users.create') ? (
                    <Link href={route('admin.users.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-user-add-line" />
                        إنشاء مستخدم جديد
                    </Link>
                ) : null
            }
        >
            <EnrollStudentModal
                key={`enroll-${userToEnroll?.id ?? 'closed'}`}
                user={userToEnroll}
                onClose={closeEnroll}
            />

            <ImpersonationLinkModal
                key={userToOpen?.id ?? 'closed'}
                user={userToOpen}
                onClose={closeImpersonation}
            />

            <DeleteConfirmModal
                show={Boolean(userToDelete)}
                message={
                    userToDelete ? `هل أنت متأكد من رغبتك في حذف المستخدم «${userToDelete.name_ar}»؟` : ''
                }
                processing={deleting}
                onConfirm={confirmDelete}
                onCancel={() => !deleting && setUserToDelete(null)}
            />

            <AdminPanel title="قائمة المستخدمين" icon="ri-group-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالاسم أو الإيميل أو الهاتف..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        {query && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-danger"
                                onClick={() => setQuery('')}
                            >
                                <i className="ri-close-line" />
                                مسح
                            </button>
                        )}
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم بالعربي</th>
                                    <th>الاسم بالإنكليزي</th>
                                    <th>البريد</th>
                                    <th>الهاتف</th>
                                    <th>نوع الحساب</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.length === 0 && (
                                    <TableEmptyRow colSpan={8} icon="ri-user-search-line">
                                        لا توجد نتائج
                                    </TableEmptyRow>
                                )}

                                {users.data.map((user, index) => (
                                    <tr key={user.id}>
                                        <th scope="row" className="row-number">
                                            {(users.from || 0) + index}
                                        </th>
                                        <td>
                                            <Link
                                                href={route('admin.users.show', user.id)}
                                                className="dashboard-student-cell ud-name-link"
                                                title="عرض تفاصيل المستخدم"
                                            >
                                                <span className="dashboard-row-avatar">
                                                    {(user.name_ar || '?').charAt(0)}
                                                </span>
                                                <div>
                                                    <h6>{user.name_ar}</h6>
                                                </div>
                                            </Link>
                                        </td>
                                        <td>
                                            <strong className="admin-user-link d-block">
                                                {user.name_en}
                                            </strong>
                                        </td>
                                        <td>
                                            <div className="admin-email-cell">
                                                <a href={`mailto:${user.email}`} className="admin-email-link">
                                                    {user.email}
                                                </a>
                                                <button
                                                    type="button"
                                                    className={`admin-copy-btn${copiedEmail === user.email ? ' copied' : ''}`}
                                                    title="نسخ البريد"
                                                    onClick={() => copyEmail(user.email)}
                                                >
                                                    <i
                                                        className={
                                                            copiedEmail === user.email
                                                                ? 'ri-check-line'
                                                                : 'ri-file-copy-line'
                                                        }
                                                    />
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            {user.phone ? (
                                                <span className="admin-phone-cell">
                                                    <a
                                                        href={`https://wa.me/${user.phone.replace(/[^0-9]/g, '')}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="admin-whatsapp-link"
                                                        title="واتساب"
                                                    >
                                                        <i className="fab fa-whatsapp" />
                                                    </a>
                                                    <span>{user.phone}</span>
                                                </span>
                                            ) : (
                                                <span className="text-muted">—</span>
                                            )}
                                        </td>
                                        <td>
                                            {user.account_type === 'admin' ? (
                                                <span className="admin-badge admin-badge-accent">
                                                    <i className="ri-shield-user-line" /> أدمن
                                                </span>
                                            ) : (
                                                <span className="admin-badge admin-badge-muted">
                                                    <i className="ri-graduation-cap-line" /> طالب
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <span className="text-muted">{user.created_at}</span>
                                        </td>
                                        <td>
                                            <div className="admin-action-group">
                                                {can('admin.enrollments.create') &&
                                                    user.account_type === 'student' && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-add"
                                                            title="إضافة إلى دورة"
                                                            onClick={() => setUserToEnroll(user)}
                                                        >
                                                            <i className="ri-user-add-line" />
                                                        </button>
                                                    )}
                                                {can('admin.users.impersonate') &&
                                                    user.account_type === 'student' && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-login"
                                                            title="الدخول إلى حساب الطالب"
                                                            onClick={() => setUserToOpen(user)}
                                                        >
                                                            <i className="ri-login-circle-line" />
                                                        </button>
                                                    )}
                                                {can('admin.users.edit') && (
                                                    <Link
                                                        href={route('admin.users.edit', user.id)}
                                                        className="admin-action-btn admin-action-edit"
                                                        title="تعديل"
                                                    >
                                                        <i className="ri-edit-line" />
                                                    </Link>
                                                )}
                                                {can('admin.users.delete') && auth.user.id !== user.id && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-delete"
                                                        title="حذف"
                                                        onClick={() => setUserToDelete(user)}
                                                    >
                                                        <i className="ri-delete-bin-line" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {users.links?.length > 3 && (
                    <div className="admin-table-footer">
                        <div className="admin-table-meta">
                            عرض {users.from} إلى {users.to} من أصل {users.total}
                        </div>
                        <div className="d-flex gap-2 flex-wrap">
                            {users.links.map((link, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    className={`admin-btn ${link.active ? 'admin-btn-primary' : 'admin-btn-ghost'}`}
                                    disabled={!link.url}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    onClick={() => link.url && router.get(link.url)}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </AdminPanel>
        </AdminUiPage>
    );
}
