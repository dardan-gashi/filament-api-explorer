<section class="fae-section">
    <div class="fae-endpoint-head">
        <span class="fae-badge fae-badge-{{ $endpoint->method->color() }} fae-method">
            {{ $endpoint->method->label() }}
        </span>

        <span class="fae-endpoint-title">{{ $endpoint->path }}</span>

        @foreach ($endpoint->security as $scheme)
            <span class="fae-badge fae-badge-outline">{{ $spec->securityLabel($scheme) }}</span>
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
</section>

@foreach ($parameterSections as $section)
    <section class="fae-section">
        <h3 class="fae-section-title">{{ $section['label'] }}</h3>

        @include('filament-api-explorer::partials.parameters', ['parameters' => $section['parameters']])
    </section>
@endforeach

@if ($endpoint->requestBody)
    <section class="fae-section">
        <div class="fae-section-head">
            <h3 class="fae-section-title">
                {{ __('filament-api-explorer::explorer.sections.request_body') }}

                @if ($endpoint->requestBody->required)
                    <span class="fae-badge fae-badge-gray">
                        {{ __('filament-api-explorer::explorer.labels.required') }}
                    </span>
                @endif
            </h3>

            @if ($endpoint->requestBody->mediaType)
                <span class="fae-media-type">{{ $endpoint->requestBody->mediaType }}</span>
            @endif
        </div>

        @if ($endpoint->requestBody->description)
            <p class="fae-param-note">{{ $endpoint->requestBody->description }}</p>
        @endif

        @php
            $bodyFields = $endpoint->requestBody->filteredFields($this->fieldSearch);
        @endphp

        @if (! $endpoint->requestBody->hasFields())
            <p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.fields') }}</p>
        @elseif ($bodyFields === [])
            <p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.field_match') }}</p>
        @else
            @include('filament-api-explorer::partials.schema-tree', ['fields' => $bodyFields])
        @endif
    </section>
@endif

<section class="fae-section">
    <div class="fae-section-head">
        <h3 class="fae-section-title">{{ __('filament-api-explorer::explorer.sections.responses') }}</h3>

        @if ($endpoint->responses !== [])
            <input
                type="search"
                class="fae-input fae-input-inline"
                wire:model.live.debounce.300ms="fieldSearch"
                placeholder="{{ __('filament-api-explorer::explorer.labels.field_search') }}"
                aria-label="{{ __('filament-api-explorer::explorer.labels.field_search') }}"
            >
        @endif
    </div>

    @include('filament-api-explorer::partials.responses')
</section>
