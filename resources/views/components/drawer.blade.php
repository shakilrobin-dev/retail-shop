@props(['title', 'subtitle' => null, 'width' => 'max-w-xl'])

{{--
    Slide-in panel from the right (used for quick create / edit forms).
    Usage: <x-drawer wire:model="showForm" title="..."> ...fields... <x-slot:footer>buttons</x-slot:footer></x-drawer>
--}}
<div x-data="{ open: @entangle($attributes->wire('model')) }" x-on:keydown.escape.window="open = false" x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-70 bg-gray-900/50 dark:bg-neutral-900/80" x-on:click="open = false"></div>
    <div x-show="open"
        x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 end-0 z-80 flex w-full {{ $width }} flex-col border-s border-overlay-line bg-overlay shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-overlay-divider px-6 py-4">
            <div>
                <h2 class="text-base font-semibold text-foreground">{{ $title }}</h2>
                @if ($subtitle)<p class="mt-0.5 text-sm text-muted-foreground-1">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-muted-foreground hover:bg-muted-hover hover:text-muted-foreground-2" aria-label="{{ __('Close') }}"><x-icon name="close" /></button>
        </div>
        <div class="scroll-thin flex-1 overflow-y-auto px-6 py-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-overlay-divider bg-overlay-footer px-6 py-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
