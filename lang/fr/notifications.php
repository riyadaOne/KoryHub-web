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
];
