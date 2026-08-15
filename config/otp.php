<?php

/**
 * إعدادات رموز التحقق لمرة واحدة (OTP)
 */
return [

    // طول الرمز. ستة أرقام هو المعتاد ويتوافق مع قوالب Meta للمصادقة.
    'length' => (int) env('OTP_LENGTH', 6),

    // مدة الصلاحية. قوالب Meta للمصادقة صلاحيتها 10 دقائق، فلا تتجاوزها.
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    // عدد محاولات التحقق المسموحة لكل رمز قبل إبطاله.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // حد طلب الرموز: max_per_window طلبات خلال window_seconds لكل (رقم + IP).
    'max_per_window' => (int) env('OTP_MAX_PER_WINDOW', 3),
    'window_seconds' => (int) env('OTP_WINDOW_SECONDS', 600),

];
