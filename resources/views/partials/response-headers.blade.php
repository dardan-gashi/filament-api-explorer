<section class="fae-section">
	<h3 class="fae-section-title">{{ __('filament-api-explorer::explorer.sections.response_headers') }}</h3>

	@foreach ($headers as $header)
		<div class="fae-kv">
			<span class="fae-kv-name">{{ $header->name }}</span>

			@if ($header->example !== null)
				<span class="fae-kv-value">{{ $header->example }}</span>
			@endif

			@if ($header->description)
				<span class="fae-kv-value">{!! \DardanGashi\FilamentApiExplorer\Support\InlineMarkdown::toHtml($header->description) !!}</span>
			@endif
		</div>
	@endforeach
</section>
