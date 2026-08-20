@php
	$depth ??= 0;
	$showRequired ??= false;
@endphp

<ul class="fae-tree">
	@foreach ($fields as $field)
		<li class="fae-field" @if ($field->hasChildren()) x-data="{ open: true }" @endif>
			<div class="fae-field-head">
				@if ($field->hasChildren())
					<button
						type="button"
						class="fae-toggle"
						x-on:click="open = ! open"
						x-bind:aria-expanded="open ? 'true' : 'false'"
						aria-label="{{ __('filament-api-explorer::explorer.labels.toggle') }}"
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
					</button>
				@else
					<span class="fae-toggle fae-toggle-leaf" aria-hidden="true"></span>
				@endif

				<span @class(['fae-field-name', 'fae-field-name-deprecated' => $field->deprecated])>
					{{ $field->name }}
				</span>

				<span class="fae-field-type">
					{{ $field->type }}@if ($field->format)&nbsp;· {{ $field->format }}@endif
				</span>

				@if ($field->reference)
					<span class="fae-field-reference">{{ $field->reference }}</span>
				@endif

				@if ($showRequired && $field->required)
					<span class="fae-badge fae-badge-gray">
						{{ __('filament-api-explorer::explorer.labels.required') }}
					</span>
				@endif

				@if (! $showRequired && $field->optional)
					<span class="fae-badge fae-badge-gray">
						{{ __('filament-api-explorer::explorer.labels.optional') }}
					</span>
				@endif

				@if ($field->nullable)
					<span class="fae-badge fae-badge-info">
						{{ __('filament-api-explorer::explorer.labels.nullable') }}
					</span>
				@endif

				@if ($field->deprecated)
					<span class="fae-badge fae-badge-warning">
						{{ __('filament-api-explorer::explorer.labels.deprecated') }}
					</span>
				@endif
			</div>

			@if ($field->description)
				<p class="fae-field-description">{!! \DardanGashi\FilamentApiExplorer\Support\InlineMarkdown::toHtml($field->description) !!}</p>
			@endif

			@if ($field->enum !== [])
				<p class="fae-enum">{{ implode(' · ', $field->enum) }}</p>
			@endif

			@if ($field->hasChildren())
				<div x-show="open">
					@include('filament-api-explorer::partials.schema-tree', [
						'fields' => $field->children,
						'depth' => $depth + 1,
						'showRequired' => $showRequired,
					])
				</div>
			@endif
		</li>
	@endforeach
</ul>
