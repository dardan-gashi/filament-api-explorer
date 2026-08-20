@forelse ($endpoint->responses as $response)
	@php
		$visibleFields = $response->filteredFields($this->fieldSearch);
	@endphp

	<div class="fae-response">
		<div class="fae-response-head">
			<div class="fae-response-title">
				<span class="fae-badge fae-badge-{{ $response->color() }}">{{ $response->status }}</span>

				@if ($response->schemaName)
					<span class="fae-response-name">{{ $response->schemaName }}</span>
				@endif

				@if ($response->description)
					<span>{!! \DardanGashi\FilamentApiExplorer\Support\InlineMarkdown::toHtml($response->description) !!}</span>
				@endif
			</div>

			@if ($response->mediaType)
				<span class="fae-media-type">{{ $response->mediaType }}</span>
			@endif
		</div>

		@if ($response->hasFields())
			@if ($visibleFields === [])
				<p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.field_match') }}</p>
			@else
				@include('filament-api-explorer::partials.schema-tree', ['fields' => $visibleFields])
			@endif
		@elseif ($response->mediaType)
			<p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.fields') }}</p>
		@endif
	</div>
@empty
	<p class="fae-empty">{{ __('filament-api-explorer::explorer.gaps.responses') }}</p>
@endforelse
