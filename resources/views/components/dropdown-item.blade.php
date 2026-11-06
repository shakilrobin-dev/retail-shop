@props(['icon' => null, 'href' => null, 'danger' => false])

@php $classes = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm '.($danger ? 'text-red-600 hover:bg-red-50' : 'text-slate-700 hover:bg-slate-100'); @endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" class="size-4 text-slate-400" />@endif {{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" class="size-4 text-slate-400" />@endif {{ $slot }}</button>
@endif
