@props(['paginator'])

{{-- "Showing 1–25 of 120", rows per page and page buttons. --}}
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-card-line bg-muted px-4 py-2.5 text-xs text-muted-foreground-2">
    <div class="flex items-center gap-3">
        <span>
            @if ($paginator->total() > 0)
                {{ __('Showing :from–:to of :total', ['from' => shop_digits($paginator->firstItem()), 'to' => shop_digits($paginator->lastItem()), 'total' => shop_digits($paginator->total())]) }}
            @else
                {{ __('No records') }}
            @endif
        </span>
        <label class="flex items-center gap-1.5">
            {{ __('Rows') }}
            <select wire:model.live="perPage" class="rounded-md border border-layer-line bg-layer py-1 pl-2 pr-7 text-xs">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </label>
    </div>
    @if ($paginator->hasPages())
        <nav class="flex items-center gap-1" aria-label="{{ __('Pages') }}">
            <button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage()) class="rounded-md border border-layer-line bg-layer px-2 py-1 hover:bg-layer-hover disabled:opacity-40" aria-label="{{ __('Previous') }}">‹</button>
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
            @endphp
            @foreach ($pages as $i => $page)
                @if ($i > 0 && $page - $pages[$i - 1] > 1)<span class="px-1 text-muted-foreground">…</span>@endif
                <button type="button" wire:click="gotoPage({{ $page }})" @class(['min-w-7 rounded-md border px-2 py-1', 'border-primary-600 bg-primary-600 text-white' => $page === $current, 'border-layer-line bg-layer hover:bg-layer-hover' => $page !== $current])>{{ shop_digits($page) }}</button>
            @endforeach
            <button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) class="rounded-md border border-layer-line bg-layer px-2 py-1 hover:bg-layer-hover disabled:opacity-40" aria-label="{{ __('Next') }}">›</button>
        </nav>
    @endif
</div>
