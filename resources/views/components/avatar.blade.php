@props(['name', 'size' => 'size-8'])

@php
    $colors = ['bg-primary-100 text-primary-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-800', 'bg-sky-100 text-sky-700', 'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700'];
    $words = preg_split('/\s+/u', trim((string) $name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    $color = $colors[abs(crc32((string) $name)) % count($colors)];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full text-xs font-semibold $size $color"]) }}>{{ $initials ?: '?' }}</span>
