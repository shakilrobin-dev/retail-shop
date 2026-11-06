@props(['label' => null, 'icon' => 'chevron-down', 'align' => 'right', 'width' => 'min-w-52', 'trigger' => null])

{{-- Preline dropdown ("Action ▾"). Put <x-dropdown-item> elements inside. --}}
@php $id = 'dd-'.\Illuminate\Support\Str::random(6); @endphp
<div class="hs-dropdown relative inline-flex [--placement:bottom-{{ $align === 'right' ? 'right' : 'left' }}]">
    @if ($trigger)
        <button id="{{ $id }}" type="button" class="hs-dropdown-toggle btn-ghost btn-sm" aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('More actions') }}">{{ $trigger }}</button>
    @else
        <button id="{{ $id }}" type="button" class="hs-dropdown-toggle btn-secondary" aria-haspopup="menu" aria-expanded="false">
            {{ $label ?? __('Action') }} <x-icon :name="$icon" class="hs-dropdown-open:rotate-180 size-4 text-muted-foreground-1 transition" />
        </button>
    @endif
    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 duration z-30 hidden {{ $width }} rounded-xl bg-dropdown opacity-0 shadow-md transition-[opacity,margin]" role="menu" aria-orientation="vertical" aria-labelledby="{{ $id }}">
        <div class="space-y-0.5 p-1.5">{{ $slot }}</div>
    </div>
</div>
