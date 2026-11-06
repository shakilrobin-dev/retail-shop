@props(['steps', 'current', 'action' => null, 'disabled' => false])

{{--
    Odoo-style status bar: Received › Diagnosing › Repairing › Ready › Delivered.
    $steps = [value => label]. If $action is given, clicking a step calls that Livewire method.
--}}
@php $keys = array_keys($steps); $currentIndex = array_search($current, $keys, true); @endphp
<div class="inline-flex overflow-hidden rounded-lg border border-slate-300 bg-white text-xs font-medium">
    @foreach ($steps as $value => $label)
        @php
            $index = $loop->index;
            $state = $index === $currentIndex ? 'current' : ($currentIndex !== false && $index < $currentIndex ? 'done' : 'todo');
        @endphp
        <button type="button"
            @if ($action && ! $disabled && $state !== 'current') wire:click="{{ $action }}('{{ $value }}')" @else disabled @endif
            @class([
                'relative flex items-center gap-1.5 px-3.5 py-1.5 transition',
                'border-l border-slate-300' => ! $loop->first,
                'bg-brand-600 text-white' => $state === 'current',
                'bg-white text-slate-700 hover:bg-slate-50' => $state === 'done' && $action && ! $disabled,
                'bg-white text-slate-700' => $state === 'done' && (! $action || $disabled),
                'bg-white text-slate-400 hover:bg-slate-50 hover:text-slate-600' => $state === 'todo' && $action && ! $disabled,
                'bg-white text-slate-400' => $state === 'todo' && (! $action || $disabled),
            ])>
            @if ($state === 'done')<svg class="size-3 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>@endif
            {{ $label }}
        </button>
    @endforeach
</div>
