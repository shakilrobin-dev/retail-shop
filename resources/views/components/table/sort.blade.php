@props(['field', 'sort' => '', 'dir' => '', 'align' => 'left'])

{{-- Sortable column header. Click to sort, click again to reverse. --}}
@php $active = $sort === $field; @endphp
<th {{ $attributes->merge(['class' => $align === 'right' ? 'text-right' : '']) }} aria-sort="{{ $active ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none' }}">
    <button type="button" wire:click="sortBy('{{ $field }}')"
        class="group inline-flex items-center gap-1 {{ $active ? 'text-foreground' : 'hover:text-foreground' }} {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
        <span>{{ $slot }}</span>
        <svg class="size-3 {{ $active ? 'text-primary-600' : 'text-muted-foreground group-hover:text-muted-foreground' }}" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
            @if ($active && $dir === 'desc')
                <path d="M6 9 2.5 4.5h7L6 9Z" />
            @elseif ($active)
                <path d="M6 3 9.5 7.5h-7L6 3Z" />
            @else
                <path d="M6 1.5 8.5 4.5h-5L6 1.5ZM6 10.5 3.5 7.5h5L6 10.5Z" />
            @endif
        </svg>
    </button>
</th>
