<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAccountEmails();
        $this->configureCliTokens();
    }

    /**
     * Jetons du CLI (TRD §7.2) : model en UUID ; un jeton révoqué est refusé comme un jeton
     * expiré (Sanctum contrôle déjà expires_at à chaque requête).
     */
    protected function configureCliTokens(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid && $token->revoked_at === null,
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Règle fixe pour tous (TRD §7.1, TEC-05) : 12 caractères au moins, refusé s'il figure
        // dans une fuite connue. La vérification des fuites interroge un service en ligne :
        // elle est coupée pendant les tests automatisés.
        Password::defaults(fn (): Password => Password::min(12)
            ->when(! app()->runningUnitTests(), fn (Password $rule) => $rule->uncompromised()),
        );
    }

    /**
     * E-mails de vérification de l'adresse et de réinitialisation du mot de passe, avec les textes
     * de lang/*\/mail.php, dans la langue du compte (User::preferredLocale).
     */
    protected function configureAccountEmails(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject(__('mail.verify_subject'))
            ->line(__('mail.verify_line'))
            ->action(__('mail.verify_action'), $url)
            ->line(__('mail.verify_outro')));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject(__('mail.reset_subject'))
            ->line(__('mail.reset_line'))
            ->action(__('mail.reset_action'), route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('mail.reset_expire', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('mail.reset_outro')));
    }
}
