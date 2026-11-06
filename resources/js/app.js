import 'preline';

/**
 * Preline components (dropdowns, tooltips, tabs...) attach to the page when it loads.
 * Livewire changes the page without a reload, so set them up again after
 * page navigation and after each Livewire update.
 */
const initPreline = () => {
    window.HSStaticMethods?.autoInit();
};

document.addEventListener('livewire:navigated', () => setTimeout(initPreline, 0));

const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ commit, succeed }) => {
        succeed(({ snapshot }) => {
            queueMicrotask(initPreline);

            // After a button/action (not while typing), show the first form error as a toast.
            if (! commit.calls?.length) {
                return;
            }

            try {
                const data = typeof snapshot === 'string' ? JSON.parse(snapshot) : snapshot;
                const errors = data?.memo?.errors ?? {};
                const first = Object.values(errors).flat()[0];

                if (first) {
                    toast('error', first);
                }
            } catch (e) {
                // Ignore snapshots that cannot be read.
            }
        });
    });
});

/**
 * Confirmation before risky actions (delete, remove, archive, void...).
 *
 * Add data-confirm="Question?" to any button or link. The click is held back,
 * a confirm dialog is shown, and the original click runs only after "Yes".
 * Optional: data-confirm-title, data-confirm-button, data-confirm-tone="danger|primary".
 */
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');

    if (! trigger || trigger.dataset.confirmed === '1') {
        if (trigger) {
            delete trigger.dataset.confirmed;
        }

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    window.dispatchEvent(new CustomEvent('confirm-dialog', {
        detail: {
            title: trigger.dataset.confirmTitle || null,
            message: trigger.dataset.confirm,
            button: trigger.dataset.confirmButton || null,
            tone: trigger.dataset.confirmTone || 'danger',
            run: () => {
                trigger.dataset.confirmed = '1';
                trigger.click();
            },
        },
    }));
}, true);
