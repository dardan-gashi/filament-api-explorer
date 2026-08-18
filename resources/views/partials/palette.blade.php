{{-- Jumping to an endpoint is typing, so the whole list travels to the browser once
     and is searched there: a round trip per keystroke is felt on every keystroke.

     The trigger stays in the toolbar where it cannot scroll away, and ⌘K opens it
     from anywhere on the page. --}}
<div
    class="fae-palette-root"
    x-data="{
        open: false,
        term: '',
        active: 0,
        endpoints: @js($paletteEndpoints),

        get results() {
            const term = this.term.trim().toLowerCase();

            if (term === '') {
                return this.endpoints.slice(0, 12);
            }

            const words = term.split(/\s+/);

            return this.endpoints
                .filter((endpoint) => words.every((word) => endpoint.haystack.includes(word)))
                .slice(0, 12);
        },

        show() {
            this.open = true;
            this.term = '';
            this.active = 0;

            this.$nextTick(() => this.$refs.term?.focus());
        },

        move(by) {
            const last = this.results.length - 1;

            this.active = Math.min(Math.max(this.active + by, 0), Math.max(last, 0));
        },

        choose(endpoint) {
            if (! endpoint) {
                return;
            }

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

    <div
        class="fae-palette-overlay"
        x-show="open"
        x-cloak
        x-on:click.self="open = false"
        x-on:keydown.escape="open = false"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('filament-api-explorer::explorer.palette.trigger') }}"
    >
        <div class="fae-palette">
            <input
                type="text"
                class="fae-input fae-palette-input"
                x-ref="term"
                x-model="term"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose(results[active])"
                placeholder="{{ __('filament-api-explorer::explorer.palette.placeholder') }}"
                aria-label="{{ __('filament-api-explorer::explorer.palette.placeholder') }}"
                autocomplete="off"
            >

            <ul class="fae-palette-results" role="listbox">
                <template x-for="(endpoint, index) in results" x-bind:key="endpoint.key">
                    <li>
                        <button
                            type="button"
                            class="fae-palette-result"
                            role="option"
                            x-bind:aria-selected="index === active ? 'true' : 'false'"
                            x-bind:class="{ 'fae-palette-result-active': index === active }"
                            x-on:mouseenter="active = index"
                            x-on:click="choose(endpoint)"
                        >
                            <span class="fae-badge fae-method" x-bind:class="'fae-badge-' + endpoint.color" x-text="endpoint.method"></span>

                            <span class="fae-palette-result-text">
                                <span class="fae-palette-result-path" x-text="endpoint.path"></span>
                                <span class="fae-palette-result-summary" x-text="endpoint.summary"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>

            <p class="fae-palette-empty" x-show="results.length === 0">
                {{ __('filament-api-explorer::explorer.palette.empty') }}
            </p>
        </div>
    </div>
</div>
