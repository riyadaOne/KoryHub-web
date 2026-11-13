<?php

// Cloche de l'en-tête et e-mails de sécurité (W02).
return [
    'bell' => 'Notifications',
    'mark_all_read' => 'Tout marquer comme lu',
    'empty' => 'Aucune notification',
    'detail_token' => 'Jeton : :token',
    'detail_ip' => 'Adresse IP : :ip',
    'detail_expires' => 'Expiration : :expires',
    'not_you' => 'Ce n\'était pas vous ? Révoquez le jeton concerné et changez votre mot de passe.',
    'cli_login' => [
        'title' => 'Nouvelle connexion du CLI',
        'mail' => 'Un terminal vient d\'être relié à votre compte.',
        'action' => 'Voir mes jetons',
    ],
    'token_created' => [
        'title' => 'Nouveau jeton « :token »',
        'mail' => 'Un jeton du CLI vient d\'être créé sur votre compte.',
        'action' => 'Voir mes jetons',
    ],
    'token_revoked' => [
        'title' => 'Jeton « :token » révoqué',
        'mail' => 'Ce jeton ne donne plus accès à KoryHub.',
        'action' => 'Voir mes jetons',
    ],
    'token_expiring' => [
        'title' => 'Le jeton « :token » expire dans 7 jours',
        'mail' => 'Si vous l\'utilisez encore, créez-en un nouveau avant son expiration.',
        'action' => 'Voir mes jetons',
    ],
    'password_changed' => [
        'title' => 'Mot de passe changé',
        'mail' => 'Le mot de passe de votre compte vient d\'être changé.',
        'action' => 'Réglages de sécurité',
    ],
    'detail_reason' => 'Motif : :reason',
    'version_published' => [
        'title' => ':module :version publiée',
        'mail' => 'Votre version :version de :module est publiée.',
        'action' => 'Ouvrir mon tableau de bord',
    ],
    'version_rejected' => [
        'title' => ':module :version rejetée',
        'mail' => 'La version :version de :module n\'a pas été publiée. Corrigez-la puis renvoyez-la avec kory push : le même numéro reste disponible.',
        'action' => 'Ouvrir mon tableau de bord',
    ],
    'types' => [
        'version_published' => 'Version publiée',
        'version_rejected' => 'Version rejetée',
        'cli_login' => 'Nouvelle connexion du CLI',
        'token_created' => 'Nouveau jeton',
        'token_revoked' => 'Jeton révoqué',
        'token_expiring' => 'Jeton qui expire',
        'password_changed' => 'Mot de passe changé',
    ],
    'preferences' => [
        'title' => 'Notifications',
        'subtitle' => 'Ce que KoryHub vous envoie, et comment.',
        'type' => 'Notification',
        'mail' => 'E-mail',
        'database' => 'Cloche',
        'group_modules' => 'Mes kory',
        'group_security' => 'Sécurité',
        'always' => 'toujours envoyées',
        'security_row' => 'Nouvelle connexion du CLI, jetons, mot de passe',
        'saved' => 'Enregistré.',
    ],
];
