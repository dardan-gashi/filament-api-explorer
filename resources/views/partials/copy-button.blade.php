{{-- Clipboard button. `$value` is the exact text that gets copied. --}}
<div x-data="{ copied: false }">
    <button
        type="button"
        class="fae-button"
        x-on:click="navigator.clipboard.writeText(@js($value)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
    >
        <span x-show="! copied">{{ __('filament-api-explorer::explorer.labels.copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('filament-api-explorer::explorer.labels.copied') }}</span>
    </button>
</div>
