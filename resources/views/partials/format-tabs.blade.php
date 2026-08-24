<div
	class="fae-tabs fae-tabs-inline fae-format-tabs"
	role="tablist"
	aria-label="{{ __('filament-api-explorer::explorer.labels.format') }}"
>
	@foreach ($options as $mediaType)
		<button
			type="button"
			role="tab"
			class="fae-tab"
			title="{{ $mediaType }}"
			aria-selected="{{ $selected === $mediaType ? 'true' : 'false' }}"
			wire:click="setFormat(@js($mediaType))"
		>
			{{ \DardanGashi\FilamentApiExplorer\Support\MediaType::label($mediaType) }}
		</button>
	@endforeach
</div>
