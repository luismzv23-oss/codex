// A new request for every search; obsolete responses must never replace current results.
window.createSalesProductSearch = function (endpoint) {
    let request = null;
    let version = 0;
    return async function (term) {
        const current = ++version;
        request?.abort();
        request = new AbortController();
        const url = new URL(endpoint, window.location.href);
        url.searchParams.set('q', term);
        try {
            const response = await fetch(url, {
                cache: 'no-store', signal: request.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) throw new Error('No se pudieron actualizar los productos. Intenta buscar nuevamente.');
            const payload = await response.json();
            if (!Array.isArray(payload.products)) throw new Error('Respuesta de productos inválida. Recarga la página.');
            return current === version ? payload.products : null;
        } catch (error) {
            if (current !== version || error.name === 'AbortError') return null;
            throw error;
        }
    };
};
