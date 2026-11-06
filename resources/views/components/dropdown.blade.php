@props(['label' => null, 'icon' => 'chevron-down', 'align' => 'right', 'width' => 'w-52'])

{{-- Action menu ("Action ▾"). Put <x-dropdown-item> elements inside. --}}
<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <button type="button" x-on:click="open = !open" class="btn-secondary">
        {{ $label ?? __('Action') }} <x-icon :name="$icon" class="size-4 text-slate-400" />
    </button>
    <div x-cloak x-show="open" x-transition.origin.top class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-30 mt-1.5 {{ $width }} rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg" x-on:click="open = false">
        {{ $slot }}
    </div>
</div>
