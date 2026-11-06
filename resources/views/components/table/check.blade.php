@props(['value'])
<td class="w-10 !pr-0" x-on:click.stop>
    <input type="checkbox" wire:model.live="selected" value="{{ $value }}" class="rounded border-slate-300 text-brand-600" aria-label="{{ __('Select row') }}">
</td>
