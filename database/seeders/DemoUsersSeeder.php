<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
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
        $password = env('KORYHUB_DEMO_PASSWORD');

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
    }
}
