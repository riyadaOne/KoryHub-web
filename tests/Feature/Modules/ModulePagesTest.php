<?php

use App\Models\Module;
use App\Models\User;
use App\Services\Modules\PublicationError;
use App\Services\Modules\Secrets\SecretScanner;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Fiche d'un kory (W03 bloc B, S-03 à S-06) : README sûr, fichiers aux octets exacts, versions,
 * images ; module privé ou sans version publiée : 404 pour les autres.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
});

test('S-03 rend le README sans HTML brut ni lien javascript', function () {
    $archive = TarBuilder::kory('@moussa/paiement');
    $archive = (new TarBuilder)
        ->file('kory.json', json_encode(['name' => '@moussa/paiement', 'version' => '1.0.0', 'license' => 'MIT', 'tags' => [], 'files' => ['src/A.php']]))
        ->file('README.md', "# Paiement\n\n## Problème résolu\n\n<script>alert(1)</script>\n\n[clic](javascript:alert(1))\n\n```php\n\$a = 1;\n```\n")
        ->file('src/A.php', '<?php');
    publishKory($this->author, $archive);

    $this->get('/@moussa/paiement')
        ->assertOk()
        ->assertSee('Problème résolu')
        ->assertSee('kory pull @moussa/paiement')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false);
});

test('un module privé répond 404 aux autres et s\'affiche pour son auteur', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'), options: ['visibility' => 'private']);

    $this->get('/@moussa/paiement')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/@moussa/paiement')->assertNotFound();
    $this->actingAs($this->author)->get('/@moussa/paiement')->assertOk()->assertSee(__('modules.visibility.private'));
});

test('un module sans version publiée répond 404 aux autres ; la version rejetée n\'est vue que par l\'auteur', function () {
    $scanner = new FakeSecretScanner;
    $this->app->instance(SecretScanner::class, $scanner);

    try {
        publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/c.php' => "SECRET=x\n"]));
    } catch (PublicationError) {
    }

    $this->get('/@moussa/paiement')->assertNotFound();
    $this->get('/@moussa/paiement/v/1.0.0')->assertNotFound();
    $this->actingAs($this->author)->get('/@moussa/paiement/v/1.0.0')
        ->assertOk()->assertSee('data-test="rejection-banner"', false)->assertSee('src/c.php');
});

test('S-06 montre le fichier choisi, coloré, avec ses octets exacts', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['src/PaymentService.php' => "<?php\r\nfinal class PaymentService {}\r\n"]));

    $response = $this->get('/@moussa/paiement/files?path=src/PaymentService.php')->assertOk()
        ->assertSee('src/PaymentService.php')
        ->assertSee('<span class="hl-type">PaymentService</span>', false);

    // Les octets gardés sont ceux envoyés (CRLF) : l'empreinte affichée est la leur.
    $response->assertSee(substr(hash('sha256', "<?php\r\nfinal class PaymentService {}\r\n"), 0, 12));
});

test('S-04 et S-05 : versions, empreinte complète, commande d\'installation', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $version = publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.1.0'));

    $this->get('/@moussa/paiement/versions')->assertOk()->assertSeeInOrder(['1.1.0', '1.0.0']);
    $this->get('/@moussa/paiement/v/1.1.0')->assertOk()
        ->assertSee($version->archive_sha256)
        ->assertSee('kory pull @moussa/paiement@1.1.0');
});

test('les images de la documentation sont servies avec leur type et nosniff ; un autre fichier non', function () {
    $png = "\x89PNG\r\n\x1a\n".str_repeat("\0", 16);
    publishKory($this->author, TarBuilder::kory('@moussa/paiement', '1.0.0', ['docs/flux.png' => $png, 'src/A.php' => '<?php']));

    $this->get('/@moussa/paiement/v/1.0.0/raw/docs/flux.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get('/@moussa/paiement/v/1.0.0/raw/src/A.php')->assertNotFound();
});

test('S-32 liste les modules de l\'auteur seulement', function () {
    publishKory($this->author, TarBuilder::kory('@moussa/paiement'));
    $other = User::factory()->create(['handle' => 'awa']);
    publishKory($other, TarBuilder::kory('@awa/wave'), 'wave');

    $this->actingAs($this->author)->get(route('modules.mine'))
        ->assertOk()->assertSee('@moussa/paiement')->assertDontSee('@awa/wave');
});
