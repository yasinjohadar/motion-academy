import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import EnrollStudentModal from '@/Components/Admin/EnrollStudentModal';
import ChangePasswordModal from '@/Components/Admin/ChangePasswordModal';
import ImpersonationLinkModal from '@/Components/Admin/ImpersonationLinkModal';
import useCan from '@/hooks/useCan';
import { Link } from '@inertiajs/react';
import { useCallback, useState } from 'react';

const STATUS = {
    approved: { className: 'is-approved', icon: 'ri-checkbox-circle-line' },
    pending: { className: 'is-pending', icon: 'ri-hourglass-line' },
    rejected: { className: 'is-rejected', icon: 'ri-close-circle-line' },
};

function Verified({ ok, label }) {
    return (
        <span className={`ud-verify${ok ? ' is-ok' : ''}`}>
            <i className={ok ? 'ri-shield-check-line' : 'ri-error-warning-line'} />{' '}
            {ok ? `${label} مؤكد` : `${label} غير مؤكد`}
        </span>
    );
}

function Empty({ icon, text }) {
    return (
        <div className="ud-empty">
            <i className={icon} />
            <span>{text}</span>
        </div>
    );
}

export default function Show({
    user,
    stats,
    courses,
    requests,
    recentLessons,
    newsletter,
    security,
    credentials,
    flash,
}) {
    const { can } = useCan();
    const isStudent = user.account_type === 'student';
    const [enrollOpen, setEnrollOpen] = useState(false);
    const [impersonateOpen, setImpersonateOpen] = useState(false);
    const closeEnroll = useCallback(() => setEnrollOpen(false), []);
    const closeImpersonate = useCallback(() => setImpersonateOpen(false), []);
    const [passwordOpen, setPasswordOpen] = useState(false);
    const closePassword = useCallback(() => setPasswordOpen(false), []);
    const [copied, setCopied] = useState('');

    const copy = (value, key) => {
        navigator.clipboard?.writeText(value);
        setCopied(key);
        setTimeout(() => setCopied(''), 1800);
    };

    const name = user.name_ar || user.name || user.name_en;

    return (
        <AdminUiPage
            title={name}
            eyebrow="تفاصيل المستخدم"
            eyebrowIcon="ri-user-3-line"
            subtitle={user.name_en && user.name_en !== name ? user.name_en : user.email}
            flash={flash}
            chips={[
                {
                    label: 'نوع الحساب',
                    value: isStudent ? 'طالب' : 'أدمن',
                    icon: isStudent ? 'ri-graduation-cap-line' : 'ri-shield-user-line',
                },
                { label: 'عضو منذ', value: user.created_human, icon: 'ri-calendar-line' },
                { label: 'آخر دخول', value: user.last_login || 'لم يدخل بعد', icon: 'ri-login-circle-line' },
            ]}
            actions={
                <>
                    <Link href={route('admin.users.index')} className="admin-btn admin-btn-ghost">
                        <i className="ri-arrow-right-line" /> المستخدمون
                    </Link>
                    {can('admin.users.edit') && (
                        <Link href={route('admin.users.edit', user.id)} className="admin-btn admin-btn-ghost">
                            <i className="ri-edit-line" /> تعديل
                        </Link>
                    )}
                    {isStudent && credentials && can('admin.users.edit') && (
                        <button
                            type="button"
                            className="admin-btn ud-btn-password"
                            onClick={() => setPasswordOpen(true)}
                        >
                            <i className="ri-key-2-line" /> تغيير كلمة المرور
                        </button>
                    )}
                    {isStudent && can('admin.enrollments.create') && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-primary"
                            onClick={() => setEnrollOpen(true)}
                        >
                            <i className="ri-user-add-line" /> إضافة إلى دورة
                        </button>
                    )}
                    {isStudent && can('admin.users.impersonate') && (
                        <button
                            type="button"
                            className="admin-btn ud-btn-login"
                            onClick={() => setImpersonateOpen(true)}
                        >
                            <i className="ri-login-circle-line" /> الدخول إلى حسابه
                        </button>
                    )}
                </>
            }
        >
            {enrollOpen && <EnrollStudentModal user={user} onClose={closeEnroll} />}
            {impersonateOpen && <ImpersonationLinkModal user={user} onClose={closeImpersonate} />}
            {passwordOpen && (
                <ChangePasswordModal user={user} credentials={credentials} onClose={closePassword} />
            )}

            <div className="ud-layout">
                {/* ---------- Identity ---------- */}
                <aside className="ud-card">
                    <div className="ud-card-top">
                        <span className="ud-avatar">{(name || '?').charAt(0)}</span>
                        <h2 className="ud-name">{name}</h2>
                        {user.name_en && <p className="ud-name-en">{user.name_en}</p>}
                        <span className={`ud-type ${isStudent ? 'is-student' : 'is-admin'}`}>
                            <i className={isStudent ? 'ri-graduation-cap-line' : 'ri-shield-user-line'} />
                            {isStudent ? 'طالب' : 'أدمن'}
                        </span>
                    </div>

                    <ul className="ud-facts">
                        <li>
                            <span className="ud-fact-icon">
                                <i className="ri-mail-line" />
                            </span>
                            <span className="ud-fact-body">
                                <small>البريد الإلكتروني</small>
                                <a href={`mailto:${user.email}`} dir="ltr">
                                    {user.email}
                                </a>
                                <Verified ok={user.email_verified} label="البريد" />
                            </span>
                            <button
                                type="button"
                                className="ud-copy"
                                title="نسخ"
                                onClick={() => copy(user.email, 'email')}
                            >
                                <i className={copied === 'email' ? 'ri-check-line' : 'ri-file-copy-line'} />
                            </button>
                        </li>
                        <li>
                            <span className="ud-fact-icon is-wa">
                                <i className="ri-whatsapp-line" />
                            </span>
                            <span className="ud-fact-body">
                                <small>رقم واتساب</small>
                                {user.phone ? (
                                    <>
                                        {user.phone_e164 ? (
                                            <a
                                                href={`https://wa.me/${user.phone_e164}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                dir="ltr"
                                            >
                                                {user.phone}
                                            </a>
                                        ) : (
                                            <span dir="ltr">{user.phone}</span>
                                        )}
                                        <Verified ok={user.phone_verified} label="الرقم" />
                                    </>
                                ) : (
                                    <span className="text-muted">لا يوجد رقم</span>
                                )}
                            </span>
                            {user.phone && (
                                <button
                                    type="button"
                                    className="ud-copy"
                                    title="نسخ"
                                    onClick={() => copy(user.phone, 'phone')}
                                >
                                    <i
                                        className={copied === 'phone' ? 'ri-check-line' : 'ri-file-copy-line'}
                                    />
                                </button>
                            )}
                        </li>
                        <li>
                            <span className="ud-fact-icon">
                                <i className="ri-calendar-check-line" />
                            </span>
                            <span className="ud-fact-body">
                                <small>تاريخ التسجيل</small>
                                <span className="en-text">{user.created_at}</span>
                            </span>
                        </li>
                        <li>
                            <span className="ud-fact-icon">
                                <i className="ri-login-box-line" />
                            </span>
                            <span className="ud-fact-body">
                                <small>آخر دخول</small>
                                {user.last_login_at ? (
                                    <>
                                        <span className="en-text">{user.last_login_at}</span>
                                        {user.last_login_ip && (
                                            <span className="ud-muted en-text" dir="ltr">
                                                IP {user.last_login_ip}
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <span className="text-muted">لم يدخل بعد</span>
                                )}
                            </span>
                        </li>
                        <li>
                            <span className="ud-fact-icon">
                                <i className="ri-mail-send-line" />
                            </span>
                            <span className="ud-fact-body">
                                <small>القائمة البريدية</small>
                                {newsletter ? (
                                    <span>
                                        {newsletter.label}
                                        {newsletter.since && (
                                            <span className="ud-muted en-text"> · {newsletter.since}</span>
                                        )}
                                    </span>
                                ) : (
                                    <span className="text-muted">غير مشترك</span>
                                )}
                            </span>
                        </li>
                    </ul>

                    {user.roles.length > 0 && (
                        <div className="ud-roles">
                            {user.roles.map((role) => (
                                <span key={role} className="ud-role">
                                    {role}
                                </span>
                            ))}
                        </div>
                    )}
                </aside>

                <div className="ud-main">
                    {/* ---------- Numbers ---------- */}
                    <div className="ud-stats">
                        {[
                            {
                                icon: 'ri-book-open-line',
                                value: stats.courses,
                                label: 'دورة منضم إليها',
                                tone: '#02ad8f',
                            },
                            {
                                icon: 'ri-trophy-line',
                                value: stats.completed,
                                label: 'دورة مكتملة',
                                tone: '#f59e0b',
                            },
                            {
                                icon: 'ri-checkbox-circle-line',
                                value: stats.lessons,
                                label: 'درس أنهاه',
                                tone: '#33a4da',
                            },
                            {
                                icon: 'ri-time-line',
                                value: stats.minutes >= 60 ? (stats.minutes / 60).toFixed(1) : stats.minutes,
                                label: stats.minutes >= 60 ? 'ساعة تعلّم' : 'دقيقة تعلّم',
                                tone: '#6366f1',
                            },
                            {
                                icon: 'ri-hourglass-line',
                                value: stats.pending,
                                label: 'طلب معلّق',
                                tone: '#d97706',
                            },
                            {
                                icon: 'ri-pulse-line',
                                value: stats.last_activity || '—',
                                label: 'آخر نشاط',
                                tone: '#0ea5e9',
                                small: true,
                            },
                        ].map((stat) => (
                            <div key={stat.label} className="ud-stat" style={{ '--tone': stat.tone }}>
                                <span className="ud-stat-icon">
                                    <i className={stat.icon} />
                                </span>
                                <strong className={stat.small ? 'is-small' : 'en-text'}>{stat.value}</strong>
                                <span>{stat.label}</span>
                            </div>
                        ))}
                    </div>

                    {/* ---------- Courses ---------- */}
                    <AdminPanel title={`الدورات (${courses.length})`} icon="ri-book-open-line">
                        {courses.length === 0 ? (
                            <Empty icon="ri-book-open-line" text="غير منضم إلى أي دورة بعد." />
                        ) : (
                            <div className="ud-courses">
                                {courses.map((course) => (
                                    <article
                                        key={course.course_id}
                                        className="ud-course"
                                        style={{ '--c': course.color }}
                                    >
                                        <div className="ud-course-cover">
                                            {course.thumbnail ? (
                                                <img src={course.thumbnail} alt="" loading="lazy" />
                                            ) : (
                                                <i className="ri-film-line" />
                                            )}
                                            {course.completed_at && (
                                                <span className="ud-course-done">
                                                    <i className="ri-trophy-line" /> مكتملة
                                                </span>
                                            )}
                                        </div>
                                        <div className="ud-course-body">
                                            {course.category && (
                                                <span className="ud-chip">{course.category}</span>
                                            )}
                                            <h3>{course.title}</h3>
                                            <div className="ud-progress">
                                                <div className="ud-bar">
                                                    <span style={{ width: `${course.progress}%` }} />
                                                </div>
                                                <strong className="en-text">{course.progress}%</strong>
                                            </div>
                                            <ul className="ud-course-meta">
                                                <li>
                                                    <i className="ri-checkbox-circle-line" />{' '}
                                                    <span className="en-text">{course.done}</span> من{' '}
                                                    <span className="en-text">{course.total}</span> درس
                                                </li>
                                                <li>
                                                    <i className="ri-calendar-line" /> انضم{' '}
                                                    <span className="en-text">{course.joined_at}</span>
                                                </li>
                                                {course.last_accessed && (
                                                    <li>
                                                        <i className="ri-time-line" /> آخر دخول{' '}
                                                        {course.last_accessed}
                                                    </li>
                                                )}
                                            </ul>
                                            {course.last_lesson && !course.completed_at && (
                                                <p className="ud-course-next">
                                                    <i className="ri-play-circle-line" /> توقف عند:{' '}
                                                    {course.last_lesson}
                                                </p>
                                            )}
                                            {!course.deleted && (
                                                <div className="ud-course-links">
                                                    {course.can_edit && (
                                                        <Link
                                                            href={route(
                                                                'admin.courses.edit',
                                                                course.course_id,
                                                            )}
                                                        >
                                                            <i className="ri-edit-line" /> الدورة
                                                        </Link>
                                                    )}
                                                    <a
                                                        href={route('courses.show', course.slug)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <i className="ri-external-link-line" /> في الموقع
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </AdminPanel>

                    {/* ---------- Requests ---------- */}
                    <AdminPanel
                        title={`الطلبات والاشتراكات (${requests.length})`}
                        icon="ri-file-list-3-line"
                        className="mt-4"
                    >
                        {requests.length === 0 ? (
                            <Empty icon="ri-file-list-3-line" text="لا توجد طلبات انضمام." />
                        ) : (
                            <ol className="ud-timeline">
                                {requests.map((request) => {
                                    const status = STATUS[request.status] ?? STATUS.pending;

                                    return (
                                        <li key={request.id} className={status.className}>
                                            <span className="ud-timeline-dot">
                                                <i className={status.icon} />
                                            </span>
                                            <div className="ud-timeline-body">
                                                <div className="ud-timeline-head">
                                                    <strong>{request.title}</strong>
                                                    <span className={`ud-pill ${status.className}`}>
                                                        {request.status_label}
                                                    </span>
                                                </div>
                                                <div className="ud-timeline-meta">
                                                    <span>
                                                        <i className="ri-send-plane-line" /> {request.source}
                                                    </span>
                                                    <span className="en-text">
                                                        <i className="ri-calendar-line" />{' '}
                                                        {request.created_at}
                                                    </span>
                                                    {request.decided_at && (
                                                        <span>
                                                            <i className="ri-user-star-line" />{' '}
                                                            {request.decided_by || 'الإدارة'} ·{' '}
                                                            <span className="en-text">
                                                                {request.decided_at}
                                                            </span>
                                                        </span>
                                                    )}
                                                </div>
                                                {request.message && (
                                                    <p className="ud-quote">
                                                        <i className="ri-chat-3-line" /> {request.message}
                                                    </p>
                                                )}
                                                {request.admin_note && (
                                                    <p className="ud-quote is-admin">
                                                        <i className="ri-shield-user-line" />{' '}
                                                        {request.admin_note}
                                                    </p>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </AdminPanel>

                    <div className="ud-grid-2 mt-4">
                        {/* ---------- Recent lessons ---------- */}
                        <AdminPanel title="آخر الدروس" icon="ri-play-list-line">
                            {recentLessons.length === 0 ? (
                                <Empty icon="ri-play-list-line" text="لم يفتح أي درس بعد." />
                            ) : (
                                <ul className="ud-list">
                                    {recentLessons.map((row) => (
                                        <li key={row.id}>
                                            <span
                                                className={`ud-list-icon${row.completed ? ' is-done' : ''}`}
                                            >
                                                <i
                                                    className={
                                                        row.completed ? 'ri-check-line' : 'ri-play-line'
                                                    }
                                                />
                                            </span>
                                            <span className="ud-list-body">
                                                <strong>{row.lesson}</strong>
                                                <small>
                                                    {row.course} · {row.when}
                                                </small>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </AdminPanel>

                        {/* ---------- Security ---------- */}
                        {security && (
                            <AdminPanel title="سجل الأمان" icon="ri-shield-keyhole-line">
                                {security.length === 0 ? (
                                    <Empty icon="ri-shield-keyhole-line" text="لا أحداث مسجّلة." />
                                ) : (
                                    <ul className="ud-list">
                                        {security.map((event) => (
                                            <li key={event.id}>
                                                <span className="ud-list-icon is-security">
                                                    <i className="ri-shield-line" />
                                                </span>
                                                <span className="ud-list-body">
                                                    <strong>{event.description}</strong>
                                                    <small title={event.at}>
                                                        {event.when}
                                                        {event.ip && (
                                                            <span className="en-text" dir="ltr">
                                                                {' '}
                                                                · {event.ip}
                                                            </span>
                                                        )}
                                                    </small>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </AdminPanel>
                        )}
                    </div>
                </div>
            </div>
        </AdminUiPage>
    );
}
