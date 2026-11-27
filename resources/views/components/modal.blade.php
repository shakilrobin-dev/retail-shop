@props(['title', 'subtitle' => null, 'width' => 'max-w-lg'])

{{--
    Centered pop-up dialog.
    Usage: <x-modal wire:model="showForm" title="..."> ...content... <x-slot:footer>buttons</x-slot:footer></x-modal>
--}}
<div x-data="{ open: @entangle($attributes->wire('model')) }" x-on:keydown.escape.window="open = false" x-cloak class="no-print">
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-80 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center dark:bg-neutral-900/80" x-on:click.self="open = false">
        <div x-show="open" x-transition role="dialog" aria-modal="true"
            class="my-auto flex max-h-[calc(100dvh-2rem)] w-full {{ $width }} flex-col rounded-xl border border-overlay-line bg-overlay shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-overlay-divider px-5 py-3.5">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-foreground">{{ $title }}</h2>
                    @if ($subtitle)<p class="mt-0.5 text-sm text-muted-foreground-1">{{ $subtitle }}</p>@endif
                </div>
                <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-muted-foreground hover:bg-muted-hover hover:text-muted-foreground-2" aria-label="{{ __('Close') }}"><x-icon name="close" /></button>
            </div>
            <div class="scroll-thin flex-1 overflow-y-auto px-5 py-4">{{ $slot }}</div>
            @isset($footer)
                <div class="flex justify-end gap-2 rounded-b-xl border-t border-overlay-divider bg-overlay-footer px-5 py-3">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
