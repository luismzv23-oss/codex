document.addEventListener('alpine:init', () => {
 Alpine.data('cashWorkspace', () => ({
 init() {
    // One refresh path updates KPIs, register states, operators and actions together.
    const refresh = () => {
        if (document.hidden || document.querySelector('.modal.show, dialog[open]')) return;
        if (typeof window.reloadCashData === 'function') return window.reloadCashData();
    };
    document.addEventListener('click', (event) => {
        if (event.target.closest('#cash-dashboard-refresh')) {
            event.preventDefault();
            refresh();
        }
    });
    // A seller may have opened/closed the register in another tab or window.
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
 }
 }));
});
