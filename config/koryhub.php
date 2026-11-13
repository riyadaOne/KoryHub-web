<?php

/*
|--------------------------------------------------------------------------
| KoryHub
|--------------------------------------------------------------------------
|
| Réglages propres à KoryHub (TRD §4.1, T-05). Les valeurs qui changent d'un
| environnement à l'autre viennent du fichier .env ; aucune n'est écrite dans
| le code. Chaque module ajoute ici ses réglages quand il en a besoin.
|
*/

return [

    /*
     * Domaines des quatre espaces (TRD §6). En local : koryhub.test et ses
     * sous-domaines ; en préproduction : noms de premier niveau (piège 22).
     */
    'domains' => [
        'main' => env('KORYHUB_DOMAIN_MAIN', 'koryhub.test'),
        'api' => env('KORYHUB_DOMAIN_API', 'api.koryhub.test'),
        'docs' => env('KORYHUB_DOMAIN_DOCS', 'docs.koryhub.test'),
        'admin' => env('KORYHUB_DOMAIN_ADMIN', 'admin.koryhub.test'),
    ],

    /*
     * Noms @auteur réservés (W01) : jamais attribués à un compte, parce qu'ils
     * ressemblent à une adresse du site ou à KoryHub lui-même.
     */
    'reserved_handles' => [
        'admin', 'administration', 'api', 'docs', 'documentation', 'help', 'support',
        'settings', 'dashboard', 'login', 'logout', 'register', 'search', 'install',
        'koryhub', 'kory', 'riyadaone', 'root', 'system', 'systeme', 'security',
        'about', 'governance', 'terms', 'privacy', 'status', 'up', 'www', 'mail',
        'categories', 'stars', 'watching', 'notifications', 'user', 'users', 'deleted',
    ],

    /*
     * Photo de profil (F-AUT-16, DON-08) : jpg, png ou webp, 2 Mo au plus.
     * Disque local en développement, R2 à partir de W03.
     */
    'avatars' => [
        'disk' => env('KORYHUB_AVATARS_DISK', 'public'),
        'max_kilobytes' => 2048,
    ],

    /*
     * CLI kory (TRD §17, §18, T-11) : sous la version minimale, l'API répond 426
     * « Mettez à jour kory » ; la dernière version est annoncée par GET /v1/meta.
     * Versions au format x.y.z.
     */
    'cli' => [
        'minimum_version' => env('KORYHUB_CLI_MINIMUM_VERSION', '0.1.0'),
        'latest_version' => env('KORYHUB_CLI_LATEST_VERSION', '0.1.0'),
    ],

    /*
     * Jetons du CLI (TRD §7.2) : préfixe reconnu par la recherche de secrets (§22).
     */
    'tokens' => [
        'prefix' => 'kory_',
    ],

    /*
     * Publication des kory (TRD §13, §16, §22). Archives : disque « local » (privé, liens
     * temporaires) en développement, R2 (disque compatible S3) en production.
     */
    'publication' => [
        'archives_disk' => env('KORYHUB_ARCHIVES_DISK', 'local'),
        'max_archive_kb' => 10240,
        'max_uncompressed_mb' => 50,
        'max_files' => 1000,
        'gitleaks_binary' => env('KORYHUB_GITLEAKS_BINARY', 'gitleaks'),
        'scan_timeout_seconds' => 60,
        'per_hour' => 30,
    ],

    /*
     * Téléchargements (F-RCH-15, TRD §17) : limites par heure, sans compte (par adresse IP réelle)
     * et par compte connecté ; durée du lien signé vers l'archive.
     */
    'downloads' => [
        'guest_per_hour' => (int) env('KORYHUB_DOWNLOADS_GUEST_PER_HOUR', 60),
        'user_per_hour' => (int) env('KORYHUB_DOWNLOADS_USER_PER_HOUR', 5000),
        'link_minutes' => 5,
        'audit_max_modules' => 200,
    ],

    /*
     * Score de classement (TRD §14, décision W05 2a) : poids réglables sans changer le code.
     * Note : moyenne bayésienne avec prior_count notes fictives à prior_mean sur 5.
     */
    'score' => [
        'downloads' => 1.0,
        'rating' => 1.0,
        'recency' => 0.5,
        'certification' => 0.5,
        'prior_count' => 5,
        'prior_mean' => 3.5,
        'half_life_days' => 90,
    ],

    // Recherche (W05) : résultats par page sur le site et dans l'API.
    'search' => [
        'per_page' => [20, 50],
        'api_max' => 100,
        'max_query' => 100,
    ],

    /*
     * Journal d'audit (TRD §21) : champs notés comme modifiés, sans leur valeur,
     * pour qu'aucune donnée personnelle ni aucun secret n'y entre.
     */
    'audit' => [
        'redacted_fields' => [
            'name', 'email', 'password', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes',
            // Commentaire d'un avis : texte libre, peut contenir des données personnelles (W05).
            'comment',
        ],
    ],

    /*
     * Proxies de confiance : plages d'adresses de Cloudflare (TRD §22, piège 17).
     * Liste publiée sur https://www.cloudflare.com/ips/ : à revérifier à la mise
     * en production. En local, aucune requête ne vient de ces adresses.
     */
    'trusted_proxies' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ],

];
