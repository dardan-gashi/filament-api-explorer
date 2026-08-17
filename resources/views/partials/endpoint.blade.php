<div class="fae-panel">
    <div class="fae-panel-body">
        <div class="fae-endpoint-head">
            <span class="fae-badge fae-badge-{{ $endpoint->method->color() }} fae-method">
                {{ $endpoint->method->label() }}
            </span>

            <span class="fae-endpoint-title">{{ $endpoint->path }}</span>

            @foreach ($endpoint->security as $scheme)
                <span class="fae-badge fae-badge-outline">{{ $scheme }}</span>
            @endforeach

            @if ($endpoint->deprecated)
                <span class="fae-badge fae-badge-warning">
                    {{ __('filament-api-explorer::explorer.labels.deprecated') }}
                </span>
            @endif
        </div>

        @if ($endpoint->summary)
            <p class="fae-summary">{{ $endpoint->summary }}</p>
        @endif

        @if ($endpoint->description && $endpoint->description !== $endpoint->summary)
            <p class="fae-param-note">{{ $endpoint->description }}</p>
        @endif

        @if ($endpoint->meta !== [])
            <div class="fae-meta-row">
                @foreach ($endpoint->meta as $value)
                    <span>{{ $value }}</span>
                @endforeach
            </div>
        @endif

        @unless ($endpoint->isDocumented())
            <div class="fae-meta-row">
                @foreach ($endpoint->gaps() as $gap)
                    <span class="fae-badge fae-badge-warning">{{ __($gap->translationKey()) }}</span>
                @endforeach
            </div>
        @endunless
    </div>
</div>

@foreach ($parameterSections as $section)
    <div class="fae-panel">
        <div class="fae-panel-body">
            <h3 class="fae-section-title">{{ $section['label'] }}</h3>

            @include('filament-api-explorer::partials.parameters', ['parameters' => $section['parameters']])
        </div>
    </div>
@endforeach

@unless ($endpoint->method->isSafe())
    <div class="fae-panel">
        <div class="fae-panel-body">
            <p class="fae-note">{{ __('filament-api-explorer::explorer.notes.request_body') }}</p>
        </div>
    </div>
@endunless

<div class="fae-panel">
    <div class="fae-panel-body">
        <h3 class="fae-section-title">{{ __('filament-api-explorer::explorer.sections.responses') }}</h3>

        @if ($endpoint->responses !== [])
            <input
                type="search"
                class="fae-input"
                wire:model.live.debounce.300ms="fieldSearch"
                placeholder="{{ __('filament-api-explorer::explorer.labels.field_search') }}"
                aria-label="{{ __('filament-api-explorer::explorer.labels.field_search') }}"
            >
        @endif

        @include('filament-api-explorer::partials.responses')
    </div>
</div>
