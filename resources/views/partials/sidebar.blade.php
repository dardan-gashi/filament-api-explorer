@php
    use Illuminate\Support\Str;
    use DardanGashi\FilamentApiExplorer\Support\GroupLabel;
    use DardanGashi\FilamentApiExplorer\Support\PathParts;

    $pathPrefix = $spec->commonPathPrefix();

    $groupPaths = fn (array $groupEndpoints): string => PathParts::sharedPrefix(array_map(
        fn ($groupEndpoint): string => $groupEndpoint->path,
        $groupEndpoints,
    )) ?: $pathPrefix;
@endphp

<div class="fae-surface">
    <div class="fae-tabs" role="tablist">
        <button
            type="button"
            role="tab"
            class="fae-tab"
            aria-selected="{{ $this->onlyGaps ? 'false' : 'true' }}"
            wire:click="filterGaps(false)"
        >
            {{ __('filament-api-explorer::explorer.sidebar.all') }}
        </button>

        <button
            type="button"
            role="tab"
            class="fae-tab"
            aria-selected="{{ $this->onlyGaps ? 'true' : 'false' }}"
            wire:click="filterGaps(true)"
        >
            {{ __('filament-api-explorer::explorer.sidebar.gaps') }}
            @if ($coverage->gapCount() > 0)
                ({{ $coverage->gapCount() }})
            @endif
        </button>
    </div>

    {{-- A term can arrive from the address bar, and a list narrowed by something
         invisible is a list that looks broken. --}}
    @if ($this->search !== '')
        <div class="fae-nav-filter">
            <span class="fae-nav-filter-term">{{ $this->search }}</span>

            <button type="button" class="fae-nav-filter-clear" wire:click="clearSearch">&times;</button>
        </div>
    @endif

    @if ($groups === [])
        <p class="fae-empty">{{ __('filament-api-explorer::explorer.sidebar.empty') }}</p>
    @elseif ($navGroup === null)
        {{-- One row per resource: nine of them fit without scrolling, and the paths
             below get the whole column to themselves. --}}
        <ul class="fae-nav-list">
            @foreach ($groups as $group => $groupEndpoints)
                <li>
                    <button
                        type="button"
                        class="fae-nav-row"
                        title="{{ Str::after($groupPaths($groupEndpoints), $pathPrefix) }}"
                        wire:click="openGroup(@js($group))"
                    >
                        <span class="fae-nav-row-label">{{ GroupLabel::for($group) }}</span>
                        <span class="fae-nav-row-count">{{ count($groupEndpoints) }}</span>

                        <svg class="fae-chevron" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true">
                            <path d="M4.25 2.5 7.75 6l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>
    @else
        @php
            $groupEndpoints = $groups[$navGroup];
            $groupPrefix = $groupPaths($groupEndpoints);
        @endphp

        <button type="button" class="fae-nav-back" wire:click="openGroup(null)">
            <svg class="fae-chevron fae-chevron-back" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true">
                <path d="M7.75 2.5 4.25 6l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <span class="fae-nav-back-path">{{ Str::after($groupPrefix, $pathPrefix) ?: GroupLabel::for($navGroup) }}</span>
        </button>

        <ul class="fae-endpoint-list">
            @foreach ($groupEndpoints as $groupEndpoint)
                @php
                    $path = PathParts::split(PathParts::within($groupEndpoint->path, $groupPrefix));
                @endphp

                <li>
                    <button
                        type="button"
                        class="fae-endpoint-link"
                        aria-current="{{ $endpoint?->key === $groupEndpoint->key ? 'true' : 'false' }}"
                        title="{{ $groupEndpoint->path }}"
                        wire:click="selectEndpoint(@js($groupEndpoint->key))"
                    >
                        <span class="fae-badge fae-badge-{{ $groupEndpoint->method->color() }} fae-method">
                            {{ $groupEndpoint->method->label() }}
                        </span>

                        {{-- The head gives way, the last segment never does: that is
                             where one endpoint differs from the next. An endpoint on
                             its way out is struck through, the same way a field is:
                             otherwise you only find out after selecting it. --}}
                        <span @class([
                            'fae-endpoint-path',
                            'fae-endpoint-path-deprecated' => $groupEndpoint->deprecated,
                        ])>
                            <span class="fae-path-head">{{ $path['head'] }}</span><span class="fae-path-tail">{{ $path['tail'] }}</span>
                        </span>

                        @unless ($groupEndpoint->isDocumented())
                            <span
                                class="fae-gap-dot"
                                title="{{ __('filament-api-explorer::explorer.sidebar.incomplete') }}"
                                aria-label="{{ __('filament-api-explorer::explorer.sidebar.incomplete') }}"
                            >&bull;</span>
                        @endunless
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
