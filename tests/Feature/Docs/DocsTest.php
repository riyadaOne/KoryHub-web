<?php

use App\Enums\Locale;
use App\Support\Docs\DocsRepository;
use Illuminate\Support\Facades\File;

/*
 * Documentation et pages publiques (W09, S-10 à S-13, F-REG-01, F-REG-34, FLX-12) : chaque page
 * dans chaque langue, navigation, 404, langue de secours, arabe de droite à gauche avec le code de
 * gauche à droite, liens internes valides ; scripts d'installation du CLI (K05, TRD §18.1).
 */

beforeEach(function () {
    $this->docs = 'http://'.config('koryhub.domains.docs');
});

test('chaque page de la documentation s\'affiche dans les 4 langues, avec navigation, sommaire et pages voisines', function () {
    foreach (Locale::cases() as $locale) {
        $this->get($this->docs.'/'.$locale->value)->assertOk()->assertSee('data-test="docs-home"', false)
            ->assertSee(config('koryhub.cli.install_windows'));

        foreach (DocsRepository::slugs() as $slug) {
            $page = DocsRepository::docs()->find($locale, $slug);
            expect($page)->not->toBeNull();
            $this->get($this->docs.'/'.$locale->value.'/'.$slug)->assertOk()
                ->assertSee($page->title)->assertSee('data-test="docs-nav"', false)->assertSee('data-test="docs-pager"', false);
        }
    }
    $this->get($this->docs.'/fr/publish')->assertSee('data-test="docs-toc"', false)->assertSee('id="preparer"', false);
});

test('page inconnue ou langue inconnue : 404', function () {
    $this->get($this->docs.'/fr/inexistante')->assertNotFound();
    $this->get($this->docs.'/de/publish')->assertNotFound();
    $this->get($this->docs.'/fr/../../.env')->assertNotFound();
});

test('page absente dans une langue : anglais, puis français, avec un bandeau', function () {
    $root = storage_path('framework/testing/docs-'.uniqid());
    File::ensureDirectoryExists($root.'/fr');
    File::ensureDirectoryExists($root.'/en');
    File::put($root.'/fr/seule.md', "# Seule en français\n\nTexte.\n");
    File::put($root.'/en/both.md', "# Both\n\n## A section\n\n```\nkory push\n```\n");
    $repository = new DocsRepository($root);

    $fr = $repository->find(Locale::Portuguese, 'seule');
    $en = $repository->find(Locale::Arabic, 'both');
    expect($fr->locale)->toBe(Locale::French)->and($fr->fallback)->toBeTrue()
        ->and($en->locale)->toBe(Locale::English)->and($en->headings)->toBe([['id' => 'a-section', 'text' => 'A section']])
        ->and((string) $en->html)->toContain('<pre dir="ltr"')
        ->and($repository->find(Locale::English, 'absente'))->toBeNull();
    File::deleteDirectory($root);
});

test('arabe : page de droite à gauche, blocs de code de gauche à droite', function () {
    $this->get($this->docs.'/ar/publish')->assertOk()
        ->assertSee('dir="rtl"', false)->assertSee('<pre dir="ltr"', false);
});

test('liens internes de la documentation valides', function () {
    $slugs = DocsRepository::slugs();
    foreach (Locale::cases() as $locale) {
        foreach (File::glob(resource_path('docs/'.$locale->value.'/*.md')) as $file) {
            preg_match_all('/\]\(([^)]+)\)/', File::get($file), $links);
            foreach ($links[1] as $link) {
                if (! preg_match('#^(https?://|\#)#', $link)) {
                    expect(in_array(explode('#', $link)[0], $slugs, true))->toBeTrue(basename($file).' : '.$link);
                }
            }
        }
        expect(collect(File::glob(resource_path('docs/'.$locale->value.'/*.md')))->map(fn ($f) => basename($f, '.md'))->sort()->values()->all())
            ->toBe(collect($slugs)->sort()->values()->all());
    }
});

test('pages publiques S-10 à S-12 et liens du pied de page', function () {
    foreach (['governance', 'terms', 'privacy'] as $page) {
        $this->get(route($page))->assertOk()->assertSee('data-test="public-page"', false)
            ->assertSee('data-test="footer-links"', false)->assertSee(route('governance'));
    }
    $this->withCookie('locale', 'fr')->get(route('governance'))->assertSee('Gouvernance et transparence');
});

test('scripts d\'installation servis en texte, avec la version publiée et l\'adresse des exécutables', function () {
    config(['koryhub.cli.latest_version' => '0.7.0', 'koryhub.cli.download_url' => 'https://cli.example.test/kory']);

    foreach (['install.windows' => '$Version = \'0.7.0\'', 'install.unix' => "VERSION='0.7.0'"] as $route => $line) {
        $response = $this->get(route($route))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        expect($response->getContent())->toContain($line)->toContain('https://cli.example.test/kory')
            ->not->toContain('__KORY_')->toContain('SHA');
    }
});
