@props(['tone' => 'slate'])

@php
    $tones = [
        'slate' => 'bg-surface text-foreground',
        'brand' => 'bg-primary-100 text-primary-800 dark:bg-primary-500/20 dark:text-primary-400',
        'green' => 'bg-teal-100 text-teal-800 dark:bg-teal-500/10 dark:text-teal-500',
        'amber' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/10 dark:text-yellow-500',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-500',
        'sky' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-500',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($tones[$tone] ?? $tones['slate'])]) }}>{{ $slot }}</span>
