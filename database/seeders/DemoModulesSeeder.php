<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\DownloadService;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\Secrets\SecretScanner;
use App\Services\Modules\VersionPublisher;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PharData;
use RuntimeException;

/**
 * Kory de démonstration (W03.10, BACKEND-SCHEMA §9.1) : publiés par le vrai service de publication,
 * à partir d'archives construites ici. Contenu écrit pour la démonstration : la recherche de secrets
 * est remplacée par un contrôle vide et aucun e-mail ne part. Jamais en production.
 */
class DemoModulesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Kory de démonstration : jamais en production.');
        }

        Notification::fake();
        app()->instance(SecretScanner::class, new class implements SecretScanner
        {
            public function scan(string $directory): array
            {
                return [];
            }
        });

        $moussa = User::query()->where('handle', 'moussa')->sole();
        $hassan = User::query()->where('handle', 'hassan-djibrine')->sole();
        $fatime = User::query()->where('handle', 'fatime-zakaria')->sole();

        $this->mobileMoney($moussa);
        $this->offlineSync($hassan);
        $this->smsFallback($fatime);
        $this->publish($moussa, 'recu-qr', '0.1.0', ['src/ReceiptQr.php' => "<?php\n\nfinal class ReceiptQr\n{\n}\n"], 'Reçus vérifiables par QR code (brouillon).', private: true);

        // Catégories de départ (CategoriesSeeder), si elles existent.
        foreach (['mobile-money-resilient' => 'payments', 'offline-sync' => 'offline-sync', 'sms-ussd-fallback' => 'messaging'] as $module => $slug) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category !== null) {
                Module::query()->where('name', $module)->update(['category_id' => $category->id]);
            }
        }

        $this->downloads($hassan);
    }

    /**
     * Téléchargements (W04) par le vrai service : Hassan a installé mobile-money-resilient 1.0.1
     * (dépréciée) puis 1.1.0 (1.2.0 disponible), visibles sur S-46 ; quelques téléchargements sans
     * compte. Le module devient « téléchargé par un tiers » : il ne repasse plus en Privé.
     */
    private function downloads(User $hassan): void
    {
        $downloads = app(DownloadService::class);
        $module = Module::query()->where('name', 'mobile-money-resilient')->sole();
        $version = fn (string $number) => $module->versions()->where('version', $number)->sole();

        RateLimiter::clear('downloads:user:'.$hassan->id);
        RateLimiter::clear('downloads:ip:demo');

        $downloads->download($version('1.0.1'), $hassan, 'demo');
        foreach (range(1, 3) as $i) {
            $downloads->download($version('1.1.0'), $hassan, 'demo');
        }
        foreach (range(1, 5) as $i) {
            $downloads->download($version('1.2.0'), null, 'demo');
        }
        $offline = Module::query()->where('name', 'offline-sync')->sole()->versions()->sole();
        $downloads->download($offline, null, 'demo');
    }

    /** @moussa/mobile-money-resilient : la maquette de référence, avec son architecture et des versions dépréciées. */
    private function mobileMoney(User $author): void
    {
        $files = [
            'src/PaymentService.php' => "<?php\n\nfinal class PaymentService\n{\n    public function __construct(private PaymentGateway \$gateway, private PendingPaymentQueue \$queue) {}\n\n    public function pay(string \$phone, int \$amountXaf, string \$orderId): PaymentStatus\n    {\n        // La clé dépend de la commande, pas du moment : un nouvel essai réutilise la même.\n        \$key = 'order-'.\$orderId;\n\n        return \$this->gateway->charge(\$phone, \$amountXaf, \$key)->status();\n    }\n}\n",
            'src/Contracts/PaymentGateway.php' => "<?php\n\ninterface PaymentGateway\n{\n    public function charge(string \$phone, int \$amount, string \$idempotencyKey): GatewayResponse;\n\n    public function verifySignature(string \$payload, string \$signature): bool;\n}\n",
            'src/Gateways/AirtelMoneyGateway.php' => "<?php\n\nfinal class AirtelMoneyGateway implements PaymentGateway\n{\n    // Adresses et champs réels : documentation de l'opérateur ; ex. https://example.com/webhooks/airtel\n}\n",
            'src/Gateways/MoovMoneyGateway.php' => "<?php\n\nfinal class MoovMoneyGateway implements PaymentGateway\n{\n}\n",
            'src/PaymentStatus.php' => "<?php\n\nenum PaymentStatus: string\n{\n    case Pending = 'pending';\n    case Confirmed = 'confirmed';\n    case Failed = 'failed';\n}\n",
            'src/PendingPaymentQueue.php' => "<?php\n\nfinal class PendingPaymentQueue\n{\n    public function confirm(string \$key): void {}\n}\n",
            'src/RetryPendingPayments.php' => "<?php\n\nfinal class RetryPendingPayments\n{\n    public function __invoke(PendingPaymentQueue \$queue, PaymentService \$payments): void {}\n}\n",
            'src/WebhookHandler.php' => "<?php\n\nfinal class WebhookHandler\n{\n    public function __construct(private PaymentGateway \$gateway, private PendingPaymentQueue \$queue) {}\n}\n",
        ];
        $architecture = [
            'components' => [
                ['id' => 'PaymentService', 'file' => 'src/PaymentService.php', 'kind' => 'class'],
                ['id' => 'PaymentGateway', 'file' => 'src/Contracts/PaymentGateway.php', 'kind' => 'interface'],
                ['id' => 'AirtelMoneyGateway', 'file' => 'src/Gateways/AirtelMoneyGateway.php', 'kind' => 'class'],
                ['id' => 'MoovMoneyGateway', 'file' => 'src/Gateways/MoovMoneyGateway.php', 'kind' => 'class'],
                ['id' => 'PaymentStatus', 'file' => 'src/PaymentStatus.php', 'kind' => 'enum'],
                ['id' => 'PendingPaymentQueue', 'file' => 'src/PendingPaymentQueue.php', 'kind' => 'class'],
                ['id' => 'RetryPendingPayments', 'file' => 'src/RetryPendingPayments.php', 'kind' => 'class'],
                ['id' => 'WebhookHandler', 'file' => 'src/WebhookHandler.php', 'kind' => 'class'],
            ],
            'links' => [
                ['from' => 'PaymentService', 'to' => 'PaymentGateway', 'kind' => 'uses'],
                ['from' => 'PaymentService', 'to' => 'PendingPaymentQueue', 'kind' => 'uses'],
                ['from' => 'PaymentService', 'to' => 'PaymentStatus', 'kind' => 'uses'],
                ['from' => 'AirtelMoneyGateway', 'to' => 'PaymentGateway', 'kind' => 'implements'],
                ['from' => 'MoovMoneyGateway', 'to' => 'PaymentGateway', 'kind' => 'implements'],
                ['from' => 'RetryPendingPayments', 'to' => 'PendingPaymentQueue', 'kind' => 'uses'],
                ['from' => 'RetryPendingPayments', 'to' => 'PaymentService', 'kind' => 'calls'],
                ['from' => 'WebhookHandler', 'to' => 'PaymentGateway', 'kind' => 'uses'],
                ['from' => 'WebhookHandler', 'to' => 'PendingPaymentQueue', 'kind' => 'calls'],
            ],
        ];
        $requires = [
            ['ecosystem' => 'composer', 'name' => 'php', 'version' => '>=8.3'],
            ['ecosystem' => 'composer', 'name' => 'laravel/framework', 'version' => '^13.0'],
        ];
        $description = 'Encaissement Mobile Money résistant aux coupures réseau, sans double débit.';

        foreach (['1.0.0', '1.0.1', '1.1.0', '1.2.0'] as $number) {
            $this->publish($author, 'mobile-money-resilient', $number, $files, $description, ['architecture' => $architecture, 'requires' => $requires], tags: ['mobile-money', 'airtel-money', 'moov-money', 'offline', 'laravel']);
        }

        $module = Module::query()->where('name', 'mobile-money-resilient')->sole();
        $lifecycle = app(ModuleLifecycle::class);
        foreach (['1.0.0' => 'Clé d’idempotence par essai : double débit possible. Passez en 1.1.0 ou plus.', '1.0.1' => 'Même problème que 1.0.0.'] as $number => $message) {
            $lifecycle->deprecate($module->versions()->where('version', $number)->sole(), $message, $author);
        }
    }

    private function offlineSync(User $author): void
    {
        $this->publish($author, 'offline-sync', '2.0.0', [
            'src/sync.service.ts' => "import { OfflineQueue } from './queue';\n\nexport class SyncService {\n  constructor(private queue: OfflineQueue) {}\n}\n",
            'src/queue.ts' => "export class OfflineQueue {\n}\n",
        ], 'Synchronisation hors ligne pour les applications de terrain.', [
            'architecture' => [
                'components' => [
                    ['id' => 'SyncService', 'file' => 'src/sync.service.ts', 'kind' => 'class'],
                    ['id' => 'OfflineQueue', 'file' => 'src/queue.ts', 'kind' => 'class'],
                ],
                'links' => [['from' => 'SyncService', 'to' => 'OfflineQueue', 'kind' => 'uses']],
            ],
        ], tags: ['offline', 'typescript'], language: 'typescript');
    }

    private function smsFallback(User $author): void
    {
        $this->publish($author, 'sms-ussd-fallback', '0.3.1', [
            'lib/sms_fallback.dart' => "class SmsFallback {\n}\n",
        ], 'Repli SMS et USSD quand les données mobiles sont coupées.', tags: ['sms', 'ussd'], language: 'dart');

        app(ModuleLifecycle::class)->archive(Module::query()->where('name', 'sms-ussd-fallback')->sole(), $author);
    }

    /**
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $tags
     */
    private function publish(User $author, string $name, string $version, array $files, string $description, array $extra = [], bool $private = false, array $tags = [], string $language = 'php'): void
    {
        $dir = storage_path('app/private/demo/'.Str::uuid7());
        mkdir($dir, 0700, true);

        $manifest = array_merge([
            'name' => "@{$author->handle}/{$name}",
            'version' => $version,
            'description' => $description,
            'license' => 'MIT',
            'language' => $language,
            'tags' => $tags,
            'files' => array_keys($files),
        ], $extra);

        $tar = new PharData($dir.'/kory.tar');
        $tar->addFromString('kory.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $tar->addFromString('README.md', "# {$name}\n\n## Problème résolu\n\n{$description}\n\n## Pièges évités\n\n1. Kory de démonstration.\n");
        foreach ($files as $path => $content) {
            $tar->addFromString($path, $content);
        }
        $tar->compress(\Phar::GZ);
        unset($tar);

        $archive = $dir.'/kory.tar.gz';
        app(VersionPublisher::class)->publish($author, $author->handle, $name, new UploadedFile($archive, 'kory.tar.gz', null, null, true), hash_file('sha256', $archive), [
            'visibility' => $private ? 'private' : 'public',
            'rights_certified' => true,
            'notice_accepted' => true,
        ]);

        File::deleteDirectory($dir);
    }
}
