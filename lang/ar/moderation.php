<?php

// Signalements et modération (W07) : feuille « Signaler », S-48, administration.
return [
    'more' => 'إجراءات أخرى',
    'report' => 'إبلاغ',
    'review_of' => 'مراجعة :handle على :module',
    'target_gone' => 'عنصر محذوف',
    'review_hidden_mine' => 'أخفى فريق الإشراف مراجعتك. لم تعد معروضة ولا محسوبة.',
    'status' => [
        'open' => 'مفتوح',
        'in_review' => 'قيد الفحص',
        'closed_no_action' => 'مغلق دون إجراء',
        'closed_with_action' => 'مغلق مع إجراء',
    ],
    'reason' => [
        'malware' => 'برمجية خبيثة',
        'illegal_content' => 'محتوى غير قانوني',
        'copyright' => 'حقوق المؤلف',
        'harassment' => 'تحرش',
    ],
    'reason_help' => [
        'malware' => 'شيفرة تسرق أو تتلف أو تتجسس.',
        'illegal_content' => 'محتوى يمنعه القانون.',
        'copyright' => 'شيفرة منسوخة دون احترام ترخيصها.',
        'harassment' => 'كلام يستهدف شخصًا.',
    ],
    'target' => [
        'module' => 'وحدة',
        'version' => 'إصدار',
        'user' => 'حساب',
        'review' => 'مراجعة',
    ],
    'resolution' => [
        'security_warning' => 'تحذير أمني',
        'legal_removal' => 'سحب قانوني',
        'review_hidden' => 'مراجعة مخفية',
        'account_suspended' => 'حساب موقوف',
        'account_deleted' => 'حساب محذوف',
    ],
    'sheet' => [
        'reason' => 'السبب',
        'details' => 'شرح',
        'optional' => 'اختياري',
        'anonymous' => 'يفحص فريق الإشراف كل بلاغ. لا يعرف الشخص المُبلَّغ عنه من أبلغ.',
        'send' => 'إرسال البلاغ',
        'sent_title' => 'أُرسل البلاغ',
        'sent' => 'شكرًا. سيفحصه فريق الإشراف.',
        'sent_text' => 'ستُبلَّغ بالقرار. تابع بلاغاتك في',
        'verify' => 'تأكيد بريدي الإلكتروني',
    ],
    'errors' => [
        'target_not_found' => 'لا يمكن الإبلاغ عن هذا العنصر.',
        'own_target' => 'لا يمكنك الإبلاغ عمّا يخصك.',
        'email_unverified' => 'أكّد بريدك الإلكتروني لتتمكن من الإبلاغ.',
        'duplicate' => 'لديك بلاغ مفتوح على هذا العنصر.',
    ],
    'mine' => [
        'title' => 'بلاغاتي',
        'subtitle' => 'بلاغاتك ونتائجها.',
        'empty' => 'لا توجد بلاغات',
        'target' => 'العنصر',
        'reason' => 'السبب',
        'status' => 'الحالة',
        'sent' => 'أُرسل',
        'no_action' => 'دون إجراء',
        'with_action' => 'اتُّخذ إجراء',
    ],
];
