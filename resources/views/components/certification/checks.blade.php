{{--
    Résultats des contrôles préliminaires (S-35, S-36, S-64) : ✓ ou ✗, libellé, détail traduit
    (composants et liens, sections manquantes, licence, statut de la version).
--}}
@props(['checks'])

<ul {{ $attributes->class('m-0 grid list-none gap-2 p-0') }} data-test="checks">
    @foreach ($checks as $check)
        @php($check = is_array($check) ? $check : $check->only(['code', 'passed', 'detail']))
        <li class="grid grid-cols-[20px_minmax(0,1fr)] items-start gap-2 text-sm" data-test="check-{{ $check['code'] }}">
            <span @class(['font-bold', 'text-green-700 dark:text-green-400' => $check['passed'], 'text-red-700 dark:text-red-400' => ! $check['passed']]) aria-hidden="true">{{ $check['passed'] ? '✓' : '✗' }}</span>
            <span>
                @switch($check['code'])
                    @case('architecture_declared')
                        {{ __('certification.checks.architecture_declared') }}
                        <small class="block text-zinc-500 dark:text-zinc-400">
                            @if ($check['passed'])
                                @php([$components, $links] = explode('/', (string) $check['detail']) + [0, 0])
                                {{ __('certification.checks.architecture_detail', ['components' => $components, 'links' => $links]) }}
                            @else
                                {{ __('certification.checks.architecture_fix') }}
                            @endif
                        </small>
                        @break
                    @case('readme_sections')
                        {{ __('certification.checks.readme_sections') }}
                        <small class="block text-zinc-500 dark:text-zinc-400">
                            @if ($check['passed'])
                                {{ __('certification.checks.readme_detail') }}
                            @else
                                {{ __('certification.checks.readme_missing', ['sections' => collect(explode(',', (string) $check['detail']))->map(fn ($s) => $s === 'problem' ? __('certification.checks.section_problem') : __('certification.checks.section_pitfalls'))->implode(', ')]) }}
                            @endif
                        </small>
                        @break
                    @case('license_allowed')
                        {{ __('certification.checks.license_allowed') }}
                        <small class="block text-zinc-500 dark:text-zinc-400">{{ $check['detail'] }}</small>
                        @break
                    @case('version_published')
                        {{ __('certification.checks.version_published') }}
                        @unless ($check['passed'])
                            <small class="block text-zinc-500 dark:text-zinc-400">{{ \App\Enums\VersionStatus::tryFrom((string) $check['detail'])?->label() }}</small>
                        @endunless
                        @break
                    @default
                        {{ __('certification.checks.no_open_case') }}
                @endswitch
            </span>
        </li>
    @endforeach
</ul>
