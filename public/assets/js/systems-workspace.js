document.addEventListener('alpine:init', () => {
 Alpine.data('systemsWorkspace', () => ({
 init() {
    const activePaginators = new Map();

    // Universal Table Pagination (5 items per page)
    function setupTablePagination(tableId, pageSize = 5) {
        const table = document.getElementById(tableId);
        if (!table) return null;

        const tableResponsive = table.closest('.table-responsive');
        if (!tableResponsive) return null;

        let paginationWrapper = tableResponsive.parentNode.querySelector(`.codex-pagination[data-for="${tableId}"]`);
        if (!paginationWrapper) {
            paginationWrapper = document.createElement('div');
            paginationWrapper.className = 'codex-pagination';
            paginationWrapper.dataset.for = tableId;
            tableResponsive.after(paginationWrapper);
        }

        let currentPage = 1;

        function render() {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const rows = Array.from(tbody.querySelectorAll('tr.systems-row'));
            const totalItems = rows.length;

            if (totalItems <= pageSize) {
                rows.forEach(r => {
                    r.classList.remove('d-none');
                    r.style.display = '';
                });
                paginationWrapper.innerHTML = '';
                paginationWrapper.classList.add('d-none');
                return;
            }

            paginationWrapper.classList.remove('d-none');
            const pageCount = Math.ceil(totalItems / pageSize);
            if (currentPage > pageCount) currentPage = pageCount;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach((row, index) => {
                const isVisible = index >= startIndex && index < endIndex;
                row.classList.toggle('d-none', !isVisible);
                row.style.display = isVisible ? '' : 'none';
            });

            paginationWrapper.innerHTML = '';

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, totalItems)} de ${totalItems} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First (<<)
            if (pageCount > 4) {
                const first = document.createElement('button');
                first.type = 'button';
                first.className = 'codex-pagination__btn';
                first.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                first.disabled = currentPage === 1;
                first.title = 'Primera página';
                first.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage = 1;
                        render();
                    }
                });
                controls.appendChild(first);
            }

            // Prev (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    render();
                }
            });
            controls.appendChild(prev);

            // Page numbers
            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(pageCount, currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.title = `Página ${p}`;
                btn.addEventListener('click', () => {
                    currentPage = p;
                    render();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', () => {
                if (currentPage < pageCount) {
                    currentPage++;
                    render();
                }
            });
            controls.appendChild(next);

            // Last (>>)
            if (pageCount > 4) {
                const last = document.createElement('button');
                last.type = 'button';
                last.className = 'codex-pagination__btn';
                last.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                last.disabled = currentPage === pageCount;
                last.title = 'Última página';
                last.addEventListener('click', () => {
                    if (currentPage < pageCount) {
                        currentPage = pageCount;
                        render();
                    }
                });
                controls.appendChild(last);
            }

            paginationWrapper.appendChild(summary);
            paginationWrapper.appendChild(controls);
        }

        render();

        return {
            refresh: () => {
                currentPage = 1;
                render();
            },
            render
        };
    }

    // Initialize table paginators (5 items per page)
    function initAllTablePagination() {
        ['company-assignments-table', 'user-permissions-table', 'catalog-systems-table'].forEach(id => {
            const paginator = setupTablePagination(id, 5);
            if (paginator) {
                activePaginators.set(id, paginator);
            }
        });
    }
    initAllTablePagination();

    // Bind async action forms (toggles, deletes) without full reload
    function bindAsyncForms() {
        document.querySelectorAll('.systems-async-action-form').forEach(form => {
            form.onsubmit = async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button');
                if (btn) btn.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (window.showCodexToast) {
                        window.showCodexToast('Operación completada con éxito.', 'success');
                    }
                    await reloadSystemsData();
                } catch (err) {
                    console.error('Error en acción de sistemas:', err);
                    form.submit();
                }
            };
        });
    }
    bindAsyncForms();

    // Seamless AJAX refresh of the entire Systems Workspace
    let isSystemsReloading = false;
    async function reloadSystemsData() {
        if (isSystemsReloading) return;
        isSystemsReloading = true;

        try {
            const response = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Error al recargar sistemas');

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Update Available Systems Cards Grid (all systems visible)
            const newAvail = doc.getElementById('systems-available-container');
            const curAvail = document.getElementById('systems-available-container');
            if (newAvail && curAvail) {
                curAvail.innerHTML = newAvail.innerHTML;
            }

            // 2. Update Company Assignments Table
            const newCompTable = doc.getElementById('company-assignments-table');
            const curCompTable = document.getElementById('company-assignments-table');
            if (newCompTable && curCompTable) {
                const newTbody = newCompTable.querySelector('tbody');
                const curTbody = curCompTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('company-assignments-table')?.refresh();
            }

            // 3. Update User Permissions Table
            const newUserTable = doc.getElementById('user-permissions-table');
            const curUserTable = document.getElementById('user-permissions-table');
            if (newUserTable && curUserTable) {
                const newTbody = newUserTable.querySelector('tbody');
                const curTbody = curUserTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('user-permissions-table')?.refresh();
            }

            // 4. Update Catalog Systems Table
            const newCatTable = doc.getElementById('catalog-systems-table');
            const curCatTable = document.getElementById('catalog-systems-table');
            if (newCatTable && curCatTable) {
                const newTbody = newCatTable.querySelector('tbody');
                const curTbody = curCatTable.querySelector('tbody');
                if (newTbody && curTbody) {
                    curTbody.innerHTML = newTbody.innerHTML;
                }
                activePaginators.get('catalog-systems-table')?.refresh();
            }

            // 5. Re-bind async forms & popups
            bindAsyncForms();
            if (typeof window.initCodexPopups === 'function') {
                window.initCodexPopups();
            }
        } catch (err) {
            console.error('Error al actualizar datos de sistemas:', err);
        } finally {
            isSystemsReloading = false;
        }
    }
    window.reloadSystemsData = reloadSystemsData;

    // Listen for real-time creation / modifications across systems
    window.addEventListener('codex:system-saved', () => reloadSystemsData());
    window.addEventListener('codex:item-saved', () => reloadSystemsData());
    window.addEventListener('message', (event) => {
        if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) {
            reloadSystemsData();
        }
    });
 }
 }));
});