{{-- The navigation, all of it.

     Jumping is typing and browsing is two levels, and both happen here: the whole
     structure travels to the browser once, so neither costs a round trip — and
     neither costs a column of the page for the rest of the time.

     The trigger stays in the toolbar where it cannot scroll away, and ⌘K opens it
     from anywhere on the page. --}}
<div
    class="fae-palette-root"
    {{-- A new key means a new element, which is the only way a structure filtered
         on the server reaches a palette that is already on the page. --}}
    wire:key="palette-{{ $paletteKey }}"
    x-data="{
        open: false,
        term: '',
        active: 0,
        resource: @js($openResource),
        resources: @js($resources),

        get endpoints() {
            return this.resources.flatMap((resource) => resource.endpoints);
        },

        get results() {
            const term = this.term.trim().toLowerCase();

            if (term === '') {
                return [];
            }

            const words = term.split(/\s+/);

            return this.endpoints
                .filter((endpoint) => words.every((word) => endpoint.haystack.includes(word)))
                .slice(0, 20);
        },

        get opened() {
            return this.resources.find((resource) => resource.group === this.resource) ?? null;
        },

        /** Whatever list is on screen, so one pair of arrow keys serves all three. */
        get items() {
            if (this.term.trim() !== '') {
                return this.results.map((endpoint) => ({ kind: 'endpoint', endpoint }));
            }

            if (this.opened) {
                return this.opened.endpoints.map((endpoint) => ({ kind: 'endpoint', endpoint }));
            }

            return this.resources.map((resource) => ({ kind: 'resource', resource }));
        },

        show() {
            this.open = true;
            this.term = '';
            this.active = 0;

            this.$nextTick(() => this.$refs.term?.focus());
        },

        move(by) {
            const last = this.items.length - 1;

            this.active = Math.min(Math.max(this.active + by, 0), Math.max(last, 0));
            this.reveal();
        },

        /**
         * The box scrolls at 70vh, so the row the arrows moved to is regularly
         * below the fold. Marking it is not enough — it has to be brought up.
         *
         * Found by position rather than by a marker on the row: an expression in
         * an attribute cannot carry a double quote, and a selector for one would
         * end the attribute in the middle of this object.
         */
        reveal() {
            this.$nextTick(() => this.$refs.results
                ?.querySelectorAll('li')[this.active]
                ?.scrollIntoView({ block: 'nearest' }));
        },

        enter() {
            const item = this.items[this.active];

            if (! item) {
                return;
            }

            if (item.kind === 'resource') {
                this.into(item.resource.group);

                return;
            }

            this.choose(item.endpoint);
        },

        into(group) {
            this.resource = group;
            this.active = 0;
            this.term = '';
        },

        /** One step in, and only where there is a level to go into. */
        deeper() {
            if (this.items[this.active]?.kind === 'resource') {
                this.enter();
            }
        },

        /** One step out: from a term to no term, from a resource to the resources. */
        out() {
            if (this.term !== '') {
                this.term = '';
            } else {
                this.resource = null;
            }

            this.active = 0;
        },

        /**
         * Which resource the palette stands in afterwards is the server's answer,
         * not this one's: the page it renders next says which endpoint is open.
         */
        choose(endpoint) {
            this.open = false;

            $wire.selectEndpoint(endpoint.key);
        },
    }"
    x-on:keydown.window.cmd.k.prevent="show()"
    x-on:keydown.window.ctrl.k.prevent="show()"
>
    <button type="button" class="fae-palette-trigger" x-on:click="show()">
        <span>{{ __('filament-api-explorer::explorer.palette.trigger') }}</span>
        <kbd class="fae-kbd">{{ __('filament-api-explorer::explorer.palette.shortcut') }}</kbd>
    </button>

    {{-- Nothing in here is ever updated by the server: the structure arrives in
         `x-data` and the captions are fixed, so a changed structure comes back as a
         new element by the key above. What Livewire's diffing would do instead is
         take out the rows Alpine wrote — they are in nobody's HTML, so they look
         like leftovers — and hand back a list that answers no clicks while nothing
         fails anywhere. The same diffing drops the inline style behind `x-show`,
         which opens a closed dialog on an unrelated request. --}}
    <div
        class="fae-palette-overlay"
        wire:ignore
        x-show="open"
        x-cloak
        x-on:click.self="open = false"
        x-on:keydown.escape="open = false"
        {{-- On the dialog rather than on the field: hovering a row moves the focus
             off the input, and the arrows have to keep working when it has. --}}
        x-on:keydown.down.prevent="move(1)"
        x-on:keydown.up.prevent="move(-1)"
        x-on:keydown.enter.prevent="enter()"
        x-on:keydown.left="term === '' && out()"
        x-on:keydown.right="term === '' && deeper()"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('filament-api-explorer::explorer.palette.trigger') }}"
    >
        <div class="fae-palette">
            <div class="fae-palette-head">
                {{-- The way back is a row, not only a key: which level you are on is
                     not obvious until you see what you are inside of. --}}
                <button type="button" class="fae-palette-back" x-show="opened && term.trim() === ''" x-on:click="out()">
                    <svg class="fae-chevron fae-chevron-back" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true">
                        <path d="M7.75 2.5 4.25 6l3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <span x-text="opened?.prefix || opened?.label"></span>
                </button>

                <input
                    type="text"
                    class="fae-input fae-palette-input"
                    x-ref="term"
                    x-model="term"
                    {{-- A narrower list can no longer hold the row that was active. --}}
                    x-on:input="active = 0"
                    placeholder="{{ __('filament-api-explorer::explorer.palette.placeholder') }}"
                    aria-label="{{ __('filament-api-explorer::explorer.palette.placeholder') }}"
                    autocomplete="off"
                >
            </div>

            <ul class="fae-palette-results" role="listbox" x-ref="results">
                <template x-for="(item, index) in items" x-bind:key="item.kind + (item.resource?.group ?? item.endpoint.key)">
                    <li>
                        {{-- A resource: its name, the path below it and how many
                             endpoints hang off it. --}}
                        <button
                            type="button"
                            class="fae-palette-result"
                            role="option"
                            x-show="item.kind === 'resource'"
                            x-bind:aria-selected="index === active ? 'true' : 'false'"
                            x-bind:class="{ 'fae-palette-result-active': index === active }"
                            x-on:mouseenter="active = index"
                            x-on:click="into(item.resource.group)"
                        >
                            <span class="fae-palette-result-text">
                                <span x-text="item.resource.label"></span>
                                <span class="fae-palette-result-summary" x-text="item.resource.prefix"></span>
                            </span>

                            <span class="fae-palette-count" x-text="item.resource.endpoints.length"></span>

                            <svg class="fae-chevron" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true">
                                <path d="M4.25 2.5 7.75 6l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        {{-- An endpoint: inside a resource the part of the path it does
                             not share, in a search result the whole thing. --}}
                        <button
                            type="button"
                            class="fae-palette-result"
                            role="option"
                            x-show="item.kind === 'endpoint'"
                            x-bind:aria-selected="index === active ? 'true' : 'false'"
                            x-bind:class="{ 'fae-palette-result-active': index === active }"
                            x-on:mouseenter="active = index"
                            x-on:click="choose(item.endpoint)"
                        >
                            <span class="fae-badge fae-method" x-bind:class="'fae-badge-' + item.endpoint.color" x-text="item.endpoint.method"></span>

                            <span class="fae-palette-result-text">
                                <span
                                    class="fae-palette-result-path"
                                    x-bind:class="{ 'fae-endpoint-path-deprecated': item.endpoint.deprecated }"
                                    x-text="term.trim() === '' ? item.endpoint.label : item.endpoint.path"
                                ></span>

                                <span class="fae-palette-result-summary" x-text="item.endpoint.summary"></span>
                            </span>

                            <span
                                class="fae-gap-dot"
                                x-show="! item.endpoint.documented"
                                title="{{ __('filament-api-explorer::explorer.nav.incomplete') }}"
                            >&bull;</span>
                        </button>
                    </li>
                </template>
            </ul>

            <p class="fae-palette-empty" x-show="items.length === 0">
                {{ __('filament-api-explorer::explorer.palette.empty') }}
            </p>

            {{-- Read once and then never again, which is why it sits at the bottom
                 and not in the way. --}}
            <div class="fae-palette-keys">
                <span class="fae-palette-key">
                    <kbd class="fae-kbd">↑</kbd><kbd class="fae-kbd">↓</kbd>
                    {{ __('filament-api-explorer::explorer.palette.move') }}
                </span>

                <span class="fae-palette-key">
                    <kbd class="fae-kbd">↵</kbd>
                    {{ __('filament-api-explorer::explorer.palette.open') }}
                </span>

                <span class="fae-palette-key">
                    <kbd class="fae-kbd">←</kbd>
                    {{ __('filament-api-explorer::explorer.palette.back') }}
                </span>

                <span class="fae-palette-key">
                    <kbd class="fae-kbd">esc</kbd>
                    {{ __('filament-api-explorer::explorer.palette.close') }}
                </span>
            </div>
        </div>
    </div>
</div>
