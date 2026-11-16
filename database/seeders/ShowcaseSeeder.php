<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\WatchLevel;
use App\Models\Module;
use App\Models\Star;
use App\Models\User;
use App\Models\Watch;
use App\Services\Community\ReviewService;
use App\Services\Modules\DownloadService;
use App\Services\Search\ScoreCalculator;
use App\Services\Search\SearchText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Jeu vitrine de la démo en ligne (DON-09) : huit comptes réels et vérifiés, adresses
 * « boite+compte@domaine » tirées de KORYHUB_SHOWCASE_MAILBOX, puis les kory, avis, certifications,
 * signalements, modération et jetons de DemoModulesSeeder. Aucun compte admin n'est créé : la
 * modération et les certifications sont faites par l'admin réel, qui doit exister avant.
 * Aucun e-mail ne part pendant le seed. Jamais en production.
 */
class ShowcaseSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string}> handle, nom, pays, langue */
    public const ACCOUNTS = [
        ['moussa', 'Moussa Mahamat', 'TD', 'fr'],
        ['hassan-djibrine', 'Hassan Djibrine', 'TD', 'fr'],
        ['fatime-zakaria', 'Fatimé Zakaria', 'TD', 'ar'],
        ['awa-diop', 'Awa Diop', 'SN', 'fr'],
        ['wanjiru', 'Wanjiru Kamau', 'KE', 'en'],
        ['ines-mondlane', 'Inês Mondlane', 'MZ', 'pt'],
        ['chinedu-okafor', 'Chinedu Okafor', 'NG', 'en'],
        ['koffi-kouassi', 'Koffi Kouassi', 'CI', 'fr'],
    ];

    public function run(): void
    {
        $password = config('koryhub.demo.password');
        $mailbox = (string) config('koryhub.demo.showcase_mailbox');

        if (app()->isProduction() || blank($password)) {
            throw new RuntimeException('Jeu vitrine : KORYHUB_DEMO_PASSWORD manquant, ou environnement de production.');
        }
        if (filter_var($mailbox, FILTER_VALIDATE_EMAIL) === false || str_contains($mailbox, '+')) {
            throw new RuntimeException('Jeu vitrine : KORYHUB_SHOWCASE_MAILBOX doit être une adresse e-mail réelle, sans « + ».');
        }
        if (! User::query()->where('role', Role::Admin)->exists()) {
            throw new RuntimeException('Jeu vitrine : créez d\'abord le compte admin réel (php artisan koryhub:admin <email>).');
        }
        $handles = array_column(self::ACCOUNTS, 0);
        if (User::query()->whereIn('handle', $handles)->exists()) {
            throw new RuntimeException('Jeu vitrine : des comptes du jeu existent déjà ; il se lance sur une base sans eux.');
        }

        Notification::fake();
        Mail::fake();

        [$local, $domain] = explode('@', $mailbox, 2);
        foreach (self::ACCOUNTS as [$handle, $name, $country, $locale]) {
            User::factory()->create([
                'handle' => $handle,
                'name' => $name,
                'email' => "{$local}+{$handle}@{$domain}",
                'country' => $country,
                'locale' => $locale,
                'password' => $password,
            ]);
        }

        app(DemoUsersSeeder::class)->seedTokens(User::query()->where('handle', 'moussa')->sole());
        $this->call(DemoModulesSeeder::class);
        $this->community();
    }

    /**
     * Avis, favoris et abonnements des comptes qui ne publient pas, sur les kory publics encore
     * ouverts : chaque avis suit un vrai téléchargement (« A utilisé ce module »).
     */
    private function community(): void
    {
        $users = User::query()->whereIn('handle', ['awa-diop', 'ines-mondlane', 'chinedu-okafor', 'koffi-kouassi'])->get()->keyBy('handle');
        $mobileMoney = Module::query()->where('name', 'mobile-money-resilient')->sole();
        $offlineSync = Module::query()->where('name', 'offline-sync')->sole();

        $reviews = [
            [$offlineSync, 'ines-mondlane', 5, 'Usamos na nossa app de inquéritos em Maputo: os dados chegam ao servidor mesmo depois de dias sem rede.'],
            [$offlineSync, 'chinedu-okafor', 4, 'Solid queue design. I would like a conflict strategy option, but it saved us weeks on our field app in Kano.'],
            [$offlineSync, 'awa-diop', 4, 'Simple à brancher sur notre application de collecte. Le README explique bien les pièges de la synchronisation.'],
            [$mobileMoney, 'koffi-kouassi', 5, 'Branché sur Moov Money à Abidjan en une après-midi. Plus de double débit quand le réseau coupe.'],
            [$mobileMoney, 'chinedu-okafor', 4, 'Clean PaymentGateway interface. Adding a new operator was straightforward.'],
        ];

        foreach ($reviews as [$module, $handle, $rating, $comment]) {
            $user = $users[$handle];
            RateLimiter::clear('downloads:user:'.$user->id);
            app(DownloadService::class)->download($module->latestVersion, $user, 'showcase');
            app(ReviewService::class)->save($module, $user, $rating, $comment);
        }

        foreach ([[$offlineSync, 'koffi-kouassi'], [$offlineSync, 'ines-mondlane'], [$mobileMoney, 'chinedu-okafor'], [$mobileMoney, 'koffi-kouassi']] as [$module, $handle]) {
            Star::query()->firstOrCreate(['user_id' => $users[$handle]->id, 'module_id' => $module->id]);
        }
        Watch::query()->updateOrCreate(['user_id' => $users['koffi-kouassi']->id, 'module_id' => $offlineSync->id], ['level' => WatchLevel::Releases]);
        Watch::query()->updateOrCreate(['user_id' => $users['ines-mondlane']->id, 'module_id' => $offlineSync->id], ['level' => WatchLevel::All]);

        Module::query()->each(fn (Module $module) => app(SearchText::class)->refresh($module));
        app(ScoreCalculator::class)->refreshAll();
    }
}
