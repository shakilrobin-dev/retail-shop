<div x-data="{ open: false, active: 0 }"
    x-on:keydown.window.prevent.ctrl.k="open = true; $nextTick(() => $refs.input.focus())"
    x-on:keydown.window.prevent.meta.k="open = true; $nextTick(() => $refs.input.focus())"
    x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true; $nextTick(() => $refs.input.focus())"
        class="flex items-center gap-2 rounded-lg border border-card-line bg-muted px-3 py-1.5 text-sm text-muted-foreground hover:border-layer-line hover:text-muted-foreground-2 md:w-72">
        <x-icon name="search" class="size-4" />
        <span class="hidden flex-1 text-left md:inline">{{ __('Search anything...') }}</span>
        <kbd class="hidden rounded border border-card-line bg-layer px-1.5 text-[10px] font-medium text-muted-foreground-1 md:inline">Ctrl K</kbd>
    </button>

    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/50 p-4 pt-[12vh] dark:bg-neutral-900/80" x-on:click.self="open = false">
        <div x-show="open" x-transition class="w-full max-w-xl overflow-hidden rounded-xl border border-overlay-line bg-overlay shadow-2xl">
            <div class="flex items-center gap-3 border-b border-card-divider px-4">
                <x-icon name="search" class="size-5 text-muted-foreground" />
                <input x-ref="input" wire:model.live.debounce.250ms="query" type="search" autocomplete="off"
                    x-on:keydown.arrow-down.prevent="active = Math.min(active + 1, ($refs.list?.querySelectorAll('a').length ?? 1) - 1)"
                    x-on:keydown.arrow-up.prevent="active = Math.max(active - 1, 0)"
                    x-on:keydown.enter.prevent="$refs.list?.querySelectorAll('a')[active]?.click()"
                    x-on:input="active = 0"
                    placeholder="{{ __('Product, barcode, invoice, job no, phone, page...') }}"
                    class="h-14 flex-1 border-0 bg-transparent text-base text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-0">
                <kbd class="rounded border border-card-line px-1.5 text-[10px] text-muted-foreground-1">Esc</kbd>
            </div>

            <div class="scroll-thin max-h-[60vh] overflow-y-auto">
                @if (mb_strlen(trim($query)) < 2)
                    <p class="px-4 py-8 text-center text-sm text-muted-foreground-1">{{ __('Type at least 2 letters to search.') }}</p>
                @elseif (count($results) === 0)
                    <p class="px-4 py-8 text-center text-sm text-muted-foreground-1">{{ __('Nothing found.') }}</p>
                @else
                    <div x-ref="list" class="py-2">
                        @foreach ($results as $index => $result)
                            @if ($index === 0 || $results[$index - 1]['group'] !== $result['group'])
                                <p class="px-4 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{{ $result['group'] }}</p>
                            @endif
                            <a href="{{ $result['url'] }}" wire:navigate x-on:click="open = false" wire:key="gs-{{ $index }}"
                                x-on:mouseenter="active = {{ $index }}"
                                :class="active === {{ $index }} ? 'bg-primary-50' : ''"
                                class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                <span class="truncate font-medium text-foreground">{{ $result['title'] }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground-1">{{ $result['subtitle'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
