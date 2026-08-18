{{-- The headers a response sets, as the document declares them. They belong
     beside the payload they arrive with, not in the schema column. --}}
<section class="fae-section">
    <h3 class="fae-section-title">{{ __('filament-api-explorer::explorer.sections.response_headers') }}</h3>

    @foreach ($headers as $header)
        <div class="fae-kv">
            <span class="fae-kv-name">{{ $header->name }}</span>

            @if ($header->example !== null)
                <span class="fae-kv-value">{{ $header->example }}</span>
            @endif

            @if ($header->description)
                <span class="fae-kv-value">{{ $header->description }}</span>
            @endif
        </div>
    @endforeach
</section>
