<x-filament-panels::page>
    <div class="fae">
        {{-- One row: how to get somewhere on the left, what is being read on the
             right of the badges it belongs to. --}}
        <div class="fae-toolbar">
            @include('filament-api-explorer::partials.palette')

            <button
                type="button"
                class="fae-button"
                aria-pressed="{{ $this->onlyGaps ? 'true' : 'false' }}"
                wire:click="filterGaps({{ $this->onlyGaps ? 'false' : 'true' }})"
            >
                {{ __('filament-api-explorer::explorer.nav.gaps') }}
                @if ($coverage->gapCount() > 0)
                    ({{ $coverage->gapCount() }})
                @endif
            </button>

            @if ($this->search !== '')
                {{-- A term can arrive from the address bar, and a navigation narrowed
                     by something invisible is one that looks broken. --}}
                <span class="fae-nav-filter">
                    <span class="fae-nav-filter-term">{{ $this->search }}</span>

                    <button type="button" class="fae-nav-filter-clear" wire:click="clearSearch">&times;</button>
                </span>
            @endif

            @if (count($sourceNames) > 1)
                <select
                    class="fae-select"
                    wire:model.live="source"
                    aria-label="{{ __('filament-api-explorer::explorer.header.version') }}"
                >
                    @foreach ($sourceNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            @endif

            <span class="fae-toolbar-meta">
                {{ $spec->name }}
                &middot; {{ __('filament-api-explorer::explorer.header.endpoints', ['count' => $spec->endpointCount()]) }}
                @if ($spec->generatedAt)
                    &middot; {{ __('filament-api-explorer::explorer.header.snapshot', ['time' => $spec->generatedAt->translatedFormat('d.m., H:i')]) }}
                @endif
            </span>

            @if ($spec->versionLabel())
                <span class="fae-badge fae-badge-outline">{{ $spec->versionLabel() }}</span>
            @endif

            <span class="fae-badge fae-badge-{{ $coverage->color() }}">
                {{ __('filament-api-explorer::explorer.header.documented', ['percentage' => $coverage->percentage()]) }}
            </span>
        </div>

        @if ($specError)
            <div class="fae-surface">
                <section class="fae-section">
                    <p class="fae-note">{{ __('filament-api-explorer::explorer.empty.spec') }} {{ $specError }}</p>
                </section>
            </div>
        @endif

        <div class="fae-shell">
            <div class="fae-surface">
                @if ($endpoint)
                    @include('filament-api-explorer::partials.endpoint')
                @else
                    <section class="fae-section">
                        <p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.endpoint') }}</p>
                    </section>
                @endif
            </div>

            @if ($endpoint)
                <div class="fae-surface">
                    @include('filament-api-explorer::partials.snippet')
                    @include('filament-api-explorer::partials.sender')
                    @include('filament-api-explorer::partials.examples')
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
