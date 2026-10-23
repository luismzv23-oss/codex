document.addEventListener('alpine:init', () => {
 Alpine.data('cashTables', () => ({
 init() {
    class PaginatedTable {
        constructor(tableId, pageSize = 5, searchInputId = 'cashSearchInput', filterFn = null) {
            this.table = document.getElementById(tableId);
            if (!this.table) return;
            this.pageSize = pageSize;
            this.currentPage = 1;
            this.searchInputId = searchInputId;
            this.filterFn = filterFn;
            this.tbody = this.table.tBodies[0];
            if (!this.tbody) return;
            this.allRows = Array.from(this.tbody.querySelectorAll('tr.data-row'));
            this.noResultsRow = this.tbody.querySelector('tr.no-results-row');
            this.noDataRow = this.tbody.querySelector('tr.no-data-row');

            // Create pagination wrapper
            const tableResponsive = this.table.closest('.table-responsive');
            this.paginationWrapper = document.createElement('div');
            this.paginationWrapper.className = 'codex-pagination';
            tableResponsive.after(this.paginationWrapper);

            // Listen for input search if specified
            if (searchInputId) {
                const searchInput = document.getElementById(searchInputId);
                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        this.currentPage = 1;
                        this.update();
                    });
                }
            }

            this.update();
        }

        refreshRows() {
            if (!this.table) return;
            this.tbody = this.table.querySelector('tbody');
            if (!this.tbody) return;
            this.allRows = Array.from(this.tbody.querySelectorAll('tr.data-row'));
            this.noResultsRow = this.tbody.querySelector('tr.no-results-row');
            this.noDataRow = this.tbody.querySelector('tr.no-data-row');
            this.currentPage = 1;
            this.update();
        }

        update() {
            const query = this.searchInputId ? (document.getElementById(this.searchInputId)?.value.toLowerCase().trim() || '') : '';
            
            if (this.allRows.length === 0) {
                if (this.noDataRow) this.noDataRow.style.display = '';
                if (this.noResultsRow) this.noResultsRow.style.display = 'none';
                this.paginationWrapper.innerHTML = '';
                return;
            }

            let matchedRows = [];

            this.allRows.forEach(row => {
                let matches = true;
                if (query) {
                    const text = row.textContent.toLowerCase();
                    if (!text.includes(query)) matches = false;
                }

                if (matches && typeof this.filterFn === 'function') {
                    matches = Boolean(this.filterFn(row));
                }

                if (matches) {
                    row.style.display = '';
                    matchedRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            const totalCount = matchedRows.length;
            if (totalCount === 0) {
                if (this.noResultsRow) this.noResultsRow.style.display = '';
                if (this.noDataRow) this.noDataRow.style.display = 'none';
                this.paginationWrapper.innerHTML = '';
            } else {
                if (this.noResultsRow) this.noResultsRow.style.display = 'none';
                if (this.noDataRow) this.noDataRow.style.display = 'none';

                const pageCount = Math.ceil(totalCount / this.pageSize);
                if (this.currentPage > pageCount) {
                    this.currentPage = Math.max(1, pageCount);
                }

                const startIndex = (this.currentPage - 1) * this.pageSize;
                const endIndex = startIndex + this.pageSize;

                matchedRows.forEach((row, index) => {
                    if (index >= startIndex && index < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                this.renderPagination(totalCount, pageCount);
            }

            if (this.table.id === 'movements-table') {
                const badge = document.getElementById('movement-count-badge');
                if (badge) {
                    badge.textContent = `${totalCount} movimiento${totalCount === 1 ? '' : 's'}`;
                }
            }
        }

        renderPagination(totalCount, pageCount) {
            this.paginationWrapper.innerHTML = '';
            if (pageCount <= 1) {
                const summary = document.createElement('div');
                summary.className = 'codex-pagination__summary';
                summary.textContent = `Mostrando ${totalCount} registro${totalCount === 1 ? '' : 's'}`;
                this.paginationWrapper.appendChild(summary);
                return;
            }

            const summary = document.createElement('div');
            summary.className = 'codex-pagination__summary';
            const startIndex = (this.currentPage - 1) * this.pageSize;
            const endIndex = Math.min(startIndex + this.pageSize, totalCount);
            summary.textContent = `Mostrando ${startIndex + 1}-${endIndex} de ${totalCount} registros`;

            const controls = document.createElement('div');
            controls.className = 'codex-pagination__controls';

            // First page button (<<)
            if (pageCount > 4) {
                const firstBtn = document.createElement('button');
                firstBtn.type = 'button';
                firstBtn.className = 'codex-pagination__btn';
                firstBtn.innerHTML = '<i class="bi bi-chevron-double-left"></i>';
                firstBtn.disabled = this.currentPage === 1;
                firstBtn.title = 'Primera página';
                firstBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.currentPage > 1) {
                        this.currentPage = 1;
                        this.update();
                    }
                });
                controls.appendChild(firstBtn);
            }

            // Previous button (<)
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'codex-pagination__btn';
            prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prev.disabled = this.currentPage === 1;
            prev.title = 'Página anterior';
            prev.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.update();
                }
            });
            controls.appendChild(prev);

            const pages = document.createElement('div');
            pages.className = 'codex-pagination__pages';

            const startPage = Math.max(1, this.currentPage - 2);
            const endPage = Math.min(pageCount, this.currentPage + 2);

            for (let p = startPage; p <= endPage; p++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `codex-pagination__btn${p === this.currentPage ? ' is-active' : ''}`;
                btn.textContent = String(p);
                btn.title = `Página ${p}`;
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.currentPage = p;
                    this.update();
                });
                pages.appendChild(btn);
            }
            controls.appendChild(pages);

            // Next button (>)
            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'codex-pagination__btn';
            next.innerHTML = '<i class="bi bi-chevron-right"></i>';
            next.disabled = this.currentPage === pageCount;
            next.title = 'Página siguiente';
            next.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.currentPage < pageCount) {
                    this.currentPage++;
                    this.update();
                }
            });
            controls.appendChild(next);

            // Last page button (>>)
            if (pageCount > 4) {
                const lastBtn = document.createElement('button');
                lastBtn.type = 'button';
                lastBtn.className = 'codex-pagination__btn';
                lastBtn.innerHTML = '<i class="bi bi-chevron-double-right"></i>';
                lastBtn.disabled = this.currentPage === pageCount;
                lastBtn.title = 'Última página';
                lastBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.currentPage < pageCount) {
                        this.currentPage = pageCount;
                        this.update();
                    }
                });
                controls.appendChild(lastBtn);
            }

            this.paginationWrapper.appendChild(summary);
            this.paginationWrapper.appendChild(controls);
        }
    }

    // Movements Table Filter Function & Initialization
    const movementFilterFn = (row) => {
        const typeFilter = document.getElementById('movementTypeFilter')?.value.toLowerCase() || '';
        const registerFilter = document.getElementById('movementRegisterFilter')?.value.toLowerCase() || '';
        
        if (typeFilter && !row.getAttribute('data-type')?.includes(typeFilter)) {
            return false;
        }
        if (registerFilter && !row.getAttribute('data-register')?.includes(registerFilter)) {
            return false;
        }
        return true;
    };

    // Table instances registry
    const allPaginatedTables = [
        new PaginatedTable('registers-table', 5, 'cashSearchInput'),
        new PaginatedTable('sessions-table', 5, 'cashSearchInput'),
        new PaginatedTable('checks-table', 5, 'cashSearchInput'),
        new PaginatedTable('reconciliations-table', 5, 'cashSearchInput'),
        new PaginatedTable('movements-table', 5, 'movementSearchInput', movementFilterFn)
    ];
    window.codexCashTables = allPaginatedTables;

    document.getElementById('movementTypeFilter')?.addEventListener('change', () => {
        const movTable = allPaginatedTables.find(t => t.table?.id === 'movements-table');
        if (movTable) {
            movTable.currentPage = 1;
            movTable.update();
        }
    });

    document.getElementById('movementRegisterFilter')?.addEventListener('change', () => {
        const movTable = allPaginatedTables.find(t => t.table?.id === 'movements-table');
        if (movTable) {
            movTable.currentPage = 1;
            movTable.update();
        }
    });

    // Clear search handler for Upper Tables
    const clearBtn = document.getElementById('clearCashSearchBtn');
    const searchInput = document.getElementById('cashSearchInput');
    if (clearBtn && searchInput) {
        searchInput.addEventListener('input', () => {
            clearBtn.style.display = searchInput.value ? 'block' : 'none';
        });
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            searchInput.value = '';
            clearBtn.style.display = 'none';
            searchInput.dispatchEvent(new Event('input'));
        });
    }

    // Clear search handler for Movements Table
    const clearMovBtn = document.getElementById('clearMovementSearchBtn');
    const movSearchInput = document.getElementById('movementSearchInput');
    if (clearMovBtn && movSearchInput) {
        movSearchInput.addEventListener('input', () => {
            clearMovBtn.style.display = movSearchInput.value ? 'block' : 'none';
        });
        clearMovBtn.addEventListener('click', (e) => {
            e.preventDefault();
            movSearchInput.value = '';
            clearMovBtn.style.display = 'none';
            movSearchInput.dispatchEvent(new Event('input'));
        });
    }

    // Seamless AJAX refresh without page reload
    let isCashReloading = false;
    async function reloadCashData() {
        if (isCashReloading) return;
        isCashReloading = true;
        const refreshBtn = document.getElementById('cash-dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.classList.add('is-loading');
            refreshBtn.disabled = true;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const refreshUrl = new URL(window.location.href);
            refreshUrl.searchParams.delete('cash_dashboard');
            const response = await fetch(refreshUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: controller.signal
            });
            if (!response.ok) throw new Error('Network error');
            
            const text = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            if (!doc.getElementById('registers-table')) throw new Error('Invalid cash response');

            // 1. Update Cash Dashboard Panel (KPIs + Breakdown)
            const newPanel = doc.getElementById('cash-dashboard-panel');
            const currentPanel = document.getElementById('cash-dashboard-panel');
            if (newPanel && currentPanel) {
                currentPanel.innerHTML = newPanel.innerHTML;
            }

            // 2. Update Table Bodies
            const tableIds = ['registers-table', 'sessions-table', 'checks-table', 'reconciliations-table', 'movements-table'];
            tableIds.forEach(id => {
                const newTable = doc.getElementById(id);
                const currentTable = document.getElementById(id);
                if (newTable && currentTable) {
                    const newTbody = newTable.querySelector('tbody');
                    const currentTbody = currentTable.querySelector('tbody');
                    if (newTbody && currentTbody) {
                        currentTbody.innerHTML = newTbody.innerHTML;
                    }
                }
            });

            // 3. Update Counts and Badges
            const newMovCount = doc.getElementById('movement-count-badge');
            const currentMovCount = document.getElementById('movement-count-badge');
            if (newMovCount && currentMovCount) {
                currentMovCount.textContent = newMovCount.textContent;
            }

            // Update panel tags (e.g. "X sesiones", "X registros")
            const docPanels = doc.querySelectorAll('.insight-panel');
            const currentPanels = document.querySelectorAll('.insight-panel');
            docPanels.forEach((dPanel, idx) => {
                const cPanel = currentPanels[idx];
                if (cPanel) {
                    const dTag = dPanel.querySelector('.insight-tag');
                    const cTag = cPanel.querySelector('.insight-tag');
                    if (dTag && cTag) {
                        cTag.textContent = dTag.textContent;
                    }
                }
            });

            // Update Section 02 summary label if present
            const docSectionLabels = doc.querySelectorAll('.insight-section-label');
            const currentSectionLabels = document.querySelectorAll('.insight-section-label');
            if (docSectionLabels.length > 1 && currentSectionLabels.length > 1) {
                currentSectionLabels[1].innerHTML = docSectionLabels[1].innerHTML;
            }

            // Update Hero Action Buttons if active session state changed
            const docHeroActions = doc.querySelector('.insight-hero .d-flex');
            const currentHeroActions = document.querySelector('.insight-hero .d-flex');
            if (docHeroActions && currentHeroActions) {
                currentHeroActions.innerHTML = docHeroActions.innerHTML;
            }

            // 4. Refresh all Paginated Tables to display new data on page 1
            allPaginatedTables.forEach(t => t.refreshRows());

            // 5. Re-bind popups in updated elements if needed
            if (typeof window.initCodexPopups === 'function') {
                window.initCodexPopups();
            }
        } catch (err) {
            console.error('Error al actualizar datos de caja:', err);
        } finally {
            clearTimeout(timeout);
            isCashReloading = false;
            if (refreshBtn) {
                refreshBtn.classList.remove('is-loading');
                refreshBtn.disabled = false;
            }
        }
    }
    window.reloadCashData = reloadCashData;

    // Listeners for seamless updates when modals/popups close
    window.addEventListener('codex:item-saved', () => {
        reloadCashData();
    });
    window.addEventListener('codex:cash-saved', () => {
        reloadCashData();
    });
    window.addEventListener('message', (event) => {
        if (event.origin === window.location.origin && event.data && (event.data.type === 'codex-popup-saved' || event.data.type === 'codex-popup-close')) {
            reloadCashData();
        }
    });

    // Vendor filter — AJAX, updates only the sessions card
    async function filterSessionsByVendor(vendorId) {
        const form = document.getElementById('sessions-vendor-form');
        if (!form) return;

        const url = new URL(form.action);
        url.searchParams.set('company_id', form.querySelector('input[name="company_id"]')?.value || '');
        url.searchParams.set('cash_register_id', form.querySelector('input[name="cash_register_id"]')?.value || '');
        if (vendorId) {
            url.searchParams.set('vendor_id', vendorId);
        } else {
            url.searchParams.delete('vendor_id');
        }
        // Preserve active date range from current URL
        const cur = new URLSearchParams(window.location.search);
        if (cur.get('from')) url.searchParams.set('from', cur.get('from'));
        if (cur.get('to'))   url.searchParams.set('to',   cur.get('to'));

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Network error');

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

            // Swap sessions tbody
            const newTbody = doc.getElementById('sessions-table')?.querySelector('tbody');
            const curTbody = document.getElementById('sessions-table')?.querySelector('tbody');
            if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;

            // Update count tag
            const newPanel  = doc.getElementById('sessions-table')?.closest('.insight-panel');
            const curPanel  = document.getElementById('sessions-table')?.closest('.insight-panel');
            if (newPanel && curPanel) {
                const nTag = newPanel.querySelector('.insight-panel-heading .insight-tag');
                const cTag = curPanel.querySelector('.insight-panel-heading .insight-tag');
                if (nTag && cTag) cTag.textContent = nTag.textContent;
            }

            // Sync the "Limpiar" link (show/hide and correct href)
            const newClear = doc.getElementById('sessions-vendor-form')?.querySelector('a.btn-outline-danger');
            const curClear = form.querySelector('a.btn-outline-danger');
            if (newClear && !curClear) {
                form.appendChild(newClear.cloneNode(true));
                // Re-bind the newly inserted clear link
                bindVendorClearLink();
            } else if (!newClear && curClear) {
                curClear.remove();
            } else if (newClear && curClear) {
                curClear.href = newClear.href;
            }

            // Refresh sessions PaginatedTable
            const sessionsTable = window.codexCashTables?.find(t => t.table?.id === 'sessions-table');
            if (sessionsTable) sessionsTable.refreshRows();

            // Update browser URL so refresh/back preserves the filter
            history.replaceState(null, '', url.toString());
        } catch (err) {
            console.error('Error al filtrar sesiones por vendedor:', err);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function bindVendorClearLink() {
        const form = document.getElementById('sessions-vendor-form');
        if (!form) return;
        const clearLink = form.querySelector('a.btn-outline-danger');
        if (!clearLink || clearLink.dataset.ajaxBound) return;
        clearLink.dataset.ajaxBound = '1';
        clearLink.addEventListener('click', (e) => {
            e.preventDefault();
            const sel = form.querySelector('select[name="vendor_id"]');
            if (sel) sel.value = '';
            filterSessionsByVendor('');
        });
    }

    const vendorForm = document.getElementById('sessions-vendor-form');
    if (vendorForm) {
        vendorForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const sel = vendorForm.querySelector('select[name="vendor_id"]');
            filterSessionsByVendor(sel?.value || '');
        });
        bindVendorClearLink();
    }

    // Manual refresh and return-to-tab refresh share cash-dashboard.js.
 }
 }));
});