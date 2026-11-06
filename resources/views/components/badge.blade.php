@props(['tone' => 'slate'])

@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700',
        'brand' => 'bg-brand-50 text-brand-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-800',
        'red' => 'bg-red-50 text-red-700',
        'sky' => 'bg-sky-50 text-sky-700',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($tones[$tone] ?? $tones['slate'])]) }}>{{ $slot }}</span>
