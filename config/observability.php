<?php

use App\Models\AiProvider;
use App\Models\AppStorageConfig;
use App\Models\BackupSchedule;
use App\Models\EvolutionInstance;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\SmtpSetting;
use App\Models\StorageDiskMapping;
use App\Models\User;

/**
 * إعدادات طبقة المراقبة والسجلات
 *
 * التدقيق منتقى بوعي: الموديلات الحساسة قليلة التغيّر فقط. الموديلات عالية الحجم
 * (الوسائط، رسائل واتساب، الإشعارات) مستبعدة لأن تدقيقها يدفن المهم.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | سجل التدقيق (Spatie activitylog)
    |--------------------------------------------------------------------------
    */
    'audit' => [

        /*
         * قائمة بيضاء صريحة لكل موديل: `fields` هي الحقول المسجّلة فقط.
         *
         * ⚠️ لا تستخدم logAll()/logFillable() أبداً — Spatie تسجّل *قيم الخصائص*،
         * وحقول مثل api_key و password تُفكّ تشفيرها عبر الـcast فتُخزَّن نصاً صريحاً.
         */
        'models' => [
            User::class => [
                'log_name' => 'users',
                'fields' => ['name_ar', 'name_en', 'email', 'phone'],
            ],
            SmtpSetting::class => [
                'log_name' => 'settings',
                'fields' => [
                    'name', 'host', 'port', 'username', 'encryption',
                    'from_address', 'from_name', 'is_default', 'is_active',
                ],
            ],
            AiProvider::class => [
                'log_name' => 'settings',
                'fields' => ['name', 'driver', 'base_url', 'default_model', 'is_default', 'is_active'],
            ],
            AppStorageConfig::class => [
                // `config` مستبعد كاملاً — يحوي secret_access_key و password و token و client_secret.
                'log_name' => 'settings',
                'fields' => ['name', 'driver', 'is_active', 'priority', 'cdn_url', 'max_backups'],
            ],
            StorageDiskMapping::class => [
                'log_name' => 'settings',
                'fields' => ['disk_name', 'label', 'primary_storage_id', 'fallback_storage_ids', 'is_active'],
            ],
            SiteSetting::class => [
                'log_name' => 'content',
                'fields' => [
                    'site_name', 'site_name_en', 'tagline', 'logo_path', 'favicon_path',
                    'primary_whatsapp', 'email', 'phone', 'address', 'footer_text',
                ],
            ],
            SitePage::class => [
                'log_name' => 'content',
                'fields' => ['title', 'slug', 'excerpt', 'meta_title', 'meta_description', 'is_published', 'sort_order'],
            ],
            BackupSchedule::class => [
                'log_name' => 'backups',
                'fields' => [
                    'name', 'backup_type', 'storage_config_id', 'frequency', 'time',
                    'day_of_week', 'day_of_month', 'retention_days', 'is_active',
                ],
            ],
            EvolutionInstance::class => [
                'log_name' => 'whatsapp',
                'fields' => ['instance_name', 'label', 'connection_status', 'rotation_enabled', 'is_default', 'is_manual'],
            ],
        ],

        /*
         * دفاع ثانٍ: تُحذف هذه الأسماء من أي قائمة بيضاء أعلاه مهما حدث.
         * تحمي من خطأ بشري عند إضافة موديل جديد أو تعديل قائمة قائمة.
         */
        'never_log' => [
            'password',
            'password_confirmation',
            'remember_token',
            'api_key',
            'evolution_api_key',
            'secret_access_key',
            'client_secret',
            'refresh_token',
            'token',
            'secret',
            'config',
            'options',
            'metadata',
            'qr_code',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | مدد الاحتفاظ — لكل log_name على حدة
    |--------------------------------------------------------------------------
    | لا نستخدم activitylog:clean لأنه يحذف بعمر واحد عام. السجل الأمني يُحفظ أطول
    | لأنه المصدر الوحيد للتقصّي عند حادثة.
    */
    'retention' => [
        'security' => (int) env('OBSERVABILITY_RETENTION_SECURITY', 365),
        'default' => (int) env('OBSERVABILITY_RETENTION_DEFAULT', 180),
        'webhook_events' => (int) env('OBSERVABILITY_RETENTION_WEBHOOKS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | خنق أحداث الأمن المتكررة
    |--------------------------------------------------------------------------
    | محاولة تخمين واحدة تولّد آلاف الأسطر. سطر واحد لكل (بريد + IP) خلال هذه المدة.
    */
    'security_throttle_seconds' => (int) env('OBSERVABILITY_SECURITY_THROTTLE', 60),

    'per_page' => 25,

];
