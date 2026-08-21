<x-filament-panels::page>
	<div class="fae">
		@if (! $specError || count($sourceNames) > 1)
			<div class="fae-toolbar">
				@unless ($specError)
					@include('filament-api-explorer::partials.palette')

					<button
						type="button"
						class="fae-button"
						aria-pressed="{{ $this->onlyGaps ? 'true' : 'false' }}"
						@disabled($coverage->isComplete() && ! $this->onlyGaps)
						@if ($coverage->isComplete()) title="{{ __('filament-api-explorer::explorer.nav.no_gaps') }}" @endif
						wire:click="filterGaps({{ $this->onlyGaps ? 'false' : 'true' }})"
					>
						{{ __('filament-api-explorer::explorer.nav.gaps') }}
						@if ($coverage->gapCount() > 0)
							({{ $coverage->gapCount() }})
						@endif
					</button>

					@if ($this->search !== '')
						<span class="fae-nav-filter">
							<span class="fae-nav-filter-term">{{ $this->search }}</span>

							<button type="button" class="fae-nav-filter-clear" wire:click="clearSearch">&times;</button>
						</span>
					@endif
				@endunless

				@if (count($sourceNames) > 1)
					<select
						class="fae-select"
						wire:model.live="source"
						aria-label="{{ __('filament-api-explorer::explorer.header.version') }}"
					>
						@foreach ($sourceNames as $name)
							<option value="{{ $name }}">{{ $name }}</option>
						@endforeach
					</select>
				@endif

				@unless ($specError)
					<span class="fae-toolbar-meta">
						{{ __('filament-api-explorer::explorer.header.endpoints', ['count' => $spec->endpointCount()]) }}
						@if ($spec->generatedAt)
							&middot; {{ __('filament-api-explorer::explorer.header.snapshot', ['time' => $spec->generatedAt->translatedFormat('d.m., H:i')]) }}
						@endif
					</span>

					@if ($spec->versionLabel())
						<span
							class="fae-badge fae-badge-outline"
							title="{{ __('filament-api-explorer::explorer.header.api_version') }}"
						>{{ $spec->versionLabel() }}</span>
					@endif

					<span class="fae-badge fae-badge-{{ $coverage->color() }}">
						{{ __('filament-api-explorer::explorer.header.documented', ['percentage' => $coverage->percentage()]) }}
					</span>
				@endunless
			</div>
		@endif

		@if ($specError)
			<div class="fae-surface">
				<div class="fae-state">
					@include('filament-api-explorer::partials.icon', [
						'name' => 'heroicon-o-document-magnifying-glass',
						'class' => 'fae-state-icon',
						'extra' => ['width' => 28, 'height' => 28],
					])

					<h2 class="fae-state-title">{{ __('filament-api-explorer::explorer.empty.spec') }}</h2>

					<p class="fae-state-text">
						{{ __('filament-api-explorer::explorer.empty.spec_source', ['name' => $spec->name]) }}
					</p>

					<pre class="fae-code fae-state-reason">{{ $specError }}</pre>

					<p class="fae-state-text">{{ __('filament-api-explorer::explorer.empty.spec_hint') }}</p>
				</div>
			</div>
		@else
			<div class="fae-shell">
				<div class="fae-surface">
					@if ($endpoint)
						@include('filament-api-explorer::partials.endpoint')
					@else
						<section class="fae-section">
							<p class="fae-empty">{{ __('filament-api-explorer::explorer.empty.endpoint') }}</p>
						</section>
					@endif
				</div>

				@if ($endpoint)
					<div class="fae-surface">
						@include('filament-api-explorer::partials.snippet')
						@include('filament-api-explorer::partials.sender')
						@include('filament-api-explorer::partials.examples')
					</div>
				@endif
			</div>
		@endif
	</div>
</x-filament-panels::page>
