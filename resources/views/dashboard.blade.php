{{--
    Tableau de bord du développeur (S-30, F-ADM-12, maquettes W08) : d'abord les actions à faire
    (e-mail à vérifier, complément de certification, jeton qui expire), puis notifications récentes,
    mes modules, mes certifications ; premiers pas tant qu'aucun module n'existe.
--}}
@php($user = auth()->user())
@php($complements = \App\Models\Certification::query()->where('requested_by', $user->id)->where('status', \App\Enums\CertificationStatus::ComplementRequired)->with(['module.author', 'version'])->get())
@php($expiring = $user->tokens()->usable()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(7))->orderBy('expires_at')->get())
@php($modules = $user->modules()->with('latestVersion')->latest('updated_at')->limit(5)->get())
@php($certifications = \App\Models\Certification::query()->where('requested_by', $user->id)->with(['module', 'version'])->latest('requested_at')->limit(3)->get())
@php($notifications = $user->notifications()->latest()->limit(5)->get())
<x-layouts::app :title="__('common.dashboard')">
    <div class="grid gap-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('account.dashboard.greeting', ['name' => $user->displayName()]) }}</flux:heading>
            <flux:text class="mt-1">{{ __('account.dashboard.subtitle') }}</flux:text>
        </div>

        @unless ($user->hasVerifiedEmail())
            <x-warning-banner :heading="__('account.dashboard.unverified')" data-test="unverified-banner">
                {{ __('account.dashboard.unverified_action') }}
                <flux:link :href="route('verification.notice')">{{ __('account.dashboard.resend') }}</flux:link>
            </x-warning-banner>
        @endunless

        {{-- Certification (W06) : complément demandé par l'équipe de modération. --}}
        @foreach ($complements as $pending)
            <x-warning-banner :heading="__('certification.dashboard.complement', ['module' => $pending->module->fullName(), 'version' => $pending->version->version])" data-test="complement-card">
                {{ __('certification.dashboard.complement_text') }}
                <flux:link :href="route('certifications.show', $pending)" wire:navigate>{{ __('certification.mine.respond') }}</flux:link>
            </x-warning-banner>
        @endforeach

        {{-- Jetons du CLI qui expirent dans 7 jours (W08). --}}
        @foreach ($expiring as $token)
            <x-warning-banner :heading="__('account.dashboard.token_expiring', ['token' => $token->name, 'date' => $token->expires_at->locale(app()->getLocale())->isoFormat('LL')])" data-test="token-expiring">
                <flux:link :href="route('tokens.create')" wire:navigate>{{ __('account.dashboard.new_token') }}</flux:link>
            </x-warning-banner>
        @endforeach

        @if ($modules->isEmpty())
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('account.dashboard.step_terminal') }}</flux:heading>
                    <flux:text>{{ __('account.dashboard.step_terminal_text') }}</flux:text>
                    <x-copy-command command="kory login" />
                </div>
                <div class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('account.dashboard.step_publish') }}</flux:heading>
                    <flux:text>{{ __('account.dashboard.step_publish_text') }}</flux:text>
                    <x-copy-command command="kory init" />
                </div>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="grid content-start gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="recent-notifications">
                <flux:heading>{{ __('account.dashboard.recent_notifications') }}</flux:heading>
                @forelse ($notifications as $notification)
                    @php($kind = $notification->data['kind'] ?? '')
                    <flux:text @class(['text-sm', 'font-semibold text-zinc-900! dark:text-zinc-50!' => $notification->read_at === null])>
                        {{ __("notifications.{$kind}.title", \App\Notifications\NotificationText::replacements($notification->data['params'] ?? [])) }}
                        · <x-relative-date :at="$notification->created_at" />
                    </flux:text>
                @empty
                    <flux:text>{{ __('notifications.empty') }}</flux:text>
                @endforelse
                <flux:link :href="route('notifications.index')" wire:navigate class="text-sm">{{ __('notifications.page.see_all') }}</flux:link>
            </div>

            <div class="grid content-start gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="my-modules">
                <flux:heading>{{ __('account.dashboard.my_modules') }}</flux:heading>
                @forelse ($modules as $module)
                    <flux:text class="font-mono text-sm" dir="ltr">
                        <a href="{{ route('modules.show', ['author' => $user->handle, 'module' => $module->name]) }}" wire:navigate>{{ $module->name }}</a>
                        · {{ $module->visibility === \App\Enums\Visibility::Private ? $module->visibility->label() : ($module->latestVersion?->version ?? '—') }}
                        · ↓ {{ \Illuminate\Support\Number::format($module->download_count) }}
                    </flux:text>
                @empty
                    <flux:text>{{ __('account.dashboard.empty_title') }}</flux:text>
                @endforelse
                <flux:link :href="route('modules.mine')" wire:navigate class="text-sm">{{ __('account.dashboard.see_modules') }}</flux:link>
            </div>

            <div class="grid content-start gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="my-certifications">
                <flux:heading>{{ __('certification.mine.title') }}</flux:heading>
                @forelse ($certifications as $certification)
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <x-status-badge :status="$certification->status" />
                        <span class="font-mono" dir="ltr">{{ $certification->module->name }} {{ $certification->version->version }}</span>
                    </div>
                @empty
                    <flux:text>{{ __('certification.mine.empty') }}</flux:text>
                @endforelse
                <flux:link :href="route('certifications.index')" wire:navigate class="text-sm">{{ __('account.dashboard.see_certifications') }}</flux:link>
            </div>
        </div>
    </div>
</x-layouts::app>
