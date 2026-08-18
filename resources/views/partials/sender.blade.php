<section class="fae-section">
    <h3 class="fae-section-title">{{ __('filament-api-explorer::explorer.sections.sender') }}</h3>

    @if (! $sendingEnabled)
        <p class="fae-note">{{ __('filament-api-explorer::explorer.notes.sending_disabled') }}</p>
    @elseif (! $canSend)
        <p class="fae-note">{{ __('filament-api-explorer::explorer.notes.unsafe_method') }}</p>
    @else
        @if (count($serverOptions) > 1)
            <div class="fae-field-grid">
                <label class="fae-field-label" for="fae-server">
                    {{ __('filament-api-explorer::explorer.labels.server') }}
                </label>

                <select id="fae-server" class="fae-input" wire:model="server">
                    @foreach ($serverOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @foreach ($senderSections as $section)
            <h4 class="fae-group-label fae-group-label-flush">{{ $section['label'] }}</h4>

            @foreach ($section['fields'] as $field)
                <div class="fae-field-grid">
                    <label class="fae-field-label" for="fae-{{ $field['bind'] }}" title="{{ $field['name'] }}">
                        {{ $field['name'] }}@if ($field['required'])<span class="fae-gap-dot">&nbsp;*</span>@endif
                    </label>

                    <input
                        id="fae-{{ $field['bind'] }}"
                        type="text"
                        class="fae-input"
                        wire:model="{{ $field['bind'] }}"
                        placeholder="{{ $field['placeholder'] }}"
                    >
                </div>
            @endforeach
        @endforeach

        <div class="fae-sender-actions">
            <button
                type="button"
                class="fae-button fae-button-primary"
                wire:click="send"
                wire:loading.attr="disabled"
                wire:target="send"
            >
                <span wire:loading.remove wire:target="send">
                    {{ __('filament-api-explorer::explorer.labels.send') }}
                </span>

                <span wire:loading wire:target="send">
                    {{ __('filament-api-explorer::explorer.labels.sending') }}
                </span>
            </button>

            <button type="button" class="fae-button" wire:click="resetRequest">
                {{ __('filament-api-explorer::explorer.labels.reset') }}
            </button>
        </div>
    @endif
</section>

@if ($this->result)
    @php
        $result = $this->result;
    @endphp

    <section class="fae-section">
        <div class="fae-section-head">
            <div class="fae-response-title">
                <span class="fae-badge fae-badge-{{ $result->color() }}">
                    {{ $result->hasFailed() ? '—' : $result->status }}
                </span>

                <span class="fae-media-type">
                    {{ __('filament-api-explorer::explorer.labels.duration', ['duration' => $result->durationMs]) }}
                </span>
            </div>

            @unless ($result->hasFailed())
                @include('filament-api-explorer::partials.copy-button', ['value' => $result->prettyBody()])
            @endunless
        </div>

        @if ($result->hasFailed())
            <p class="fae-note">{{ $result->error }}</p>
        @else
            @foreach ($result->headers as $name => $value)
                <div class="fae-kv">
                    <span class="fae-kv-name">{{ $name }}</span>
                    <span class="fae-kv-value">{{ $value }}</span>
                </div>
            @endforeach

            <pre class="fae-code">{!! \DardanGashi\FilamentApiExplorer\Support\JsonHighlighter::highlight($result->prettyBody()) !!}</pre>
        @endif
    </section>
@endif
