<div class="fae-endpoint-actions" x-data="{ copied: false }">
	<button
		type="button"
		class="fae-button fae-button-icon"
		title="{{ __('filament-api-explorer::explorer.labels.copy_link') }}"
		aria-label="{{ __('filament-api-explorer::explorer.labels.copy_link') }}"
		x-on:click="navigator.clipboard.writeText(window.location.href).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
	>
		<span x-show="! copied">
			@include('filament-api-explorer::partials.icon', ['name' => 'heroicon-o-link'])
		</span>

		<span x-show="copied" x-cloak>
			@include('filament-api-explorer::partials.icon', ['name' => 'heroicon-o-check'])
		</span>
	</button>
</div>
