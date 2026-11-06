{{-- One confirmation dialog for the whole app (opened by data-confirm, see resources/js/app.js). --}}
<div x-data="{
        open: false, title: '', message: '', button: '', tone: 'danger', run: null,
        show(detail) {
            this.title = detail.title || @js(__('Are you sure?'));
            this.message = detail.message;
            this.button = detail.button || (detail.tone === 'primary' ? @js(__('Yes, continue')) : @js(__('Yes, remove')));
            this.tone = detail.tone;
            this.run = detail.run;
            this.open = true;
            this.$nextTick(() => this.$refs.cancel.focus());
        },
        confirm() { this.open = false; if (this.run) this.run(); },
    }"
    x-on:confirm-dialog.window="show($event.detail)"
    x-on:keydown.escape.window="open = false"
    x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[96] bg-gray-900/50 dark:bg-neutral-900/80" x-on:click="open = false"></div>
    <div x-show="open" x-transition class="fixed inset-0 z-[97] flex items-start justify-center p-4 pt-[18vh]" role="alertdialog" aria-modal="true" :aria-label="title">
        <div class="w-full max-w-md rounded-xl border border-overlay-line bg-overlay shadow-2xl" x-on:click.stop>
            <div class="flex gap-4 p-5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full"
                    :class="tone === 'danger' ? 'bg-red-50 text-red-600 dark:bg-red-500/15' : 'bg-primary-50 text-primary dark:bg-primary-500/15'">
                    <x-icon name="warning" class="size-5" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 class="text-[15px] font-semibold text-foreground" x-text="title"></h3>
                    <p class="mt-1 text-[13px] text-muted-foreground-2" x-text="message"></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 rounded-b-xl border-t border-overlay-divider bg-overlay-footer px-5 py-3">
                <button type="button" x-ref="cancel" x-on:click="open = false" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="button" x-on:click="confirm()" :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'" x-text="button"></button>
            </div>
        </div>
    </div>
</div>
