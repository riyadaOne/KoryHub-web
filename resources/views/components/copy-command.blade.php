{{--
    Commande copiable (UXUI §6.2, U8) : police à chasse fixe, bouton Copier, « Copié » pendant 2 secondes.
    Si le presse-papiers est refusé, le texte est sélectionné pour une copie manuelle.
--}}
@props(['command'])

<div x-data="{
        copied: false,
        copy() {
            const select = () => { const range = document.createRange(); range.selectNodeContents(this.$refs.code); const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range); };
            if (! navigator.clipboard) { select(); return; }
            navigator.clipboard.writeText(this.$refs.code.textContent).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }, select);
        },
    }"
    {{ $attributes->class('flex min-w-0 items-center gap-2 rounded-md border border-zinc-200 bg-zinc-100 py-1.5 ps-3 pe-1.5 dark:border-zinc-700 dark:bg-zinc-800') }}>
    <code x-ref="code" class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap font-mono text-sm">{{ $command }}</code>
    <flux:button size="xs" variant="ghost" x-on:click="copy()" data-test="copy-button">
        <span x-show="! copied">{{ __('common.copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('common.copied') }}</span>
    </flux:button>
</div>
