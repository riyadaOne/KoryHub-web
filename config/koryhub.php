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
     * CLI kory (TRD §17, §18, T-11) : sous la version minimale, l'API répond 426
     * « Mettez à jour kory » ; la dernière version est annoncée par GET /v1/meta.
     * Versions au format x.y.z.
     */
    'cli' => [
        'minimum_version' => env('KORYHUB_CLI_MINIMUM_VERSION', '0.1.0'),
        'latest_version' => env('KORYHUB_CLI_LATEST_VERSION', '0.1.0'),
    ],

    /*
     * Journal d'audit (TRD §21) : champs notés comme modifiés, sans leur valeur,
     * pour qu'aucune donnée personnelle ni aucun secret n'y entre.
     */
    'audit' => [
        'redacted_fields' => [
            'name', 'email', 'password', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes',
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
