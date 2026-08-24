<div
	class="fae-tabs fae-tabs-inline"
	role="tablist"
	aria-label="{{ __('filament-api-explorer::explorer.labels.format') }}"
>
	@foreach ($formatOptions as $mediaType)
		<button
			type="button"
			role="tab"
			class="fae-tab"
			title="{{ $mediaType }}"
			aria-selected="{{ ($format ?? $formatOptions[0]) === $mediaType ? 'true' : 'false' }}"
			wire:click="setFormat(@js($mediaType))"
		>
			{{ \DardanGashi\FilamentApiExplorer\Support\MediaType::label($mediaType) }}
		</button>
	@endforeach
</div>
