document.addEventListener('alpine:init', () => {
    Alpine.data('codexPopup', () => ({
        init() {
                const overlay = document.getElementById('codex-popup');
                const frame = document.getElementById('codex-popup-frame');
                const closeButton = document.getElementById('codex-popup-close');
                const title = document.getElementById('codex-popup-title');
                const subtitle = document.getElementById('codex-popup-subtitle');
                const card = overlay.querySelector('.popup-card');

                if (!overlay || !frame || !closeButton || !title || !subtitle || !card) {
                    return;
                }

                const resizePopup = () => {
                    if (frame.src === 'about:blank') {
                        return;
                    }

                    if (frame.dataset.pdf === 'true') {
                        const headHeight = overlay.querySelector('.popup-head')?.offsetHeight ?? 72;
                        frame.style.height = `${Math.max(260, Math.floor(window.innerHeight * 0.9) - headHeight)}px`;
                        return;
                    }

                    try {
                        const doc = frame.contentWindow?.document;

                        if (!doc) {
                            return;
                        }

                        const bodyHeight = doc.body ? doc.body.scrollHeight : 0;
                        const htmlHeight = doc.documentElement ? doc.documentElement.scrollHeight : 0;
                        const contentHeight = Math.max(bodyHeight, htmlHeight, 420);
                        const headHeight = overlay.querySelector('.popup-head')?.offsetHeight ?? 72;
                        const availableHeight = Math.max(420, Math.floor(window.innerHeight * 0.9) - headHeight);

                        frame.style.height = `${Math.min(contentHeight, availableHeight)}px`;
                    } catch (error) {
                        frame.style.height = '640px';
                    }
                };

                let opener = null;
                let previousOverflow = '';
                const background = [...document.body.children].filter(el => el !== overlay && ['MAIN','HEADER','NAV'].includes(el.tagName));
                const originalInert = new Map();
                const openPopup = (link) => {
                    opener = link;
                    previousOverflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                    background.forEach(el => { originalInert.set(el, el.inert); el.inert = true; });
                    const url = new URL(link.href, window.location.origin);
                    url.searchParams.set('popup', '1');
                    frame.dataset.pdf = link.dataset.popupPdf || 'false';
                    frame.src = url.toString();
                    title.textContent = link.dataset.popupTitle || link.textContent.trim() || 'Formulario';
                    subtitle.textContent = link.dataset.popupSubtitle || 'Gestion del registro actual.';
                    if (link.dataset.popupWidth) {
                        card.style.width = link.dataset.popupWidth;
                    } else {
                        card.style.width = '';
                    }
                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                    closeButton.focus();
                    if (frame.dataset.pdf === 'true') resizePopup();
                };

                const closePopup = () => {
                    overlay.classList.remove('is-open');
                    overlay.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = previousOverflow;
                    background.forEach(el => { el.inert = originalInert.get(el) || false; });
                    opener?.focus();
                    frame.src = 'about:blank';
                    frame.style.height = '420px';
                    card.style.width = '';
                };

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('a[data-popup="true"]');

                    if (!link) {
                        return;
                    }

                    event.preventDefault();
                    openPopup(link);
                });

                closeButton.addEventListener('click', closePopup);
                const handleKeys = event => {
                    if (!overlay.classList.contains('is-open')) return;
                    if (event.key === 'Escape') { event.preventDefault(); closePopup(); }
                    if (event.key === 'Tab' && event.target === closeButton && event.shiftKey) {
                        event.preventDefault(); frame.focus();
                    }
                };
                document.addEventListener('keydown', handleKeys);
                frame.addEventListener('load', () => {
                    resizePopup();
                    try { frame.contentDocument?.addEventListener('keydown', handleKeys); } catch (_) { /* Cross-origin PDF viewer. */ }
                });
                window.addEventListener('resize', resizePopup);
                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        closePopup();
                    }
                });

                window.showCodexToast = (message, type = 'success') => {
                    let container = document.getElementById('codex-toast-container');
                    if (!container) {
                        container = document.createElement('div');
                        container.id = 'codex-toast-container';
                        container.style.position = 'fixed';
                        container.style.top = '20px';
                        container.style.right = '20px';
                        container.style.zIndex = '99999';
                        container.style.display = 'flex';
                        container.style.flexDirection = 'column';
                        container.style.gap = '10px';
                        container.style.pointerEvents = 'none';
                        document.body.appendChild(container);
                    }

                    const toast = document.createElement('div');
                    toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible shadow-lg border-0 rounded-4 px-4 py-3 mb-0`;
                    toast.style.pointerEvents = 'auto';
                    toast.style.minWidth = '280px';
                    toast.style.maxWidth = '420px';
                    toast.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                    toast.style.transform = 'translateY(-10px)';
                    toast.style.opacity = '0';
                    toast.innerHTML = `
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-${type === 'error' ? 'exclamation-octagon-fill' : 'check-circle-fill'} fs-5"></i>
                            <div class="fw-semibold text-wrap" data-toast-message></div>
                        </div>
                    `;
                    toast.querySelector('[data-toast-message]').textContent = message;
                    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
                    container.appendChild(toast);

                    requestAnimationFrame(() => {
                        toast.style.transform = 'translateY(0)';
                        toast.style.opacity = '1';
                    });

                    setTimeout(() => {
                        toast.style.transform = 'translateY(-10px)';
                        toast.style.opacity = '0';
                        setTimeout(() => toast.remove(), 300);
                    }, 4000);
                };

                window.addEventListener('message', (event) => {
                    if (event.origin !== window.location.origin || !event.data) {
                        return;
                    }

                    if (event.data.type === 'codex-popup-saved') {
                        closePopup();
                        if (event.data.message) {
                            window.showCodexToast(event.data.message, 'success');
                        }
                        if (event.data.entity) {
                            window.dispatchEvent(new CustomEvent('codex:' + event.data.entity + '-saved', { detail: event.data }));
                        }
                        window.dispatchEvent(new CustomEvent('codex:item-saved', { detail: event.data }));
                        return;
                    }

                    if (event.data.type === 'codex-customer-created') {
                        closePopup();
                        const customerEvent = new CustomEvent('codex:customer-created', { detail: event.data });
                        window.dispatchEvent(customerEvent);
                        if (!document.getElementById('sale-customer-id') && !document.getElementById('kiosk-customer-id')) {
                            window.location.href = event.data.redirectUrl || window.location.href;
                        }
                        return;
                    }

                    if (event.data.type === 'codex-popup-close') {
                        closePopup();
                        if (event.data.forceReload) {
                            window.location.href = event.data.redirectUrl || window.location.href;
                        }
                        return;
                    }

                    if (event.data.type === 'codex-popup-resize') {
                        resizePopup();
                    }
                });
        }
    }));
});

document.addEventListener('alpine:init', () => {
 Alpine.data('codexTablePages', () => ({
 init() {
                const buildPagination = (table) => {
                    const pageSize = Number(table.dataset.codexPagination || 10);
                    const tbody = table.tBodies[0];

                    if (!tbody || !Number.isFinite(pageSize) || pageSize <= 0) {
                        return;
                    }

                    const rows = Array.from(tbody.querySelectorAll(':scope > tr'));
                    const validRows = rows.filter((row) => row.children.length > 1 || !row.querySelector('[colspan]'));

                    if (!validRows.length || (validRows.length <= pageSize && table.dataset.paginationAlways !== 'true')) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'codex-pagination';

                    const summary = document.createElement('div');
                    summary.className = 'codex-pagination__summary';

                    const controls = document.createElement('div');
                    controls.className = 'codex-pagination__controls';

                    const prev = document.createElement('button');
                    prev.type = 'button';
                    prev.className = 'codex-pagination__btn';
                    prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
                    prev.setAttribute('aria-label', 'Pagina anterior');

                    const pages = document.createElement('div');
                    pages.className = 'codex-pagination__pages';

                    const next = document.createElement('button');
                    next.type = 'button';
                    next.className = 'codex-pagination__btn';
                    next.innerHTML = '<i class="bi bi-chevron-right"></i>';
                    next.setAttribute('aria-label', 'Pagina siguiente');

                    controls.append(prev, pages, next);
                    wrapper.append(summary, controls);
                    table.closest('.table-responsive')?.after(wrapper);

                    const pageCount = Math.ceil(validRows.length / pageSize);
                    let currentPage = 1;

                    const renderPageButtons = () => {
                        pages.innerHTML = '';
                        const start = Math.max(1, currentPage - 2);
                        const end = Math.min(pageCount, currentPage + 2);

                        for (let page = start; page <= end; page++) {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = `codex-pagination__btn${page === currentPage ? ' is-active' : ''}`;
                            button.textContent = String(page);
                            button.addEventListener('click', () => {
                                currentPage = page;
                                render();
                            });
                            pages.appendChild(button);
                        }
                    };

                    const render = () => {
                        const startIndex = (currentPage - 1) * pageSize;
                        const endIndex = startIndex + pageSize;

                        validRows.forEach((row, index) => {
                            row.style.display = index >= startIndex && index < endIndex ? '' : 'none';
                        });

                        summary.textContent = `Mostrando ${startIndex + 1}-${Math.min(endIndex, validRows.length)} de ${validRows.length} registros`;
                        prev.disabled = currentPage === 1;
                        next.disabled = currentPage === pageCount;
                        renderPageButtons();
                    };

                    prev.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage -= 1;
                            render();
                        }
                    });

                    next.addEventListener('click', () => {
                        if (currentPage < pageCount) {
                            currentPage += 1;
                            render();
                        }
                    });

                    render();
                };

                this.$el.querySelectorAll('table[data-codex-pagination]').forEach(buildPagination);
 }
 }));
});
