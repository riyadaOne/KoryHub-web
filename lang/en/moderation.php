<?php

// Signalements et modération (W07) : feuille « Signaler », S-48, administration.
return [
    'more' => 'More actions',
    'report' => 'Report',
    'review_of' => 'Review by :handle on :module',
    'target_gone' => 'Deleted item',
    'review_hidden_mine' => 'Your review was hidden by moderation. It is no longer shown or counted.',
    'status' => [
        'open' => 'Open',
        'in_review' => 'In review',
        'closed_no_action' => 'Closed, no action',
        'closed_with_action' => 'Closed with action',
    ],
    'reason' => [
        'malware' => 'Malware',
        'illegal_content' => 'Illegal content',
        'copyright' => 'Copyright',
        'harassment' => 'Harassment',
    ],
    'reason_help' => [
        'malware' => 'Code that steals, destroys or spies.',
        'illegal_content' => 'Content prohibited by law.',
        'copyright' => 'Code copied without respecting its license.',
        'harassment' => 'Content targeting a person.',
    ],
    'target' => [
        'module' => 'Module',
        'version' => 'Version',
        'user' => 'Account',
        'review' => 'Review',
    ],
    'resolution' => [
        'security_warning' => 'Security warning',
        'legal_removal' => 'Legal removal',
        'review_hidden' => 'Review hidden',
        'account_suspended' => 'Account suspended',
        'account_deleted' => 'Account deleted',
    ],
    'sheet' => [
        'reason' => 'Reason',
        'details' => 'Explanation',
        'optional' => 'optional',
        'anonymous' => 'The moderation team reviews every report. The reported person never sees who reported them.',
        'send' => 'Send report',
        'sent_title' => 'Report sent',
        'sent' => 'Thank you. The moderation team will review it.',
        'sent_text' => 'You will be told the decision. Follow your reports in',
        'verify' => 'Verify my email address',
    ],
    'errors' => [
        'target_not_found' => 'This item cannot be reported.',
        'own_target' => 'You cannot report something that belongs to you.',
        'email_unverified' => 'Verify your email address to report.',
        'duplicate' => 'You already have an open report on this item.',
    ],
    'mine' => [
        'title' => 'My reports',
        'subtitle' => 'Your reports and their outcome.',
        'empty' => 'No reports',
        'target' => 'Item',
        'reason' => 'Reason',
        'status' => 'Status',
        'sent' => 'Sent',
        'no_action' => 'No action',
        'with_action' => 'Action was taken',
    ],
];
