{{-- Light / Dark / System theme choice (Preline theme switch, saved in this browser). --}}
<div x-data="{ theme: localStorage.getItem('hs_theme') || 'light' }"
    x-on:click="$nextTick(() => theme = localStorage.getItem('hs_theme') || 'light')"
    {{ $attributes->merge(['class' => 'inline-flex rounded-md bg-surface p-0.5 text-xs']) }} role="radiogroup" aria-label="{{ __('Theme') }}">
    @foreach ([['light', __('Light'), 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364-.707-.707M6.343 6.343l-.707-.707m12.728 0-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'], ['dark', __('Dark'), 'M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z'], ['auto', __('System'), 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25']] as [$value, $label, $path])
        <button type="button" data-hs-theme-click-value="{{ $value }}" role="radio" :aria-checked="theme === '{{ $value }}'"
            :class="theme === '{{ $value }}' ? 'bg-layer text-foreground shadow-[0_1px_2px_rgb(0_0_0/0.08)]' : 'text-muted-foreground-1 hover:text-foreground'"
            class="flex flex-1 items-center justify-center gap-1 rounded px-2 py-0.5 font-medium transition-colors" title="{{ $label }}">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
            {{ $label }}
        </button>
    @endforeach
</div>
