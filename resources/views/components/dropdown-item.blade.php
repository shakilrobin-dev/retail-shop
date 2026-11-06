@props(['icon' => null, 'href' => null, 'danger' => false])

@php $classes = 'flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-start text-sm focus:outline-hidden '.($danger ? 'text-red-600 hover:bg-red-50 focus:bg-red-50 dark:hover:bg-red-500/10' : 'text-dropdown-item-foreground hover:bg-dropdown-item-hover focus:bg-dropdown-item-focus'); @endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" class="size-4 shrink-0 opacity-70" />@endif {{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" class="size-4 shrink-0 opacity-70" />@endif {{ $slot }}</button>
@endif
