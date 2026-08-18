<section class="fae-section">
    <div class="fae-section-head">
        <div class="fae-tabs fae-tabs-inline" role="tablist">
            @foreach ($snippetLanguages as $language)
                <button
                    type="button"
                    role="tab"
                    class="fae-tab"
                    aria-selected="{{ $this->snippetLanguage === $language->value ? 'true' : 'false' }}"
                    wire:click="setSnippetLanguage(@js($language->value))"
                >
                    {{ $language->label() }}
                </button>
            @endforeach
        </div>

        @include('filament-api-explorer::partials.copy-button', ['value' => $snippet])
    </div>

    <pre class="fae-code">{!! \DardanGashi\FilamentApiExplorer\Highlighting\SnippetHighlighter::highlight($snippet, $snippetSyntax) !!}</pre>
</section>
