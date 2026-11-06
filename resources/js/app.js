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

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(initPreline));
    });
});
