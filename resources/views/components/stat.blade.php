@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])

{{-- Compact metric (used in summary strips above lists). --}}
@php
    $map = ['brand' => 'indigo', 'green' => 'emerald', 'amber' => 'amber', 'red' => 'rose', 'sky' => 'sky', 'slate' => 'slate', 'violet' => 'violet', 'indigo' => 'indigo', 'emerald' => 'emerald', 'rose' => 'rose'];
@endphp
<x-kpi :label="$label" :value="$value" :hint="$hint" :icon="$icon" :tone="$map[$tone] ?? 'indigo'" :href="$href" {{ $attributes }} />
