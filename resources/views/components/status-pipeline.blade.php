@props(['steps', 'current', 'action' => null, 'disabled' => false])

{{--
    Odoo-style status bar: Received › Diagnosing › Repairing › Ready › Delivered.
    $steps = [value => label]. If $action is given, clicking a step calls that Livewire method.
--}}
@php $keys = array_keys($steps); $currentIndex = array_search($current, $keys, true); @endphp
<div class="inline-flex overflow-hidden rounded-md border border-layer-line bg-layer text-xs font-medium shadow-[0_1px_2px_rgb(0_0_0/0.04)]">
    @foreach ($steps as $value => $label)
        @php
            $index = $loop->index;
            $state = $index === $currentIndex ? 'current' : ($currentIndex !== false && $index < $currentIndex ? 'done' : 'todo');
        @endphp
        <button type="button"
            @if ($action && ! $disabled && $state !== 'current') wire:click="{{ $action }}('{{ $value }}')" @else disabled @endif
            @class([
                'relative flex h-7 items-center gap-1.5 px-3 transition-colors',
                'border-l border-layer-line' => ! $loop->first,
                'bg-foreground text-background' => $state === 'current',
                'bg-layer text-foreground hover:bg-layer-hover' => $state === 'done' && $action && ! $disabled,
                'bg-layer text-foreground' => $state === 'done' && (! $action || $disabled),
                'bg-layer text-muted-foreground hover:bg-layer-hover hover:text-muted-foreground-2' => $state === 'todo' && $action && ! $disabled,
                'bg-layer text-muted-foreground' => $state === 'todo' && (! $action || $disabled),
            ])>
            @if ($state === 'done')<svg class="size-3 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>@endif
            {{ $label }}
        </button>
    @endforeach
</div>
