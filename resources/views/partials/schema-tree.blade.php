{{-- Renders one level of a schema and recurses into the children of each field. --}}
<ul class="fae-tree">
    @foreach ($fields as $field)
        <li class="fae-field">
            <div class="fae-field-head">
                <span @class(['fae-field-name', 'fae-field-name-deprecated' => $field->deprecated])>
                    {{ $field->name }}
                </span>

                <span class="fae-field-type">
                    {{ $field->type }}@if ($field->format)&nbsp;· {{ $field->format }}@endif
                </span>

                @if ($field->reference)
                    <span class="fae-field-reference">{{ $field->reference }}</span>
                @endif

                @if ($field->required)
                    <span class="fae-badge fae-badge-info">
                        {{ __('filament-api-explorer::explorer.labels.required') }}
                    </span>
                @endif

                @if ($field->nullable)
                    <span class="fae-badge fae-badge-gray">
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
                <p class="fae-field-description">{{ $field->description }}</p>
            @endif

            @if ($field->enum !== [])
                <p class="fae-enum">{{ implode(' · ', $field->enum) }}</p>
            @endif

            @if ($field->hasChildren())
                @include('filament-api-explorer::partials.schema-tree', ['fields' => $field->children])
            @endif
        </li>
    @endforeach
</ul>
