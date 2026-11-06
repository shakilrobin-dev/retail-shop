{{-- Apply saved light/dark theme before the page paints (Preline theme switch). --}}
<script>
    (() => {
        const apply = () => {
            const html = document.documentElement;
            const saved = localStorage.getItem('hs_theme') || 'light';
            const dark = saved === 'dark' || (saved === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            html.classList.toggle('dark', dark);
            html.classList.toggle('light', ! dark);
        };
        apply();
        document.addEventListener('livewire:navigated', apply);
    })();
</script>
