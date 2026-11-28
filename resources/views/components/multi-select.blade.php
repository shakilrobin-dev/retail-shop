@props(['label', 'options' => [], 'placeholder' => null])

{{--
    Dropdown with a search box where one or more items can be ticked. Use with wire:model on a Livewire array property.
    The choice is sent when the dropdown closes (or at once when cleared), so the page is not reloaded on every tick.
    $options: [id => label] or [['id' => 1, 'label' => 'Tiles', 'hint' => 'Floor Tiles, Wall Tiles'], ...]
--}}
@php
    $items = collect($options)->map(fn ($option, $key): array => is_array($option)
        ? ['id' => (int) $option['id'], 'label' => (string) $option['label'], 'hint' => $option['hint'] ?? null]
        : ['id' => (int) $key, 'label' => (string) $option, 'hint' => null])->values();
    $placeholder ??= __('All');
@endphp
<div {{ $attributes->merge(['class' => 'relative w-full sm:w-52']) }}
    x-data="{
        open: false,
        search: '',
        selected: [],
        before: '',
        options: @js($items),
        get ids() { return Array.isArray(this.selected) ? this.selected.map(Number) : (this.selected ? [Number(this.selected)] : []) },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            return q === '' ? this.options : this.options.filter(o => (o.label + ' ' + (o.hint ?? '')).toLowerCase().includes(q));
        },
        get shown() { return this.filtered.slice(0, 200) },
        get chosen() { return this.options.filter(o => this.ids.includes(o.id)) },
        get summary() {
            const names = this.chosen.map(o => o.label);
            if (names.length === 0) return @js($placeholder);
            return names.length <= 2 ? names.join(', ') : names[0] + ' +' + (names.length - 1);
        },
        isOn(id) { return this.ids.includes(id) },
        toggle(id) { this.selected = this.isOn(id) ? this.ids.filter(v => v !== id) : [...this.ids, id] },
        selectShown() { this.selected = [...new Set([...this.ids, ...this.filtered.map(o => o.id)])] },
        key() { return JSON.stringify([...this.ids].sort((a, b) => a - b)) },
        show() { this.open = true; this.before = this.key(); this.$nextTick(() => this.$refs.search.focus()) },
        close() {
            if (! this.open) return;
            this.open = false;
            this.search = '';
            if (this.key() !== this.before) $wire.$commit();
        },
        clear() { this.selected = []; if (! this.open) { $wire.$commit() } },
    }"
    x-modelable="selected"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="close()">
    <label class="form-label mb-1 text-xs">{{ $label }}</label>
    <button type="button" x-on:click="open ? close() : show()" x-bind:aria-expanded="open" x-bind:aria-label="@js($label) + ': ' + summary"
        class="form-input flex w-full items-center gap-2 py-1.5 text-left text-[13px]"
        x-bind:class="ids.length ? 'border-primary-400 ring-1 ring-primary-200 dark:ring-primary-500/30' : ''">
        <span class="min-w-0 flex-1 truncate" x-bind:class="ids.length ? 'font-medium text-foreground' : 'text-muted-foreground-1'" x-text="summary"></span>
        <span x-show="ids.length" x-cloak x-on:click.stop="clear()" class="rounded px-1 text-muted-foreground hover:text-red-600" title="{{ __('Clear') }}" aria-label="{{ __('Clear') }}">✕</span>
        <x-icon name="chevron-down" class="size-3.5 shrink-0 text-muted-foreground" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
        class="absolute left-0 z-40 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-card-line bg-card shadow-lg">
        <div class="border-b border-card-line p-2">
            <input x-ref="search" x-model="search" type="search" placeholder="{{ __('Search...') }}" class="form-input py-1.5 text-[13px]"
                x-on:keydown.enter.prevent="filtered.length === 1 && toggle(filtered[0].id)">
            <div class="mt-1.5 flex items-center justify-between text-xs">
                <button type="button" x-on:click="selectShown()" class="font-medium text-primary-700 hover:underline dark:text-primary-400">{{ __('Select shown') }}</button>
                <span class="text-muted-foreground" x-text="ids.length ? @js(__(':count selected')).replace(':count', ids.length) : ''"></span>
                <button type="button" x-on:click="selected = []" class="text-muted-foreground-2 hover:text-red-600">{{ __('Clear') }}</button>
            </div>
        </div>
        <ul class="max-h-64 overflow-y-auto py-1 text-[13px]" role="listbox" aria-multiselectable="true">
            <template x-for="option in shown" x-bind:key="option.id">
                <li>
                    <label class="flex cursor-pointer items-start gap-2 px-3 py-1.5 hover:bg-muted-hover">
                        <input type="checkbox" class="mt-0.5 rounded border-layer-line" x-bind:checked="isOn(option.id)" x-on:change="toggle(option.id)">
                        <span class="min-w-0">
                            <span class="block truncate" x-text="option.label"></span>
                            <span x-show="option.hint" class="block truncate text-[11px] text-muted-foreground" x-text="option.hint"></span>
                        </span>
                    </label>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-3 text-center text-muted-foreground-1">{{ __('Nothing found.') }}</li>
            <li x-show="filtered.length > 200" class="px-3 py-2 text-center text-[11px] text-muted-foreground" x-text="@js(__('Showing 200 of :count. Type to narrow down.')).replace(':count', filtered.length)"></li>
        </ul>
        <div class="flex justify-end border-t border-card-line p-2">
            <button type="button" x-on:click="close()" class="btn-primary btn-sm">{{ __('Apply') }}</button>
        </div>
    </div>
</div>
