@php
    $pathPrefix = $spec->commonPathPrefix();
@endphp

<div class="fae-panel">
    <div class="fae-sidebar-search">
        <input
            type="search"
            class="fae-input"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('filament-api-explorer::explorer.sidebar.search') }}"
            aria-label="{{ __('filament-api-explorer::explorer.sidebar.search') }}"
        >
    </div>

    <div class="fae-tabs" role="tablist">
        <button
            type="button"
            role="tab"
            class="fae-tab"
            aria-selected="{{ $this->onlyGaps ? 'false' : 'true' }}"
            wire:click="filterGaps(false)"
        >
            {{ __('filament-api-explorer::explorer.sidebar.all') }}
        </button>

        <button
            type="button"
            role="tab"
            class="fae-tab"
            aria-selected="{{ $this->onlyGaps ? 'true' : 'false' }}"
            wire:click="filterGaps(true)"
        >
            {{ __('filament-api-explorer::explorer.sidebar.gaps') }}
            @if ($coverage->gapCount() > 0)
                ({{ $coverage->gapCount() }})
            @endif
        </button>
    </div>

    @forelse ($groups as $group => $groupEndpoints)
        <div class="fae-group-label">{{ $group }}</div>

        <ul class="fae-endpoint-list">
            @foreach ($groupEndpoints as $groupEndpoint)
                <li>
                    <button
                        type="button"
                        class="fae-endpoint-link"
                        aria-current="{{ $endpoint?->key === $groupEndpoint->key ? 'true' : 'false' }}"
                        wire:click="selectEndpoint(@js($groupEndpoint->key))"
                    >
                        <span class="fae-badge fae-badge-{{ $groupEndpoint->method->color() }} fae-method">
                            {{ $groupEndpoint->method->label() }}
                        </span>

                        <span class="fae-endpoint-path">
                            {{ \Illuminate\Support\Str::after($groupEndpoint->path, $pathPrefix) }}
                        </span>

                        @unless ($groupEndpoint->isDocumented())
                            <span
                                class="fae-gap-dot"
                                title="{{ __('filament-api-explorer::explorer.sidebar.incomplete') }}"
                                aria-label="{{ __('filament-api-explorer::explorer.sidebar.incomplete') }}"
                            >&bull;</span>
                        @endunless
                    </button>
                </li>
            @endforeach
        </ul>
    @empty
        <p class="fae-empty">{{ __('filament-api-explorer::explorer.sidebar.empty') }}</p>
    @endforelse
</div>
