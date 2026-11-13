<?php

use App\Enums\ComponentKind;
use App\Enums\LinkKind;
use App\Enums\VersionStatus;
use App\Models\Module;
use App\Models\User;
use App\Services\Modules\PublicationError;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Architecture et prérequis (W03 bloc C, F-REG-22, F-REG-24, F-REG-27, TRD §22).
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
});

const KORY_FILES = [
    'src/PaymentService.php' => '<?php final class PaymentService {}',
    'src/Contracts/PaymentGateway.php' => '<?php interface PaymentGateway {}',
    'src/Gateways/AirtelMoneyGateway.php' => '<?php final class AirtelMoneyGateway implements PaymentGateway {}',
];

function architecture(array $override = []): array
{
    return array_merge([
        'requires' => [
            ['ecosystem' => 'composer', 'name' => 'php', 'version' => '>=8.3'],
            ['ecosystem' => 'composer', 'name' => 'laravel/framework', 'version' => '^13.0'],
        ],
        'architecture' => [
            'components' => [
                ['id' => 'PaymentService', 'file' => 'src/PaymentService.php', 'kind' => 'class'],
                ['id' => 'PaymentGateway', 'file' => 'src/Contracts/PaymentGateway.php', 'kind' => 'interface'],
                ['id' => 'AirtelMoneyGateway', 'file' => 'src/Gateways/AirtelMoneyGateway.php', 'kind' => 'class'],
            ],
            'links' => [
                ['from' => 'PaymentService', 'to' => 'PaymentGateway', 'kind' => 'uses'],
                ['from' => 'AirtelMoneyGateway', 'to' => 'PaymentGateway', 'kind' => 'implements'],
            ],
        ],
    ], $override);
}

test('l\'architecture et les prérequis sont copiés dans leurs tables à la publication', function () {
    $version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', KORY_FILES, architecture()));

    expect($version->components()->pluck('key')->all())->toBe(['PaymentService', 'PaymentGateway', 'AirtelMoneyGateway'])
        ->and($version->components()->where('key', 'PaymentGateway')->sole()->kind)->toBe(ComponentKind::Interface)
        ->and($version->links()->count())->toBe(2)
        ->and($version->links()->where('kind', LinkKind::Implements)->sole()->from->key)->toBe('AirtelMoneyGateway')
        ->and($version->requirements()->pluck('name')->all())->toBe(['laravel/framework', 'php']);
});

test('une architecture invalide rejette la version', function (array $manifest, string $field) {
    try {
        publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', KORY_FILES, $manifest));
        $this->fail('La publication aurait dû être refusée.');
    } catch (PublicationError $error) {
        expect($error->errorCode)->toBe('manifest_invalid')
            ->and($error->replace['field'])->toStartWith($field);
    }

    expect(Module::sole()->versions()->sole()->status)->toBe(VersionStatus::Rejected);
})->with([
    'fichier hors du kory' => [['architecture' => ['components' => [['id' => 'X', 'file' => 'src/Absent.php', 'kind' => 'class']], 'links' => []]], 'architecture.components.0'],
    'type inconnu' => [['architecture' => ['components' => [['id' => 'X', 'file' => 'src/PaymentService.php', 'kind' => 'struct']], 'links' => []]], 'architecture.components.0'],
    'identifiant non permis' => [['architecture' => ['components' => [['id' => 'x"]; click', 'file' => 'src/PaymentService.php', 'kind' => 'class']], 'links' => []]], 'architecture.components.0'],
    'lien vers un composant non déclaré' => [['architecture' => ['components' => [['id' => 'A', 'file' => 'src/PaymentService.php', 'kind' => 'class']], 'links' => [['from' => 'A', 'to' => 'B', 'kind' => 'uses']]]], 'architecture.links.0'],
    'lien vers lui-même' => [['architecture' => ['components' => [['id' => 'A', 'file' => 'src/PaymentService.php', 'kind' => 'class']], 'links' => [['from' => 'A', 'to' => 'A', 'kind' => 'uses']]]], 'architecture.links.0'],
    'trop de composants' => [['architecture' => ['components' => array_map(fn ($i) => ['id' => "C{$i}", 'file' => 'src/PaymentService.php', 'kind' => 'class'], range(1, 51)), 'links' => []]], 'architecture.components'],
    'prérequis en double' => [['requires' => [['name' => 'php'], ['name' => 'php']]], 'requires.1'],
]);

test('S-14 : diagramme construit par le serveur (n1, n2…), liste équivalente, prérequis dans la colonne', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', KORY_FILES, architecture()));

    $this->get('/@moussa/paiement/architecture')
        ->assertOk()
        ->assertSee('flowchart LR')
        ->assertSee('n1["PaymentService"]')
        ->assertSee('n2{{"PaymentGateway"}}')
        ->assertSee('data-test="architecture-list"', false)
        ->assertSeeInOrder(['AirtelMoneyGateway', __('modules.architecture.links.implements'), 'PaymentGateway']);

    $this->get('/@moussa/paiement')->assertSee('data-test="requirements"', false)->assertSee('laravel/framework ^13.0');
});

test('S-14 sans architecture déclarée : message, pas de diagramme', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));

    $this->get('/@moussa/paiement/architecture')
        ->assertOk()
        ->assertSee('data-test="no-architecture"', false)
        ->assertDontSee('data-architecture', false);
});
