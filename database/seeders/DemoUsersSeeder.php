<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TokenOrigin;
use App\Models\User;
use App\Services\Cli\CliTokenService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Comptes de démonstration (DON-09, BACKEND-SCHEMA §9.1) : une vingtaine de développeurs de
 * plusieurs pays africains, surtout tchadiens, et un admin RiyadaOne. Jamais en production.
 * Le mot de passe vient de KORYHUB_DEMO_PASSWORD (.env), jamais du dépôt.
 */
class DemoUsersSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string}> handle, nom, pays, langue */
    private const DEVELOPERS = [
        ['moussa', 'Moussa Mahamat', 'TD', 'fr'],
        ['hassan-djibrine', 'Hassan Djibrine', 'TD', 'fr'],
        ['fatime-zakaria', 'Fatimé Zakaria', 'TD', 'ar'],
        ['abdelkerim-oumar', 'Abdelkerim Oumar', 'TD', 'ar'],
        ['halime-adoum', 'Halimé Adoum', 'TD', 'fr'],
        ['brahim-mahamat', 'Brahim Mahamat Saleh', 'TD', 'fr'],
        ['ngarlem-djimadoum', 'Ngarlem Djimadoum', 'TD', 'fr'],
        ['ache-ibrahim', 'Aché Ibrahim', 'TD', 'ar'],
        ['remadji-nadjilem', 'Remadji Nadjilem', 'TD', 'fr'],
        ['youssouf-abakar', 'Youssouf Abakar', 'TD', 'fr'],
        ['awa-diop', 'Awa Diop', 'SN', 'fr'],
        ['cheikh-ndiaye', 'Cheikh Ndiaye', 'SN', 'fr'],
        ['jp-ngono', 'Jean-Paul Ngono', 'CM', 'fr'],
        ['aminatou-bello', 'Aminatou Bello', 'CM', 'fr'],
        ['wanjiru', 'Wanjiru Kamau', 'KE', 'en'],
        ['otieno-ochieng', 'Otieno Ochieng', 'KE', 'en'],
        ['chinedu-okafor', 'Chinedu Okafor', 'NG', 'en'],
        ['koffi-kouassi', 'Koffi Kouassi', 'CI', 'fr'],
        ['ines-mondlane', 'Inês Mondlane', 'MZ', 'pt'],
        ['youssef-elamrani', 'Youssef El Amrani', 'MA', 'ar'],
    ];

    public function run(): void
    {
        $password = config('koryhub.demo.password');

        if (blank($password) || app()->isProduction()) {
            throw new RuntimeException('Comptes de démonstration : KORYHUB_DEMO_PASSWORD manquant, ou environnement de production.');
        }

        foreach (self::DEVELOPERS as [$handle, $name, $country, $locale]) {
            User::factory()->create([
                'handle' => $handle,
                'name' => $name,
                'email' => "{$handle}@demo.koryhub.test",
                'country' => $country,
                'locale' => $locale,
                'password' => $password,
            ]);
        }

        User::factory()->create([
            'handle' => 'achta-riyadaone',
            'name' => 'Achta Abakar',
            'email' => 'achta@demo.koryhub.test',
            'country' => 'TD',
            'locale' => 'fr',
            'password' => $password,
            'role' => Role::Admin,
        ]);

        User::factory()->unverified()->create([
            'handle' => 'nouveau-dev',
            'name' => 'Djibrine Nouveau',
            'email' => 'nouveau@demo.koryhub.test',
            'country' => 'TD',
            'locale' => 'fr',
            'password' => $password,
        ]);

        $this->seedTokens(User::query()->where('handle', 'moussa')->sole());
    }

    /**
     * Jetons du CLI de démonstration (W02) : un de chaque statut sur le compte @moussa, écrits
     * directement pour n'envoyer aucun e-mail. Leurs valeurs en clair ne sont jamais connues.
     * Repris par ShowcaseSeeder.
     */
    public function seedTokens(User $user): void
    {
        $tokens = [
            ['kory · Windows', TokenOrigin::CliLogin, now()->subMinutes(2), now()->addDays(90), null, now()->subMinute()],
            ['CI GitHub', TokenOrigin::Site, now()->subMonths(3), null, null, now()->subHours(4)],
            ['Serveur de recette', TokenOrigin::Site, now()->subDays(25), now()->addDays(5), null, now()->subDay()],
            ['kory · Linux', TokenOrigin::CliLogin, now()->subMonths(6), now()->addMonths(6), now()->subMonths(2), now()->subMonths(2)],
            ['Essai', TokenOrigin::Site, now()->subMonths(4), now()->subMonths(3), null, null],
        ];

        foreach ($tokens as [$name, $origin, $created, $expires, $revoked, $used]) {
            $user->tokens()->forceCreate([
                'name' => $name,
                'token' => hash('sha256', CliTokenService::generate()),
                'abilities' => ['*'],
                'origin' => $origin,
                'expires_at' => $expires,
                'revoked_at' => $revoked,
                'last_used_at' => $used,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }
    }
}
