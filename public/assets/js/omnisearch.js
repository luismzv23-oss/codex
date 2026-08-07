/**
 * Codex ERP — OmniSearch Keyboard Console (Cmd+K / Ctrl+K)
 * Fast in-memory module navigation & search controller.
 */
document.addEventListener('DOMContentLoaded', () => {
    const modalHtml = `
        <div id="omnisearch-overlay" style="display:none; position:fixed; inset:0; background:rgba(9,13,22,0.8); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center; padding:1rem;">
            <div style="width:100%; max-width:640px; background:#111827; border:1px solid rgba(255,255,255,0.15); border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); overflow:hidden;">
                <div style="display:flex; align-items:center; padding:1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.08); gap:0.75rem;">
                    <svg width="20" height="20" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="omnisearch-input" type="text" placeholder="Buscar módulo, cliente, producto o acción (Cmd+K)..." style="width:100%; background:transparent; border:none; color:#f9fafb; font-size:1.05rem; font-family:'Plus Jakarta Sans', sans-serif; outline:none;">
                    <span style="font-size:0.75rem; padding:0.25rem 0.5rem; background:rgba(255,255,255,0.1); border-radius:0.375rem; color:#9ca3af;">ESC</span>
                </div>
                <div id="omnisearch-results" style="max-height:360px; overflow-y:auto; padding:0.75rem;">
                    <!-- Results populated dynamically -->
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const overlay = document.getElementById('omnisearch-overlay');
    const input = document.getElementById('omnisearch-input');
    const results = document.getElementById('omnisearch-results');

    const defaultItems = [
        { label: 'Ir a Punto de Venta (POS)', url: '/ventas/pos', icon: '🛒', group: 'Ventas' },
        { label: 'Ir a Kiosco Autoservicio', url: '/ventas/kiosco', icon: '📱', group: 'Ventas' },
        { label: 'Ver Movimientos de Inventario', url: '/inventario', icon: '📦', group: 'Inventario' },
        { label: 'Ver Kardex Valorizado', url: '/inventario/kardex', icon: '📊', group: 'Inventario' },
        { label: 'Ver Cajas y Sesiones', url: '/caja', icon: '💵', group: 'Tesorería' },
        { label: 'Ver Plan de Cuentas', url: '/contabilidad', icon: '📈', group: 'Contabilidad' },
        { label: 'Ver Dashboard BI', url: '/bi/executive', icon: '⚡', group: 'Analítica' },
    ];

    function openOmniSearch() {
        overlay.style.display = 'flex';
        input.value = '';
        renderResults(defaultItems);
        setTimeout(() => input.focus(), 50);
    }

    function closeOmniSearch() {
        overlay.style.display = 'none';
    }

    function renderResults(items) {
        if (items.length === 0) {
            results.innerHTML = '<div style="padding:1rem; text-align:center; color:#6b7280;">No se encontraron resultados</div>';
            return;
        }
        results.innerHTML = items.map((item, idx) => `
            <a href="${item.url}" style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem; border-radius:0.75rem; color:#f9fafb; text-decoration:none; margin-bottom:0.25rem; transition:background 150ms;" onmouseover="this.style.background='rgba(79,70,229,0.15)'" onmouseout="this.style.background='transparent'">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <span style="font-size:1.2rem;">${item.icon}</span>
                    <span style="font-weight:600;">${item.label}</span>
                </div>
                <span style="font-size:0.75rem; color:#9ca3af; background:rgba(255,255,255,0.05); padding:0.2rem 0.5rem; border-radius:0.375rem;">${item.group}</span>
            </a>
        `).join('');
    }

    // Keyboard shortcut listeners
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (overlay.style.display === 'flex') {
                closeOmniSearch();
            } else {
                openOmniSearch();
            }
        } else if (e.key === 'Escape' && overlay.style.display === 'flex') {
            closeOmniSearch();
        }
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeOmniSearch();
    });

    input.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        if (!query) {
            renderResults(defaultItems);
            return;
        }
        const filtered = defaultItems.filter(i => i.label.toLowerCase().includes(query) || i.group.toLowerCase().includes(query));
        renderResults(filtered);
    });
});
