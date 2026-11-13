<?php

// Cloche de l'en-tête et e-mails de sécurité (W02).
return [
    'bell' => 'Notificações',
    'mark_all_read' => 'Marcar tudo como lido',
    'empty' => 'Nenhuma notificação',
    'detail_token' => 'Token: :token',
    'detail_ip' => 'Endereço IP: :ip',
    'detail_expires' => 'Expiração: :expires',
    'not_you' => 'Não foi você? Revogue o token em causa e altere a sua palavra-passe.',
    'cli_login' => [
        'title' => 'Nova ligação do CLI',
        'mail' => 'Um terminal acabou de ser ligado à sua conta.',
        'action' => 'Ver os meus tokens',
    ],
    'token_created' => [
        'title' => 'Novo token «:token»',
        'mail' => 'Um token do CLI acabou de ser criado na sua conta.',
        'action' => 'Ver os meus tokens',
    ],
    'token_revoked' => [
        'title' => 'Token «:token» revogado',
        'mail' => 'Este token já não dá acesso ao KoryHub.',
        'action' => 'Ver os meus tokens',
    ],
    'token_expiring' => [
        'title' => 'O token «:token» expira daqui a 7 dias',
        'mail' => 'Se ainda o utiliza, crie um novo antes de expirar.',
        'action' => 'Ver os meus tokens',
    ],
    'password_changed' => [
        'title' => 'Palavra-passe alterada',
        'mail' => 'A palavra-passe da sua conta acabou de ser alterada.',
        'action' => 'Definições de segurança',
    ],
    'detail_reason' => 'Motivo: :reason',
    'version_published' => [
        'title' => ':module :version publicada',
        'mail' => 'A sua versão :version de :module foi publicada.',
        'action' => 'Abrir o meu painel',
    ],
    'version_rejected' => [
        'title' => ':module :version rejeitada',
        'mail' => 'A versão :version de :module não foi publicada. Corrija-a e volte a enviá-la com kory push: o mesmo número continua disponível.',
        'action' => 'Abrir o meu painel',
    ],
    'types' => [
        'version_published' => 'Versão publicada',
        'version_rejected' => 'Versão rejeitada',
        'cli_login' => 'Nova ligação do CLI',
        'token_created' => 'Novo token',
        'token_revoked' => 'Token revogado',
        'token_expiring' => 'Token a expirar',
        'password_changed' => 'Palavra-passe alterada',
    ],
    'preferences' => [
        'title' => 'Notificações',
        'subtitle' => 'O que o KoryHub lhe envia, e como.',
        'type' => 'Notificação',
        'mail' => 'E-mail',
        'database' => 'Sino',
        'group_modules' => 'Os meus kory',
        'group_security' => 'Segurança',
        'always' => 'sempre enviadas',
        'security_row' => 'Nova ligação do CLI, tokens, palavra-passe',
        'saved' => 'Guardado.',
    ],
];
