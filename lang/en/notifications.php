<?php

// Cloche de l'en-tête et e-mails de sécurité (W02).
return [
    'bell' => 'Notifications',
    'mark_all_read' => 'Mark all as read',
    'empty' => 'No notifications',
    'detail_token' => 'Token: :token',
    'detail_ip' => 'IP address: :ip',
    'detail_expires' => 'Expiration: :expires',
    'not_you' => 'Not you? Revoke the token concerned and change your password.',
    'cli_login' => [
        'title' => 'New CLI login',
        'mail' => 'A terminal has just been connected to your account.',
        'action' => 'See my tokens',
    ],
    'token_created' => [
        'title' => 'New token “:token”',
        'mail' => 'A CLI token has just been created on your account.',
        'action' => 'See my tokens',
    ],
    'token_revoked' => [
        'title' => 'Token “:token” revoked',
        'mail' => 'This token no longer gives access to KoryHub.',
        'action' => 'See my tokens',
    ],
    'token_expiring' => [
        'title' => 'Token “:token” expires in 7 days',
        'mail' => 'If you still use it, create a new one before it expires.',
        'action' => 'See my tokens',
    ],
    'password_changed' => [
        'title' => 'Password changed',
        'mail' => 'The password of your account has just been changed.',
        'action' => 'Security settings',
    ],
    'detail_reason' => 'Reason: :reason',
    'version_published' => [
        'title' => ':module :version published',
        'mail' => 'Your version :version of :module is published.',
        'action' => 'Open my dashboard',
    ],
    'version_rejected' => [
        'title' => ':module :version rejected',
        'mail' => 'Your version :version of :module was not published. Fix it, then send it again with kory push: the same version number stays available.',
        'action' => 'Open my dashboard',
    ],
    'types' => [
        'version_published' => 'Version published',
        'version_rejected' => 'Version rejected',
        'cli_login' => 'New CLI login',
        'token_created' => 'New token',
        'token_revoked' => 'Token revoked',
        'token_expiring' => 'Token expiring',
        'password_changed' => 'Password changed',
    ],
    'preferences' => [
        'title' => 'Notifications',
        'subtitle' => 'What KoryHub sends you, and how.',
        'type' => 'Notification',
        'mail' => 'Email',
        'database' => 'Bell',
        'group_modules' => 'My kory',
        'group_security' => 'Security',
        'always' => 'always sent',
        'security_row' => 'New CLI login, tokens, password',
        'saved' => 'Saved.',
    ],
];
