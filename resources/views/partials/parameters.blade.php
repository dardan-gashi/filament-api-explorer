@forelse ($parameters as $parameter)
    <div class="fae-param">
        <div class="fae-param-head">
            <span class="fae-param-name">{{ $parameter->name }}</span>
            <span class="fae-param-type">{{ $parameter->type }}</span>

            @if ($parameter->required)
                <span class="fae-badge fae-badge-info">
                    {{ __('filament-api-explorer::explorer.labels.required') }}
                </span>
            @endif

            @if ($parameter->deprecated)
                <span class="fae-badge fae-badge-warning">
                    {{ __('filament-api-explorer::explorer.labels.deprecated') }}
                </span>
            @endif

            @if ($parameter->hasDefault())
                <span class="fae-param-type">
                    {{ __('filament-api-explorer::explorer.labels.default', ['value' => $parameter->defaultLabel()]) }}
                </span>
            @endif

            @if ($parameter->example !== null && ! $parameter->hasDefault())
                <span class="fae-param-type">
                    {{ __('filament-api-explorer::explorer.labels.example', ['value' => $parameter->example]) }}
                </span>
            @endif
        </div>

        @if ($parameter->description)
            <p class="fae-param-note">{{ $parameter->description }}</p>
        @endif

        @if ($parameter->enum !== [])
            <p class="fae-enum">{{ implode(' · ', $parameter->enum) }}</p>
        @endif
    </div>
@empty
    <p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.parameters') }}</p>
@endforelse
