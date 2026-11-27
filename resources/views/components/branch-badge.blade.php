@props(['branch'])

{{-- Shows which branch the user is working in, so entries are not made in the wrong branch. --}}
@if ($branch)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300']) }}>
        <x-icon name="building" class="size-3.5" /> {{ __('Branch') }}: {{ $branch->localName() }}
    </span>
@endif
