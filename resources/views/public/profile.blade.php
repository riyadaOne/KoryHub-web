{{--
    S-08 Profil public (F-RCH-16) : photo, nom, @auteur, pays s'il est renseigné, date d'inscription,
    modules publics par score, total des téléchargements. Jamais l'e-mail, les modules privés ni les
    téléchargements de la personne.
--}}
<x-layouts::public :title="'@'.$user->handle" :description="__('discover.profile_description', ['handle' => '@'.$user->handle, 'count' => $modules->count()])">
    <section class="grid gap-6 py-6">
        <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-4" data-test="profile">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-18 rounded-lg object-cover">
            @else
                <span class="grid size-18 place-items-center rounded-lg bg-zinc-200 text-2xl font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $user->initials() }}</span>
            @endif
            <div class="min-w-0">
                @if ($user->name)
                    <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
                @endif
                <div class="font-mono text-sm" dir="ltr">{{ '@'.$user->handle }}</div>
                <flux:text class="mt-1 text-sm">
                    @if ($country){{ $country }} · @endif{{ __('discover.member_since', ['date' => $user->created_at->locale(app()->getLocale())->isoFormat('MMMM YYYY')]) }}
                    · {{ trans_choice('discover.public_modules', $modules->count(), ['count' => $modules->count()]) }}
                    · ↓ {{ \Illuminate\Support\Number::format($downloads) }}
                </flux:text>
            </div>
        </div>

        @if ($modules->isEmpty())
            <x-empty-state icon="cube" :title="__('discover.profile_empty')" data-test="profile-empty" />
        @else
            <div class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-3.5" data-test="profile-modules">
                @foreach ($modules as $module)
                    <x-module.card :module="$module" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts::public>
