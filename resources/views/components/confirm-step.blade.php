{{--
    Confirmation dans l'écran (UXUI U4, §6.2) : jamais d'alerte native.
    Avec « confirm-value », l'action reste désactivée tant que la valeur exacte n'est pas saisie
    (suppression d'un module privé ou d'un compte, retrait légal).
    Le bouton de confirmation est fourni par l'écran dans le créneau « action ».
--}}
@props(['name', 'heading', 'confirmValue' => null])

<flux:modal :name="$name" class="md:w-[28rem]" {{ $attributes }}>
    <div class="space-y-5" x-data="{ typed: '', expected: @js($confirmValue) }">
        <flux:heading size="lg">{{ $heading }}</flux:heading>

        @if ($slot->isNotEmpty())
            <flux:text>{{ $slot }}</flux:text>
        @endif

        @if ($confirmValue !== null)
            <flux:input x-model="typed" :label="__('common.type_to_confirm', ['value' => $confirmValue])" autocomplete="off" data-test="confirm-input" />
        @endif

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('common.cancel') }}</flux:button>
            </flux:modal.close>
            <div x-bind:inert="expected !== null && typed !== expected" x-bind:class="expected !== null && typed !== expected ? 'opacity-50' : ''">
                {{ $action }}
            </div>
        </div>
    </div>
</flux:modal>
