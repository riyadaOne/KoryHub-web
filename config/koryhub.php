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
        // Commandes d'installation (TRD §18.1, K05) : scripts servis par le site (/install.ps1, /install.sh).
        'install_windows' => 'irm '.rtrim((string) env('APP_URL', 'https://koryhub.dev'), '/').'/install.ps1 | iex',
        'install_unix' => 'curl -fsSL '.rtrim((string) env('APP_URL', 'https://koryhub.dev'), '/').'/install.sh | sh',
        // Exécutables et checksums.txt : {download_url}/{version}/ ; R2 public en MEP, public/cli/ en local (décision 3a).
        'download_url' => env('KORYHUB_CLI_DOWNLOAD_URL', rtrim((string) env('APP_URL', 'https://koryhub.dev'), '/').'/cli'),
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
     * Modération (W07, F-MOD-11, F-MOD-12) : alerte des admins dès multiple_threshold signalements
     * ouverts sur un même objet (rien n'est masqué automatiquement) ; relance après stale_hours.
     */
    'moderation' => [
        'multiple_threshold' => 3,
        'stale_hours' => 48,
    ],

    /*
     * Sauvegardes et supervision (W08, TRD §25.3, §25.4) : base où le test de restauration charge
     * la dernière sauvegarde (jamais la base de l'application) ; adresses de signal Healthchecks.io
     * (vides = rien n'est envoyé). Mot de passe des sauvegardes : BACKUP_ARCHIVE_PASSWORD.
     */
    // Fuseau des heures exactes de l'administration (S-71, S-72) ; les dates restent stockées en UTC.
    'display_timezone' => env('KORYHUB_DISPLAY_TIMEZONE', 'Africa/Ndjamena'),

    'backups' => [
        'restore_database' => env('BACKUP_RESTORE_DATABASE', 'koryhub_restore'),
    ],

    'healthchecks' => [
        'heartbeat' => env('HEALTHCHECKS_HEARTBEAT_URL', ''),
        'backup' => env('HEALTHCHECKS_BACKUP_URL', ''),
    ],

    /*
     * Données de démonstration (DON-09), jamais en production : mot de passe des comptes, et boîte
     * réelle du jeu vitrine (ShowcaseSeeder), déclinée en adresses « boite+compte@domaine ». Lues ici
     * et non par env() dans les seeders, pour fonctionner avec la configuration en cache.
     */
    'demo' => [
        'password' => env('KORYHUB_DEMO_PASSWORD'),
        'showcase_mailbox' => env('KORYHUB_SHOWCASE_MAILBOX'),
    ],

    /*
     * Bloc CADEV 2026 de l'accueil, pour le jury : video de presentation et equipe. Affiche seulement
     * si video_url est renseignee, et jusqu'a la veille de « until » (fuseau display_timezone) ;
     * jamais en production tant que KORYHUB_CADEV_VIDEO_URL est vide.
     */
    'cadev' => [
        'video_url' => env('KORYHUB_CADEV_VIDEO_URL'),
        'poster_url' => env('KORYHUB_CADEV_POSTER_URL'),
        'until' => env('KORYHUB_CADEV_UNTIL', '2026-11-30'),
        'team' => [
            ['name' => 'Skorzeny Kemleyogoto', 'role' => 'founder', 'github' => 'sk0rzeny'],
            ['name' => 'Koryam Genevieve', 'role' => 'frontend', 'github' => 'koryam004-lgtm'],
        ],
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
