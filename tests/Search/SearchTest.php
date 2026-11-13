<?php

use App\Enums\VersionStatus;
use App\Models\Category;
use App\Models\DownloadStat;
use App\Models\Module;
use App\Models\ModuleDownload;
use App\Models\Review;
use App\Models\User;
use App\Services\Modules\ModuleLifecycle;
use App\Services\Modules\Secrets\SecretScanner;
use App\Services\Search\ModuleSearch;
use App\Services\Search\ScoreCalculator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeSecretScanner;
use Tests\Support\TarBuilder;

/*
 * Recherche et score (W05 bloc B, F-RCH-01 à F-RCH-05, F-RCH-08, F-RCH-17, TRD §14). Tables
 * vidées après chaque test (pas de transaction) : l'index plein texte voit les lignes.
 */

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->app->instance(SecretScanner::class, new FakeSecretScanner);
    $this->author = User::factory()->create(['handle' => 'moussa']);
});

function kory(User $author, string $name, string $description, string $readme = '', string $visibility = 'public'): Module
{
    return publishKory($author, TarBuilder::kory("@{$author->handle}/{$name}", '1.0.0', ['src/A.php' => "<?php\n"], ['description' => $description, 'tags' => []], $readme ?: "# {$name}\n"), $name, ['visibility' => $visibility])->module->fresh();
}

function search(array $filters): array
{
    return collect(app(ModuleSearch::class)->search($filters, 20)->items())->map(fn (Module $m) => $m->name)->all();
}

test('mots cherchés dans la description, le README et le nom ; module privé jamais trouvé', function () {
    kory($this->author, 'mobile-money', 'Encaissement Mobile Money résistant aux coupures.');
    kory($this->author, 'offline-sync', 'Synchronisation de terrain.', "# Sync\n\nFile d'attente hors ligne avec reprise automatique.\n");
    kory($this->author, 'brouillon', 'Encaissement Mobile Money brouillon.', visibility: 'private');

    expect(search(['q' => 'encaissement']))->toBe(['mobile-money'])
        ->and(search(['q' => 'reprise automatique']))->toBe(['offline-sync'])
        ->and(search(['q' => 'offline-sync']))->toBe(['offline-sync'])
        ->and(search(['q' => 'brouillon']))->toBe([]);
});

test('recherche en arabe ; requête piégée sans effet', function () {
    kory($this->author, 'sms-ar', 'إرسال الرسائل النصية عند انقطاع الإنترنت');

    expect(search(['q' => 'الرسائل']))->toBe(['sms-ar'])
        ->and(search(['q' => "'; DROP TABLE modules; --"]))->toBe([])
        ->and(search(['q' => 'mobile" OR 1=1 --']))->toBe([])
        ->and(Module::count())->toBe(1);
});

test('filtres catégorie et langage ; version retirée seule : absent ; nombre par catégorie', function () {
    $payments = Category::create(['slug' => 'payments', 'position' => 1, 'is_active' => true]);
    $m = kory($this->author, 'mobile-money', 'Paiement mobile.');
    app(ModuleLifecycle::class)->updateSettings($m, ['description' => $m->description, 'category_id' => $payments->id, 'tags' => ['airtel'], 'visibility' => $m->visibility], $this->author);
    kory($this->author, 'retire', 'Paiement retiré.')->versions()->update(['status' => VersionStatus::Removed]);

    expect(search(['q' => 'paiement', 'category' => 'payments']))->toBe(['mobile-money'])
        ->and(search(['q' => 'airtel']))->toBe(['mobile-money'])
        ->and(search(['q' => 'paiement', 'language' => 'dart']))->toBe([])
        ->and(app(ModuleSearch::class)->categoryCounts(['q' => 'paiement']))->toBe([$payments->id => 1]);
});

test('score : ordre attendu sur des valeurs fixes ; note non vérifiée sans effet ; avis masqués sans bonus', function () {
    $popular = kory($this->author, 'populaire', 'A');
    $rated = kory($this->author, 'bien-note', 'B');
    $hidden = kory($this->author, 'masque', 'C');
    $old = kory($this->author, 'ancien', 'D');
    $old->latestVersion->forceFill(['published_at' => now()->subDays(360)])->save();

    DownloadStat::create(['module_id' => $popular->id, 'version_id' => $popular->latest_version_id, 'day' => now()->subDays(3)->toDateString(), 'count' => 500]);
    DownloadStat::create(['module_id' => $popular->id, 'version_id' => $popular->latest_version_id, 'day' => now()->subDays(200)->toDateString(), 'count' => 9000]);
    foreach ([$rated, $hidden] as $module) {
        foreach (range(1, 6) as $i) {
            $user = User::factory()->create();
            ModuleDownload::create(['user_id' => $user->id, 'module_id' => $module->id, 'version_id' => $module->latest_version_id, 'first_at' => now(), 'last_at' => now()]);
            Review::create(['module_id' => $module->id, 'user_id' => $user->id, 'rating' => 5]);
        }
    }
    Review::create(['module_id' => $old->id, 'user_id' => User::factory()->create()->id, 'rating' => 5]); // non vérifiée
    $hidden->forceFill(['reviews_hidden' => true])->save();

    $calculator = app(ScoreCalculator::class);
    $calculator->refreshAll();
    $scores = Module::query()->pluck('score', 'name')->map(fn ($s) => round((float) $s, 3))->all();

    // populaire : log(501) + 0,7 (note a priori 3,5/5) + 0,5 ; bien-note : (5×3,5 + 30)/11/5 = 0,864 + 0,5
    expect($scores['populaire'])->toBe(round(log(501) + 0.7 + 0.5, 3))
        ->and($scores['bien-note'])->toBe(round((5 * 3.5 + 30) / 11 / 5 + 0.5, 3))
        ->and($scores['masque'])->toBe(0.5)
        ->and($scores['ancien'])->toBe(round(0.7 + 0.5 * 0.5 ** 4, 3));

    expect(search(['q' => '']))->toBe(['populaire', 'bien-note', 'ancien', 'masque']);
});

test('GET /v1/search et kory info : résultats, auteur, architecture', function () {
    $this->author->forceFill(['country' => 'TD'])->save();
    publishKory($this->author, TarBuilder::kory('@moussa/mobile-money', '1.0.0', ['src/A.php' => "<?php\nfinal class A {}\n", 'src/B.php' => "<?php\nfinal class B {}\n"], [
        'description' => 'Encaissement Mobile Money.',
        'architecture' => ['components' => [['id' => 'A', 'file' => 'src/A.php', 'kind' => 'class'], ['id' => 'B', 'file' => 'src/B.php', 'kind' => 'class']], 'links' => [['from' => 'A', 'to' => 'B', 'kind' => 'uses']]],
    ]), 'mobile-money');
    $api = 'http://'.config('koryhub.domains.api').'/v1';

    $this->getJson($api.'/search?q=encaissement&limit=5')->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('results.0.name', '@moussa/mobile-money')
        ->assertJsonPath('results.0.latest_version', '1.0.0');
    $this->getJson($api.'/search?limit=500')->assertUnprocessable();

    $this->getJson($api.'/modules/moussa/mobile-money')->assertOk()->assertJsonPath('author', ['handle' => 'moussa', 'country' => 'TD']);
    $this->getJson($api.'/modules/moussa/mobile-money/versions/1.0.0')->assertOk()
        ->assertJsonPath('architecture.links.0', ['from' => 'A', 'to' => 'B', 'kind' => 'uses']);
});

test('S-02 : filtres dans l\'adresse, résultats, aucun résultat', function () {
    kory($this->author, 'mobile-money', 'Encaissement Mobile Money.');

    $this->get(route('search', ['q' => 'encaissement']))->assertOk()->assertSee('@moussa/mobile-money');
    $this->get(route('search', ['q' => 'wave senegal']))->assertOk()->assertSee(__('search.empty', ['q' => 'wave senegal']));
});
