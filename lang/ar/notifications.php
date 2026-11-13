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
    'detail_reason' => 'السبب: :reason',
    'version_published' => [
        'title' => 'نُشر :module :version',
        'mail' => 'نُشر إصدارك :version من :module.',
        'action' => 'فتح لوحة التحكم',
    ],
    'version_rejected' => [
        'title' => 'رُفض :module :version',
        'mail' => 'لم يُنشر الإصدار :version من :module. صحّحه ثم أعد إرساله عبر kory push: يبقى رقم الإصدار نفسه متاحًا.',
        'action' => 'فتح لوحة التحكم',
    ],
    'update_available' => [
        'title' => 'تحديث متاح: :module :version',
        'mail' => 'نُشر إصدار جديد من kory قمت بتثبيته. إصدارك: :installed.',
        'action' => 'عرض الإصدار',
    ],
    'version_deprecated' => [
        'title' => 'أُهمل :module :version',
        'mail' => 'أهمل المؤلف إصدارًا قمت بتثبيته. لا يتغير شيء في مشاريعك دون موافقتك.',
        'action' => 'عرض الإصدار',
    ],
    'types' => [
        'update_available' => 'تحديث متاح',
        'version_deprecated' => 'إصدار مُهمل',
        'version_published' => 'إصدار منشور',
        'version_rejected' => 'إصدار مرفوض',
        'cli_login' => 'تسجيل دخول جديد عبر CLI',
        'token_created' => 'رمز جديد',
        'token_revoked' => 'رمز ملغى',
        'token_expiring' => 'رمز ينتهي قريبًا',
        'password_changed' => 'تغيير كلمة المرور',
    ],
    'preferences' => [
        'title' => 'الإشعارات',
        'subtitle' => 'ما يرسله لك KoryHub، وكيف.',
        'type' => 'الإشعار',
        'mail' => 'البريد الإلكتروني',
        'database' => 'الجرس',
        'group_modules' => 'وحداتي',
        'group_installed' => 'وحدات kory المثبتة',
        'group_security' => 'الأمان',
        'always' => 'تُرسل دائمًا',
        'security_row' => 'تسجيل دخول جديد عبر CLI، الرموز، كلمة المرور',
        'saved' => 'تم الحفظ.',
    ],
];
