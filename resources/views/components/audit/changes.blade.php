{{--
    Champs d'un événement d'audit (S-47, S-71) : valeur avant → après ; un champ masqué (donnée
    personnelle, TRD §21) apparaît « modifié » sans valeur.
--}}
@props(['event', 'compact' => false])

@if ($event->changes->isEmpty())
    <span class="text-zinc-500 dark:text-zinc-400">—</span>
@elseif ($compact)
    <span {{ $attributes }}>
        @foreach ($event->changes as $change)
            <span class="block">
                <span class="font-mono text-xs">{{ $change->field }}</span> :
                @if (\App\Support\AuditPresenter::isRedacted($change))
                    {{ __('audit.redacted') }}
                @else
                    <span dir="auto">{{ \Illuminate\Support\Str::limit(\App\Support\AuditPresenter::value($change->before, $change->field, $event->subject_type), 60) }}</span> → <span dir="auto">{{ \Illuminate\Support\Str::limit(\App\Support\AuditPresenter::value($change->after, $change->field, $event->subject_type), 60) }}</span>
                @endif
            </span>
        @endforeach
    </span>
@else
    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700" {{ $attributes }}>
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('audit.field') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('audit.before') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ __('audit.after') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($event->changes as $change)
                    <tr class="border-t border-zinc-200 align-top dark:border-zinc-700">
                        <td class="px-3 py-2 font-mono text-xs">{{ $change->field }}</td>
                        @if (\App\Support\AuditPresenter::isRedacted($change))
                            <td colspan="2" class="px-3 py-2 text-zinc-500 dark:text-zinc-400">{{ __('audit.redacted') }}</td>
                        @else
                            <td class="whitespace-pre-line break-all bg-red-50 px-3 py-2 dark:bg-red-950/40" dir="auto">{{ \App\Support\AuditPresenter::value($change->before, $change->field, $event->subject_type) }}</td>
                            <td class="whitespace-pre-line break-all bg-green-50 px-3 py-2 dark:bg-green-950/40" dir="auto">{{ \App\Support\AuditPresenter::value($change->after, $change->field, $event->subject_type) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
