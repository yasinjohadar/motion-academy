<?php

/**
 * سجل الصلاحيات المركزي — متعدد اللوحات
 * الاسم: {panel}.{resource}.{action}
 */
return [

    'panels' => [

        'admin' => [
            'label' => 'لوحة الإدارة',
            'home_route' => 'admin.dashboard',
            'groups' => [
                'system' => [
                    'label' => 'النظام',
                    'permissions' => [
                        'admin.dashboard.view' => 'عرض لوحة التحكم',
                        'admin.site-settings.list' => 'عرض إعدادات الموقع',
                        'admin.site-settings.edit' => 'تعديل إعدادات الموقع',
                        'admin.otp-settings.list' => 'عرض إعدادات رموز التحقق (OTP)',
                        'admin.otp-settings.edit' => 'تعديل إعدادات OTP والإرسال التجريبي',
                        'admin.site-pages.list' => 'عرض صفحات الموقع',
                        'admin.site-pages.create' => 'إنشاء صفحة موقع',
                        'admin.site-pages.edit' => 'تعديل صفحات الموقع',
                        'admin.site-pages.delete' => 'حذف صفحات الموقع',
                        'admin.site-pages.edit-any' => 'تعديل صفحات أنشأها آخرون',
                        'admin.site-pages.delete-any' => 'حذف صفحات أنشأها آخرون',
                    ],
                ],
                'smtp' => [
                    'label' => 'إعدادات البريد SMTP',
                    'permissions' => [
                        'admin.smtp-settings.list' => 'عرض إعدادات SMTP',
                        'admin.smtp-settings.create' => 'إنشاء إعداد SMTP',
                        'admin.smtp-settings.edit' => 'تعديل إعداد SMTP',
                        'admin.smtp-settings.delete' => 'حذف إعداد SMTP',
                        'admin.smtp-settings.test' => 'اختبار SMTP وتعيين الافتراضي',
                    ],
                ],
                'ai' => [
                    'label' => 'الذكاء الاصطناعي',
                    'permissions' => [
                        'admin.ai-providers.list' => 'عرض مزودي الذكاء الاصطناعي',
                        'admin.ai-providers.create' => 'إنشاء مزود ذكاء اصطناعي',
                        'admin.ai-providers.edit' => 'تعديل مزودي الذكاء الاصطناعي',
                        'admin.ai-providers.delete' => 'حذف مزود ذكاء اصطناعي',
                        'admin.ai-providers.test' => 'اختبار مزودي الذكاء الاصطناعي وتعيين الافتراضي',
                    ],
                ],
                'storage' => [
                    'label' => 'التخزين السحابي',
                    'permissions' => [
                        'admin.app-storage.list' => 'عرض إعدادات التخزين',
                        'admin.app-storage.create' => 'إنشاء إعداد تخزين',
                        'admin.app-storage.edit' => 'تعديل إعدادات التخزين',
                        'admin.app-storage.delete' => 'حذف إعداد تخزين',
                        'admin.app-storage.edit-any' => 'تعديل إعدادات تخزين أنشأها آخرون',
                        'admin.app-storage.delete-any' => 'حذف إعدادات تخزين أنشأها آخرون',
                        'admin.app-storage.test' => 'اختبار اتصال التخزين',
                        'admin.storage-disk-mappings.list' => 'عرض ربط الأقراص',
                        'admin.storage-disk-mappings.create' => 'إنشاء ربط قرص',
                        'admin.storage-disk-mappings.edit' => 'تعديل ربط الأقراص',
                        'admin.storage-disk-mappings.delete' => 'حذف ربط قرص',
                    ],
                ],
                'backups' => [
                    'label' => 'النسخ الاحتياطي',
                    'permissions' => [
                        'admin.backups.list' => 'عرض النسخ الاحتياطية',
                        'admin.backups.create' => 'إنشاء نسخة احتياطية',
                        'admin.backups.show' => 'عرض تفاصيل النسخة',
                        'admin.backups.download' => 'تنزيل النسخ الاحتياطية',
                        'admin.backups.restore' => 'استرجاع النسخ الاحتياطية',
                        'admin.backups.delete' => 'حذف النسخ الاحتياطية',
                        'admin.backups.edit-any' => 'إدارة نسخ أنشأها آخرون',
                        'admin.backups.delete-any' => 'حذف نسخ أنشأها آخرون',
                        'admin.backup-schedules.list' => 'عرض جداول النسخ',
                        'admin.backup-schedules.create' => 'إنشاء جدولة نسخ',
                        'admin.backup-schedules.edit' => 'تعديل جداول النسخ',
                        'admin.backup-schedules.delete' => 'حذف جداول النسخ',
                        'admin.backup-schedules.edit-any' => 'تعديل جداول أنشأها آخرون',
                        'admin.backup-schedules.delete-any' => 'حذف جداول أنشأها آخرون',
                    ],
                ],
                'media' => [
                    'label' => 'مكتبة الوسائط',
                    'permissions' => [
                        'admin.media.list' => 'عرض مكتبة الوسائط',
                        'admin.media.create' => 'رفع وسائط',
                        'admin.media.edit' => 'تعديل بيانات الوسائط',
                        'admin.media.delete' => 'حذف الوسائط',
                        'admin.media.show' => 'عرض تفاصيل وسائط',
                        'admin.media.edit-any' => 'تعديل وسائط رفعها آخرون',
                        'admin.media.delete-any' => 'حذف وسائط رفعها آخرون',
                    ],
                ],
                'blog' => [
                    'label' => 'المدونة',
                    'permissions' => [
                        'admin.blog-posts.list' => 'عرض مقالات المدونة',
                        'admin.blog-posts.create' => 'إنشاء مقال',
                        'admin.blog-posts.edit' => 'تعديل مقالات المدونة',
                        'admin.blog-posts.delete' => 'حذف مقالات المدونة',
                        'admin.blog-posts.edit-any' => 'تعديل مقالات كتبها آخرون',
                        'admin.blog-posts.delete-any' => 'حذف مقالات كتبها آخرون',
                        'admin.blog-categories.list' => 'عرض تصنيفات المدونة',
                        'admin.blog-categories.create' => 'إنشاء تصنيف مدونة',
                        'admin.blog-categories.edit' => 'تعديل تصنيفات المدونة',
                        'admin.blog-categories.delete' => 'حذف تصنيفات المدونة',
                        'admin.blog-tags.list' => 'عرض وسوم المدونة',
                        'admin.blog-tags.create' => 'إنشاء وسم مدونة',
                        'admin.blog-tags.edit' => 'تعديل وسوم المدونة',
                        'admin.blog-tags.delete' => 'حذف وسوم المدونة',
                        'admin.blog-ai.generate' => 'توليد مقالات بالذكاء الاصطناعي',
                    ],
                ],
                'enrollments' => [
                    'label' => 'الانضمام للدورات',
                    'permissions' => [
                        'admin.enrollments.list' => 'عرض طلبات وانضمامات الدورات',
                        'admin.enrollments.create' => 'إضافة طالب إلى دورة',
                        'admin.enrollments.edit' => 'قبول ورفض طلبات الانضمام',
                        'admin.enrollments.delete' => 'إزالة طالب من دورة',
                    ],
                ],
                'newsletter' => [
                    'label' => 'القائمة البريدية',
                    'permissions' => [
                        'admin.newsletter-subscribers.list' => 'عرض المشتركين في القائمة البريدية',
                        'admin.newsletter-subscribers.create' => 'إضافة مشترك يدوياً',
                        'admin.newsletter-subscribers.edit' => 'تفعيل وإلغاء اشتراك المشتركين',
                        'admin.newsletter-subscribers.delete' => 'حذف المشتركين',
                        'admin.newsletter-subscribers.export' => 'تصدير القائمة البريدية (CSV)',
                    ],
                ],
                'courses' => [
                    'label' => 'الدورات',
                    'permissions' => [
                        'admin.course-categories.list' => 'عرض تصنيفات الدورات',
                        'admin.course-categories.create' => 'إنشاء تصنيف دورات',
                        'admin.course-categories.edit' => 'تعديل تصنيفات الدورات وترتيبها',
                        'admin.course-categories.delete' => 'حذف تصنيفات الدورات',
                        'admin.course-categories.restore' => 'إدارة سلة تصنيفات الدورات (استرجاع وحذف نهائي)',
                        'admin.courses.list' => 'عرض الدورات',
                        'admin.courses.create' => 'إنشاء دورة',
                        'admin.courses.edit' => 'تعديل الدورات ومناهجها',
                        'admin.courses.delete' => 'حذف الدورات',
                        'admin.courses.edit-any' => 'تعديل دورات مدرّسين آخرين',
                        'admin.courses.delete-any' => 'حذف دورات مدرّسين آخرين',
                        'admin.courses.restore' => 'إدارة سلة الدورات (استرجاع وحذف نهائي)',
                    ],
                ],
                'whatsapp' => [
                    'label' => 'واتساب Evolution',
                    'permissions' => [
                        'admin.whatsapp-settings.list' => 'عرض إعدادات واتساب',
                        'admin.whatsapp-settings.edit' => 'تعديل إعدادات واتساب',
                        'admin.whatsapp-instances.list' => 'عرض أرقام واتساب',
                        'admin.whatsapp-instances.create' => 'إضافة رقم واتساب',
                        'admin.whatsapp-instances.edit' => 'تعديل أرقام واتساب والتبديل',
                        'admin.whatsapp-instances.delete' => 'حذف رقم واتساب',
                        'admin.whatsapp-send.create' => 'إرسال رسالة واتساب',
                        'admin.whatsapp-campaigns.list' => 'عرض حملات واتساب',
                        'admin.whatsapp-campaigns.create' => 'إنشاء حملة واتساب',
                        'admin.whatsapp-campaigns.show' => 'عرض تفاصيل حملة واتساب',
                        'admin.whatsapp-campaigns.edit' => 'تعديل حملات واتساب',
                        'admin.whatsapp-campaigns.delete' => 'حذف حملات واتساب',
                        'admin.whatsapp-campaigns.edit-any' => 'تعديل حملات أنشأها آخرون',
                        'admin.whatsapp-campaigns.delete-any' => 'حذف حملات أنشأها آخرون',
                        'admin.whatsapp-messages.list' => 'عرض سجل رسائل واتساب',
                    ],
                ],
                'observability' => [
                    'label' => 'المراقبة والسجلات',
                    'permissions' => [
                        'admin.observability.activity' => 'عرض سجل التدقيق',
                        'admin.observability.security' => 'عرض السجل الأمني والجلسات',
                        'admin.observability.logs' => 'عرض ملفات سجلات النظام',
                        'admin.observability.jobs' => 'إدارة المهام الفاشلة',
                        'admin.observability.database' => 'عرض تفاصيل قاعدة البيانات',
                    ],
                ],
                'notifications' => [
                    'label' => 'الإشعارات',
                    'permissions' => [
                        'admin.notifications.send' => 'إرسال إشعار/تعميم للمستخدمين',
                        'admin.notifications.settings' => 'إدارة إعدادات الإشعارات العامة',
                    ],
                ],
                'roles_users' => [
                    'label' => 'الأدوار والمستخدمون',
                    'permissions' => [
                        'admin.roles.list' => 'عرض الأدوار',
                        'admin.roles.create' => 'إنشاء دور',
                        'admin.roles.edit' => 'تعديل دور',
                        'admin.roles.delete' => 'حذف دور',
                        'admin.users.list' => 'عرض المستخدمين',
                        'admin.users.create' => 'إنشاء مستخدم',
                        'admin.users.edit' => 'تعديل مستخدم',
                        'admin.users.delete' => 'حذف مستخدم',
                        'admin.users.impersonate' => 'الدخول إلى حساب طالب',
                    ],
                ],
            ],
            'roles' => [
                'admin' => ['*'],
            ],
        ],

        // Learners. They use the public site (/dashboard, /learn), not a panel, so
        // there are no permissions here — the role only marks the account. Every
        // account is an admin or a student (App\Support\AccountType).
        'student' => [
            'label' => 'الطلاب',
            'home_route' => 'student.dashboard',
            'groups' => [],
            'roles' => [
                'student' => [],
            ],
        ],

    ],

];
