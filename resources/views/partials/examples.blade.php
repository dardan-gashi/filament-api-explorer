@foreach ($exampleSections as $section)
	<section class="fae-section" wire:key="example-{{ $section['key'] }}" x-data="{ open: {{ $section['collapsed'] ? 'false' : 'true' }} }">
		<div class="fae-section-head">
			<button
				type="button"
				class="fae-section-toggle"
				x-on:click="open = ! open"
				x-bind:aria-expanded="open ? 'true' : 'false'"
			>
				<svg
					class="fae-chevron"
					x-bind:class="{ 'fae-chevron-open': open }"
					viewBox="0 0 12 12"
					width="12"
					height="12"
					aria-hidden="true"
				>
					<path
						d="M4.25 2.5 7.75 6l-3.5 3.5"
						fill="none"
						stroke="currentColor"
						stroke-width="1.6"
						stroke-linecap="round"
						stroke-linejoin="round"
					/>
				</svg>

				@if ($section['status'])
					<span class="fae-badge fae-badge-{{ $section['color'] }}">{{ $section['status'] }}</span>
				@endif

				<span
					@class(['fae-example-origin', 'fae-example-origin-live' => $section['captured']])
					@if ($captureEnabled && $canSend && ! $section['captured'])
						title="{{ __('filament-api-explorer::explorer.notes.capture') }}"
					@endif
				>{{ $section['origin'] }}</span>
			</button>

			<div class="fae-section-actions">
				@if ($section['captured'])
					<button
						type="button"
						class="fae-button fae-button-quiet"
						wire:click="discardSample(@js($section['status']))"
					>
						{{ __('filament-api-explorer::explorer.labels.discard_sample') }}
					</button>
				@endif

				@include('filament-api-explorer::partials.copy-button', ['value' => $section['body']])
			</div>
		</div>

		<div x-show="open">
			@include('filament-api-explorer::partials.code', [
				'html' => \DardanGashi\FilamentApiExplorer\Highlighting\BodyHighlighter::highlight($section['body'], $section['mediaType']),
				'class' => 'fae-response-body',
			])
		</div>
	</section>

	@if ($section['headers'] !== [])
		@include('filament-api-explorer::partials.response-headers', ['headers' => $section['headers']])
	@endif
@endforeach
