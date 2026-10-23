/* Inventory pilot: Alpine owns initialization and the toolbar.
 * Existing pagination, server refresh and domain events retain their contracts. */
document.addEventListener('alpine:init', () => {
    Alpine.data('inventoryWorkspace', () => ({
        searchChanged() { this._searchChanged(); },
        clearSearch() { this._clearSearch(); },
        selectFilter(event) { this._selectFilter(event.currentTarget); },
        refresh() { return this._refresh(); },
        init() {
        const table = document.getElementById('inventory-products-table');
        if (!table) return;

        const companyQuery = this.$el.dataset.companyQuery;
        const siteBase = this.$el.dataset.siteBase;
        const canConfigure = this.$el.dataset.canConfigure === '1';
        const companyId = this.$el.dataset.companyId;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatInt(val) {
            return Number(val || 0).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function buildInventoryProductRowHtml(item) {
            const isActive = Number(item.active ?? 1) === 1;
            const isCritical = Boolean(item.is_critical);
            const isOverstock = Boolean(item.is_overstock);
            const statusLabel = isCritical ? 'Critico' : (isOverstock ? 'Sobre stock' : 'Saludable');
            const statusClass = isCritical ? 'text-danger fw-semibold' : (isOverstock ? 'text-warning fw-semibold' : 'text-success fw-semibold');

            let statusHtml = '';
            if (!isActive) {
                statusHtml = '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactivo</span>';
            } else {
                statusHtml = `<span class="${statusClass}">${statusLabel}</span>`;
            }

            let manageBtn = '';
            if (canConfigure) {
                const editQuery = new URLSearchParams({company_id: companyId, redirect_to: siteBase + '/inventario?company_id=' + encodeURIComponent(companyId)});
                manageBtn = `<a href="${siteBase}/inventario/productos/${encodeURIComponent(item.id)}/editar?${editQuery}" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Producto" data-popup-subtitle="Editar datos, precios y composición del producto." title="Editar producto" aria-label="Editar producto"><i class="bi bi-pencil-square"></i></a>`;
            }

            const catBrand = ((item.category || '') + ' ' + (item.brand || '')).trim();

            return `
                <td>${escapeHtml(item.sku || '')}</td>
                <td>${escapeHtml(item.name || '')}
                    <div class="small text-secondary">${escapeHtml(catBrand)}</div>
                </td>
                <td>${escapeHtml(item.unit || 'unidad')}</td>
                <td>${formatInt(item.total_stock)}</td>
                <td>${formatInt(item.reserved_stock)}</td>
                <td>${formatInt(item.available_stock)}</td>
                <td>${formatInt(item.min_stock)} / ${formatInt(item.max_stock)}</td>
                <td>${statusHtml}</td>
                <td class="text-end">
                    ${manageBtn}
                    <a href="${siteBase}/inventario/productos/${item.id}/trazabilidad${companyQuery ? '?' + companyQuery.substring(1) : ''}" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Ver trazabilidad" aria-label="Ver trazabilidad"><i class="bi bi-diagram-3"></i></a>
                </td>
            `;
        }

        const searchInput = document.getElementById('productSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const filterPills = document.querySelectorAll('.filter-pill');
        const tableBody = table.querySelector('tbody');
        let allRows = Array.from(tableBody.querySelectorAll('.product-row'));
        const pageSize = 5;
        let currentPage = 1;
        let currentFilter = 'all';
        let searchQuery = '';

        // Create pagination container for products
        const tableResponsive = table.closest('.table-responsive');
        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'codex-pagination mt-4';
        tableResponsive.after(paginationWrapper);

        function refreshInventoryProducts(targetRow = null) {
            allRows = Array.from(tableBody.querySelectorAll('.product-row'));
            
            if (targetRow) {
                const query = searchInput.value.toLowerCase().trim();
                const matched = allRows.filter(row => {
                    const sku = (row.dataset.sku || '').toLowerCase();
                    const name = (row.dataset.name || '').toLowerCase();
                    const catBrand = (row.dataset.categoryBrand || '').toLowerCase();
                    const status = row.dataset.status || '';

                    const matchesSearch = !query ||
                        sku.includes(query) ||
                        name.includes(query) ||
                        catBrand.includes(query);

                    const matchesStatus = currentFilter === 'all' || status === currentFilter;
                    return matchesSearch && matchesStatus;
                });

                const idx = matched.indexOf(targetRow);
                if (idx !== -1) {
                    currentPage = Math.floor(idx / pageSize) + 1;
                }
            }

            updateFilters();
        }

        function updateFilters() {
            // Refresh replaces tbody; resolve its current empty-state nodes each time.
            const noResultsRow = tableBody.querySelector('#no-results-row');
            const noProductsRow = tableBody.querySelector('#no-products-row');
            searchQuery = searchInput.value.toLowerCase().trim();
            if (searchQuery) {
                clearSearchBtn.style.display = 'block';
            } else {
                clearSearchBtn.style.display = 'none';
            }

            let matchedCount = 0;
            const matchedRows = [];

            allRows.forEach(row => {
                const sku = (row.dataset.sku || '').toLowerCase();
                const name = (row.dataset.name || '').toLowerCase();
                const catBrand = (row.dataset.categoryBrand || '').toLowerCase();
                const status = row.dataset.status || '';

                // Check if matches search
                const matchesSearch = !searchQuery ||
                    sku.includes(searchQuery) ||
                    name.includes(searchQuery) ||
                    catBrand.includes(searchQuery);

                // Check if matches pill filter
                const matchesStatus = currentFilter === 'all' || status === currentFilter;

                if (matchesSearch && matchesStatus) {
                    matchedRows.push(row);
                    matchedCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (matchedCount === 0) {
                if (noResultsRow) {
                    noResultsRow.style.display = '';
                }
                if (noProductsRow) {
                    noProductsRow.style.display = 'none';
                }
                paginationWrapper.innerHTML = '';
            } else {
                if (noResultsRow) {
                    noResultsRow.style.display = 'none';
                }
                // Paginate matched rows
                const pageCount = Math.ceil(matchedRows.length / pageSize);
                if (currentPage > pageCount) {
                    currentPage = Math.max(1, pageCount);
                }

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = startIndex + pageSize;

                matchedRows.forEach((row, index) => {
                    if (index >= startIndex && index < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                renderPagination(matchedRows.length, pageCount);
            }
        }

        function renderPagination(totalCount, pageCount) {
            paginationWrapper.innerHTML = '';
            if (pageCount <= 1) {
                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando ${totalCount} registro${totalCount === 1 ? '' : 's'}`;
                paginationWrapper.appendChild(summary);
                return;
            }

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalCount);
            summary.textContent = `Mostrando ${startIndex + 1}-${endIndex} de ${totalCount} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First button (<<)
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
                        updateFilters();
                    }
                });
                controls.appendChild(first);
            }

            // Prev button (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    updateFilters();
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
                    updateFilters();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next button (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', () => {
                if (currentPage < pageCount) {
                    currentPage++;
                    updateFilters();
                }
            });
            controls.appendChild(next);

            // Last button (>>)
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
                        updateFilters();
                    }
                });
                controls.appendChild(last);
            }

            paginationWrapper.appendChild(summary);
            paginationWrapper.appendChild(controls);
        }

        // Input event for search
        this._searchChanged = () => {
            currentPage = 1;
            updateFilters();
        };

        this._clearSearch = () => {
            searchInput.value = '';
            currentPage = 1;
            updateFilters();
        };

        // Pill click handlers
        this._selectFilter = (pill) => {
                filterPills.forEach(p => {
                    p.classList.remove('active');
                    p.setAttribute('aria-pressed', 'false');
                    const filterType = p.dataset.filter;
                    if (filterType === 'all') {
                        p.classList.remove('btn-dark');
                        p.classList.add('btn-outline-secondary');
                    } else if (filterType === 'critical') {
                        p.classList.remove('btn-danger');
                        p.classList.add('btn-outline-danger');
                    } else if (filterType === 'overstock') {
                        p.classList.remove('btn-warning', 'text-dark');
                        p.classList.add('btn-outline-warning');
                    } else if (filterType === 'healthy') {
                        p.classList.remove('btn-success');
                        p.classList.add('btn-outline-success');
                    }
                });

                // Set active classes
                pill.classList.add('active');
                pill.setAttribute('aria-pressed', 'true');
                const filterType = pill.dataset.filter;
                if (filterType === 'all') {
                    pill.classList.remove('btn-outline-secondary');
                    pill.classList.add('btn-dark');
                } else if (filterType === 'critical') {
                    pill.classList.remove('btn-outline-danger');
                    pill.classList.add('btn-danger');
                } else if (filterType === 'overstock') {
                    pill.classList.remove('btn-outline-warning');
                    pill.classList.add('btn-warning', 'text-dark');
                } else if (filterType === 'healthy') {
                    pill.classList.remove('btn-outline-success');
                    pill.classList.add('btn-success');
                }

                currentFilter = filterType;
                currentPage = 1;
                updateFilters();
        };

        // Connect smart alerts
        function connectAlertTriggers() {
            document.querySelectorAll('.alert-search-trigger').forEach(trigger => {
                trigger.onclick = () => {
                    const searchTerm = trigger.dataset.searchTerm || '';
                    searchInput.value = searchTerm;

                    // Highlight search input briefly
                    searchInput.focus();
                    searchInput.classList.add('is-valid');
                    setTimeout(() => {
                        searchInput.classList.remove('is-valid');
                    }, 1000);

                    // Reset status filter to "all"
                    const allPill = document.querySelector('.filter-pill[data-filter="all"]');
                    if (allPill) {
                        allPill.click();
                    } else {
                        currentFilter = 'all';
                        currentPage = 1;
                        updateFilters();
                    }
                };
            });
        }
        connectAlertTriggers();

        // Initial render for products table
        updateFilters();

        // Generic Client-side Card List Pagination (5 items per page)
        function setupListPagination(containerId, itemSelector, pageSize = 5) {
            const container = document.getElementById(containerId);
            if (!container) return null;

            let paginationWrapper = container.parentNode.querySelector(`.codex-pagination[data-for="${containerId}"]`);
            if (!paginationWrapper) {
                paginationWrapper = document.createElement('div');
                paginationWrapper.className = 'codex-pagination mt-3';
                paginationWrapper.dataset.for = containerId;
                container.after(paginationWrapper);
            }

            let currentPage = 1;

            function render() {
                const items = Array.from(container.querySelectorAll(itemSelector));
                const totalItems = items.length;

                if (totalItems <= pageSize) {
                    items.forEach(item => { item.style.display = ''; });
                    paginationWrapper.innerHTML = '';
                    return;
                }

                const pageCount = Math.ceil(totalItems / pageSize);
                if (currentPage > pageCount) currentPage = pageCount;
                if (currentPage < 1) currentPage = 1;

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = startIndex + pageSize;

                items.forEach((item, index) => {
                    if (index >= startIndex && index < endIndex) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
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

        // Initialize 5-items pagination on all card lists
        const reservationsPaginator = setupListPagination('inventory-reservations-list', '.reservation-item', 5);
        const warehousesPaginator = setupListPagination('inventory-warehouses-list', '.warehouse-item', 5);
        const movementsPaginator = setupListPagination('recent-movements-list', '.recent-movement-item', 5);

        // Bind liberation form handlers for instant AJAX execution without full reload
        function bindReservationReleaseForms() {
            document.querySelectorAll('.release-reservation-form').forEach(form => {
                form.onsubmit = async (e) => {
                    e.preventDefault();
                    const submitBtn = form.querySelector('button[type="submit"], button');
                    if (submitBtn) submitBtn.disabled = true;
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (window.showCodexToast) {
                            window.showCodexToast('Reserva liberada correctamente.', 'success');
                        }
                        await reloadInventoryData();
                    } catch (err) {
                        console.error('Error al liberar reserva:', err);
                        form.submit();
                    }
                };
            });
        }
        bindReservationReleaseForms();

        // Seamless AJAX refresh of the entire Inventory Dashboard
        this._refresh = reloadInventoryData;
        this.refreshTimer = setInterval(() => {
            if (!document.hidden && !document.querySelector('.modal.show')) reloadInventoryData();
        }, 60000);
        let isInventoryReloading = false;
        async function reloadInventoryData() {
            if (isInventoryReloading) return;
            isInventoryReloading = true;
            const refreshButton = document.getElementById('inventory-refresh');
            const refreshStatus = document.getElementById('inventory-refresh-status');
            if (refreshButton) refreshButton.disabled = true;
            if (refreshStatus) refreshStatus.textContent = 'Actualizando...';

            try {
                const response = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                if (!response.ok) throw new Error('Error al recargar dashboard');

                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                if (!doc.getElementById('inventory-summary-card')) throw new Error('Respuesta sin datos de inventario');
                // 1. Update KPI Summary Cards
                const newSummary = doc.getElementById('inventory-summary-card');
                const curSummary = document.getElementById('inventory-summary-card');
                if (newSummary && curSummary) {
                    curSummary.innerHTML = newSummary.innerHTML;
                }

                // 2. Update Smart Alerts Card
                const newAlerts = doc.getElementById('inventory-alerts-card');
                const curAlerts = document.getElementById('inventory-alerts-card');
                if (newAlerts && curAlerts) {
                    curAlerts.innerHTML = newAlerts.innerHTML;
                    connectAlertTriggers();
                }

                // 3. Update Products Table Body
                const newProductsTable = doc.getElementById('inventory-products-table');
                if (newProductsTable) {
                    const newTbody = newProductsTable.querySelector('tbody');
                    if (newTbody) {
                        tableBody.innerHTML = newTbody.innerHTML;
                        allRows = Array.from(tableBody.querySelectorAll('.product-row'));
                        updateFilters();
                    }
                }

                // 4. Update Reservations List
                const newReservations = doc.getElementById('inventory-reservations-list');
                const curReservations = document.getElementById('inventory-reservations-list');
                if (newReservations && curReservations) {
                    curReservations.innerHTML = newReservations.innerHTML;
                    reservationsPaginator?.refresh();
                    bindReservationReleaseForms();
                }

                // 5. Update Warehouses List
                const newWarehouses = doc.getElementById('inventory-warehouses-list');
                const curWarehouses = document.getElementById('inventory-warehouses-list');
                if (newWarehouses && curWarehouses) {
                    curWarehouses.innerHTML = newWarehouses.innerHTML;
                    warehousesPaginator?.refresh();
                }

                // 6. Update Recent Movements List
                const newMovements = doc.getElementById('recent-movements-list');
                const curMovements = document.getElementById('recent-movements-list');
                if (newMovements && curMovements) {
                    curMovements.innerHTML = newMovements.innerHTML;
                    movementsPaginator?.refresh();
                }

                // 7. Re-bind popups in updated elements
                if (typeof window.initCodexPopups === 'function') {
                    window.initCodexPopups();
                }
            } catch (err) {
                console.error('Error al actualizar datos de inventario:', err);
                if (refreshStatus) refreshStatus.textContent = 'No se pudo actualizar. Se conservan los últimos datos.';
            } finally {
                isInventoryReloading = false;
                if (refreshButton) refreshButton.disabled = false;
                if (refreshStatus?.textContent === 'Actualizando...') refreshStatus.textContent = 'Datos actualizados';
            }
        }
        window.reloadInventoryData = reloadInventoryData;

        // Events are shared with the popup host. Keep listeners scoped to this instance.
        this._onSaved = () => reloadInventoryData();
        this._saveEvents = ['codex:product-saved','codex:movement-saved','codex:reservation-saved','codex:warehouse-saved','codex:item-saved'];
        this._saveEvents.forEach(name => window.addEventListener(name, this._onSaved));
        this._onMessage = event => {
            if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) reloadInventoryData();
        };
        window.addEventListener('message', this._onMessage);
        },
        destroy() {
            clearInterval(this.refreshTimer);
            this._saveEvents?.forEach(name => window.removeEventListener(name, this._onSaved));
            window.removeEventListener('message', this._onMessage);
            if (window.reloadInventoryData === this._refresh) delete window.reloadInventoryData;
        }
    }));
});
