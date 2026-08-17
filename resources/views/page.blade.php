<x-filament-panels::page>
    <div class="fae">
        <div class="fae-toolbar">
            <div class="fae-toolbar-meta">
                {{ $spec->name }}
                @if ($spec->generatedAt)
                    &middot; {{ __('filament-api-explorer::explorer.header.snapshot', ['time' => $spec->generatedAt->translatedFormat('d.m., H:i')]) }}
                @endif
                &middot; {{ __('filament-api-explorer::explorer.header.endpoints', ['count' => $spec->endpointCount()]) }}
            </div>

            <div class="fae-toolbar-actions">
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

                @if ($spec->versionLabel())
                    <span class="fae-badge fae-badge-outline">{{ $spec->versionLabel() }}</span>
                @endif

                <span class="fae-badge fae-badge-{{ $coverage->color() }}">
                    {{ __('filament-api-explorer::explorer.header.documented', ['percentage' => $coverage->percentage()]) }}
                </span>
            </div>
        </div>

        @if ($specError)
            <div class="fae-panel">
                <div class="fae-panel-body">
                    <p class="fae-note">{{ __('filament-api-explorer::explorer.empty.spec') }} {{ $specError }}</p>
                </div>
            </div>
        @endif

        <div class="fae-shell">
            @include('filament-api-explorer::partials.sidebar')

            <div class="fae-stack">
                @if ($endpoint)
                    @include('filament-api-explorer::partials.endpoint')
                @else
                    <div class="fae-panel">
                        <p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.endpoint') }}</p>
                    </div>
                @endif
            </div>

            @if ($endpoint)
                <div class="fae-stack">
                    @include('filament-api-explorer::partials.snippet')
                    @include('filament-api-explorer::partials.sender')
                    @include('filament-api-explorer::partials.examples')
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
