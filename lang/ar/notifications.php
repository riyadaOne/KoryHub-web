<?php

// Cloche de l'en-tête et e-mails de sécurité (W02).
return [
    'bell' => 'الإشعارات',
    'mark_all_read' => 'تعليم الكل كمقروء',
    'empty' => 'لا توجد إشعارات',
    'detail_token' => 'الرمز: :token',
    'detail_ip' => 'عنوان IP: :ip',
    'detail_expires' => 'انتهاء الصلاحية: :expires',
    'not_you' => 'لم تكن أنت؟ ألغِ الرمز المعني وغيّر كلمة المرور.',
    'cli_login' => [
        'title' => 'تسجيل دخول جديد عبر CLI',
        'mail' => 'رُبطت طرفية للتو بحسابك.',
        'action' => 'عرض رموزي',
    ],
    'token_created' => [
        'title' => 'رمز جديد «:token»',
        'mail' => 'أُنشئ للتو رمز CLI على حسابك.',
        'action' => 'عرض رموزي',
    ],
    'token_revoked' => [
        'title' => 'أُلغي الرمز «:token»',
        'mail' => 'لم يعد هذا الرمز يمنح الوصول إلى KoryHub.',
        'action' => 'عرض رموزي',
    ],
    'token_expiring' => [
        'title' => 'ينتهي الرمز «:token» بعد 7 أيام',
        'mail' => 'إذا كنت لا تزال تستخدمه، أنشئ رمزًا جديدًا قبل انتهائه.',
        'action' => 'عرض رموزي',
    ],
    'password_changed' => [
        'title' => 'تم تغيير كلمة المرور',
        'mail' => 'تم تغيير كلمة مرور حسابك للتو.',
        'action' => 'إعدادات الأمان',
    ],
];
