@blaze(fold: true)

<flux:button
    :attributes="$attributes->class('shrink-0')"
    variant="subtle"
    square
    x-data
    x-on:click="$dispatch('flux-sidebar-toggle')"
    aria-label="{{ __('flux.toggle_sidebar') }}"
    data-flux-sidebar-toggle
/>
