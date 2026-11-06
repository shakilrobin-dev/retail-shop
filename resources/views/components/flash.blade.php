@props(['global' => false])

{{--
    Toast messages (top-right).
    The layout renders it with "global" on full page loads (messages after a redirect).
    Inside components it only shows messages from Livewire updates, so nothing shows twice.
--}}
@php
    $isLivewireUpdate = request()->hasHeader('X-Livewire');
    $show = ($global && ! $isLivewireUpdate) || (! $global && $isLivewireUpdate);
    $success = $show ? session('success') : null;
    $error = $show ? session('error') : null;
@endphp
<div wire:key="flash-{{ $global ? 'global' : 'local' }}-{{ $success || $error ? uniqid() : 'none' }}" x-data="{
        toasts: [],
        add(type, message) {
            if (! message) return;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message });
            setTimeout(() => this.remove(id), type === 'error' ? 8000 : 4500);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
    }"
    x-init="
        @if ($success) add('success', @js($success)); @endif
        @if ($error) add('error', @js($error)); @endif
    "
    @if ($global) x-on:toast.window="add($event.detail.type ?? 'success', $event.detail.message)" @endif
    class="pointer-events-none fixed bottom-4 right-4 z-[95] flex w-full max-w-sm flex-col gap-2">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity.duration.200ms
            class="pointer-events-auto flex items-start gap-3 rounded-lg border bg-layer p-3 shadow-lg"
            :class="toast.type === 'error' ? 'border-red-200 dark:border-red-500/30' : 'border-card-line'">
            <span class="mt-0.5 size-2 shrink-0 rounded-full" :class="toast.type === 'error' ? 'bg-red-500' : 'bg-emerald-500'"></span>
            <p class="flex-1 text-[13px] text-foreground" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="text-muted-foreground hover:text-muted-foreground-2" aria-label="{{ __('Close') }}">✕</button>
        </div>
    </template>
</div>
