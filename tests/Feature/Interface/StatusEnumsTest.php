<?php

use App\Enums\HasStatusBadge;
use App\Enums\Locale;
use App\Enums\StatusColor;
use App\Exceptions\InvalidTransition;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/*
 * Enums de statut : libellé traduit et couleur dans l'enum (UXUI §8, T-01).
 */

function fakeStatus(string $label, StatusColor $color): HasStatusBadge
{
    return new class($label, $color) implements HasStatusBadge
    {
        public function __construct(private string $text, private StatusColor $tone) {}

        public function label(): string
        {
            return $this->text;
        }

        public function color(): StatusColor
        {
            return $this->tone;
        }
    };
}

test('chaque famille de couleur a une couleur de badge', function (StatusColor $color) {
    expect($color->fluxColor())->toBeIn(['zinc', 'blue', 'amber', 'green', 'red']);
})->with(StatusColor::cases());

test('chaque enum de statut a un libellé dans les 4 langues', function () {
    $enums = collect(File::files(app_path('Enums')))
        ->map(fn ($file) => 'App\\Enums\\'.$file->getFilenameWithoutExtension())
        ->filter(fn (string $class) => enum_exists($class) && is_subclass_of($class, HasStatusBadge::class));

    foreach ($enums as $enum) {
        foreach ($enum::cases() as $case) {
            foreach (Locale::values() as $locale) {
                app()->setLocale($locale);

                expect($case->label())
                    ->not->toBeEmpty()
                    ->not->toMatch('/^[a-z_]+\.[a-z_.]+$/', "{$enum}::{$case->name} n'est pas traduit en {$locale}.");
            }
        }
    }

    expect(true)->toBeTrue();
});

test('une transition refusée donne un message traduit', function () {
    app()->setLocale('fr');

    $exception = new InvalidTransition(fakeStatus('Publiée', StatusColor::Success), fakeStatus('Soumise', StatusColor::Info));

    expect($exception->getMessage())->toBe("Ce changement n'est pas permis : de Publiée à Soumise.");
});

test('le badge de statut affiche le libellé et la couleur de l\'enum', function () {
    $html = Blade::render('<x-status-badge :status="$status" />', [
        'status' => fakeStatus('Dépréciée', StatusColor::Warning),
    ]);

    expect($html)->toContain('Dépréciée')->toContain('amber');
});
