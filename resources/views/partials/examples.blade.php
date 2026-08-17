@foreach ($endpoint->responses as $response)
    @continue (blank($response->example))

    <div class="fae-panel">
        <div class="fae-code-head">
            <div class="fae-response-title">
                <span class="fae-badge fae-badge-{{ $response->color() }}">{{ $response->status }}</span>

                @if ($response->mediaType)
                    <span class="fae-media-type">{{ $response->mediaType }}</span>
                @endif
            </div>

            @include('filament-api-explorer::partials.copy-button', ['value' => $response->example])
        </div>

        <pre class="fae-code">{!! \DardanGashi\FilamentApiExplorer\Support\JsonHighlighter::highlight($response->example) !!}</pre>
    </div>
@endforeach
