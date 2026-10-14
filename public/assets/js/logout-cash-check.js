(() => {
    const button = document.getElementById('logout-cash-check');
    if (!button) return;
    button.addEventListener('click', async () => {
        if (button.disabled) return;
        button.disabled = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(button.dataset.statusUrl, {
                cache: 'no-store', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: controller.signal,
            });
            if (response.status === 401) { window.location.assign(button.dataset.logoutUrl); return; }
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (typeof data.open !== 'boolean') throw new Error();
            if (data.open) bootstrap.Modal.getOrCreateInstance(document.getElementById('openCashModal')).show();
            else window.location.assign(button.dataset.logoutUrl);
        } catch (_) {
            window.alert('No se pudo verificar el estado de tu caja. Vuelve a intentar salir.');
        } finally {
            clearTimeout(timeout);
            button.disabled = false;
        }
    });
})();
