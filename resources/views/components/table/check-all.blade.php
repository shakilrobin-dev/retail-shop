@props(['ids', 'selected'])

@php
    $ids = collect($ids)->map(fn ($id) => (string) $id)->all();
    $all = $ids !== [] && array_diff($ids, $selected) === [];
    $some = ! $all && array_intersect($ids, $selected) !== [];
@endphp
<th class="w-10 !pr-0">
    <input type="checkbox" wire:click="togglePage(@js($ids))" @checked($all) x-init="$el.indeterminate = @js($some)"
        class="rounded border-layer-line text-primary-600" aria-label="{{ __('Select all on this page') }}">
</th>
