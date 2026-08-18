@php
    use Illuminate\Support\Str;
    use DardanGashi\FilamentApiExplorer\Support\GroupLabel;
    use DardanGashi\FilamentApiExplorer\Support\PathParts;

    $pathPrefix = $spec->commonPathPrefix();

    // A group folded shut must never swallow a row the filter just found, so the
    // filter is part of the key: Livewire replaces the group and Alpine starts it
    // open again.
    $listKey = md5($this->search.($this->onlyGaps ? 'gaps' : 'all'));
@endphp

<div class="fae-surface">
    <div class="fae-sidebar-search">
        <input
            type="search"
            class="fae-input"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('filament-api-explorer::explorer.sidebar.search') }}"
            aria-label="{{ __('filament-api-explorer::explorer.sidebar.search') }}"
        >
    </div>

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

    @forelse ($groups as $group => $groupEndpoints)
        @php
            // A group of one shares nothing with anybody, so what is already stated
            // elsewhere is the prefix of the whole document.
            $groupPrefix = PathParts::sharedPrefix(array_map(
                fn ($groupEndpoint): string => $groupEndpoint->path,
                $groupEndpoints,
            )) ?: $pathPrefix;
        @endphp

        <div class="fae-group-block" x-data="{ open: true }" wire:key="group-{{ $listKey }}-{{ $loop->index }}">
            <button type="button" class="fae-group" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
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

                <span class="fae-group-label">{{ GroupLabel::for($group) }}</span>

                {{-- What the rows below leave out, said once. --}}
                @if ($groupPrefix !== '' && $groupPrefix !== $pathPrefix)
                    <span class="fae-group-prefix">{{ Str::after($groupPrefix, $pathPrefix) }}</span>
                @endif
            </button>

            <ul class="fae-endpoint-list" x-show="open">
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
        </div>
    @empty
        <p class="fae-empty">{{ __('filament-api-explorer::explorer.sidebar.empty') }}</p>
    @endforelse
</div>
