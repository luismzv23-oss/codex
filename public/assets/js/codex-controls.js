/* Shared controls own interaction only; native forms, links and authorization stay intact. */
(() => {
    const instances = new WeakMap();
    const targetOf = trigger => {
        const id = trigger?.getAttribute('data-codex-target');
        return id?.startsWith('#') ? document.getElementById(id.slice(1)) : null;
    };
    window.CodexDialogs = {
        getOrCreateInstance(element) {
            if (!element) return null;
            if (instances.has(element)) return instances.get(element);
            let dialog, opener, overflow;
            const instance = {
                show() {
                    if (dialog?.open) return;
                    if (!dialog) {
                        dialog = document.createElement('dialog');
                        dialog.className = 'codex-dialog';
                        const label = element.getAttribute('aria-labelledby');
                        if (label) dialog.setAttribute('aria-labelledby', label);
                        else dialog.setAttribute('aria-label', element.querySelector('.modal-title')?.textContent.trim() || 'Formulario');
                        Alpine.mutateDom(() => { element.before(dialog); dialog.append(element); });
                        dialog.addEventListener('cancel', event => { event.preventDefault(); instance.hide(); });
                        dialog.addEventListener('click', event => {
                            if (event.target === element || event.target === dialog) instance.hide();
                        });
                        dialog.addEventListener('close', () => {
                            element.classList.remove('show');
                            element.setAttribute('aria-hidden', 'true');
                            document.body.style.overflow = overflow;
                            if (opener?.isConnected) opener.focus();
                            element.dispatchEvent(new CustomEvent('codex:dialog-hidden', {bubbles: true}));
                        });
                    }
                    opener = document.activeElement;
                    overflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                    element.classList.add('show');
                    element.removeAttribute('aria-hidden');
                    dialog.showModal();
                    element.dispatchEvent(new CustomEvent('codex:dialog-shown', {bubbles: true}));
                },
                hide() { if (dialog?.open) dialog.close(); },
            };
            instances.set(element, instance);
            return instance;
        },
    };
    document.addEventListener('alpine:init', () => {
        Alpine.data('codexSearchSelect', () => ({
            init() {
                const select = this.$el.querySelector('select');
                if (!select) return;
                const search = document.createElement('input');
                search.type = 'search';
                search.className = 'form-control';
                search.placeholder = select.getAttribute('placeholder') || 'Buscar producto…';
                search.setAttribute('aria-label', select.getAttribute('aria-label') || 'Buscar producto por código o nombre');
                if (!select.hasAttribute('aria-label') && !select.labels.length) select.setAttribute('aria-label', 'Producto');
                search.autocomplete = 'off';
                this.$el.prepend(search);
                const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
                const originalHidden = new WeakMap();
                this.filter = () => {
                    search.disabled = select.disabled;
                    const query = normalize(search.value);
                    for (const option of select.options) {
                        if (!originalHidden.has(option)) originalHidden.set(option, option.hidden);
                        option.hidden = originalHidden.get(option) || (!!query && !normalize(option.text).includes(query));
                    }
                };
                this.reset = () => { setTimeout(() => { search.value = ''; this.filter(); }, 0); };
                this.key = event => {
                    if (event.key === 'ArrowDown') { event.preventDefault(); select.focus(); }
                    if (event.key === 'Enter') event.preventDefault(); // Searching never submits the purchase/product form.
                    if (event.key === 'Escape') { event.preventDefault(); search.value = ''; this.filter(); }
                };
                this.observer = new MutationObserver(this.filter);
                this.observer.observe(select, {attributes: true, attributeFilter: ['disabled'], childList: true, subtree: true});
                search.addEventListener('input', this.filter);
                search.addEventListener('keydown', this.key);
                select.form?.addEventListener('reset', this.reset);
                this.select = select;
                this.search = search;
                this.filter();
            },
            destroy() {
                this.observer?.disconnect();
                this.select?.form?.removeEventListener('reset', this.reset);
                this.search?.removeEventListener('input', this.filter);
                this.search?.removeEventListener('keydown', this.key);
                this.search?.remove();
            },
        }));
        Alpine.data('codexNavigation', () => ({
            mobileOpen: false,
            get mobileClass() { return this.mobileOpen ? 'show' : ''; },
            toggleMobile() { this.mobileOpen = !this.mobileOpen; if (!this.mobileOpen) this.closeMenus(); },
            closeMenus(except) {
                this.$el.querySelectorAll('[data-codex-dropdown]').forEach(trigger => {
                    if (trigger === except) return;
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.nextElementSibling?.classList.remove('show');
                });
            },
            init() {
                const root = this.$el;
                const open = trigger => {
                    this.closeMenus(trigger);
                    trigger.setAttribute('aria-expanded', 'true');
                    trigger.nextElementSibling?.classList.add('show');
                };
                this.onClick = event => {
                    const trigger = event.target.closest('[data-codex-dropdown]');
                    if (trigger && root.contains(trigger)) {
                        event.preventDefault();
                        if (trigger.getAttribute('aria-expanded') === 'true') this.closeMenus(); else open(trigger);
                    } else this.closeMenus();
                };
                this.onKey = event => {
                    if (!root.contains(event.target)) return;
                    const trigger = event.target.closest('[data-codex-dropdown]');
                    const active = root.querySelector('[data-codex-dropdown][aria-expanded="true"]');
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        if (active) { this.closeMenus(); active.focus(); }
                        else { this.mobileOpen = false; root.querySelector('.navbar-toggler')?.focus(); }
                    } else if (trigger && (event.key === ' ' || event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                        event.preventDefault(); open(trigger);
                        const links = trigger.nextElementSibling.querySelectorAll('a[href],button:not(:disabled)');
                        (event.key === 'ArrowUp' ? links[links.length - 1] : links[0])?.focus();
                    } else if (active && ['ArrowDown','ArrowUp','Home','End'].includes(event.key)) {
                        event.preventDefault();
                        const links = [...active.nextElementSibling.querySelectorAll('a[href],button:not(:disabled)')];
                        let index = links.indexOf(document.activeElement);
                        index = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
                        links[index]?.focus();
                    }
                };
                this.onFocus = event => { if (!root.contains(event.target)) this.closeMenus(); };
                document.addEventListener('click', this.onClick);
                document.addEventListener('keydown', this.onKey);
                document.addEventListener('focusin', this.onFocus);
            },
            destroy() {
                document.removeEventListener('click', this.onClick);
                document.removeEventListener('keydown', this.onKey);
                document.removeEventListener('focusin', this.onFocus);
            },
        }));
        Alpine.data('codexControls', () => ({
            init() {
                const tabSelector = '[data-codex-toggle="tab"],[data-codex-toggle="pill"]';
                const activate = tab => {
                    const list = tab.closest('[role="tablist"],.nav');
                    if (!list || !targetOf(tab)) return;
                    list.querySelectorAll(tabSelector).forEach(item => {
                        const selected = item === tab;
                        item.classList.toggle('active', selected);
                        item.setAttribute('aria-selected', String(selected));
                        item.tabIndex = selected ? 0 : -1;
                        targetOf(item)?.classList.toggle('active', selected);
                        targetOf(item)?.classList.toggle('show', selected);
                    });
                    tab.dispatchEvent(new CustomEvent('codex:tab-shown', {bubbles: true}));
                };
                this.$el.querySelectorAll(tabSelector).forEach(tab => { tab.tabIndex = tab.classList.contains('active') ? 0 : -1; });
                this.click = event => {
                    const tab = event.target.closest(tabSelector);
                    if (tab && !tab.disabled) { event.preventDefault(); activate(tab); return; }
                    const opener = event.target.closest('[data-codex-toggle="modal"]');
                    if (opener) { event.preventDefault(); window.CodexDialogs.getOrCreateInstance(targetOf(opener))?.show(); }
                    const close = event.target.closest('[data-codex-dismiss="modal"]');
                    if (close) { event.preventDefault(); window.CodexDialogs.getOrCreateInstance(close.closest('.modal'))?.hide(); }
                };
                this.key = event => {
                    const tab = event.target.closest(tabSelector);
                    if (!tab || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
                    const list = tab.closest('[role="tablist"],.nav');
                    if (!list) return;
                    const tabs = [...list.querySelectorAll(tabSelector)].filter(item => !item.disabled);
                    const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
                    event.preventDefault(); tabs[index].focus(); activate(tabs[index]);
                };
                this.$el.addEventListener('click', this.click);
                this.$el.addEventListener('keydown', this.key);
            },
            destroy() {
                this.$el.removeEventListener('click', this.click);
                this.$el.removeEventListener('keydown', this.key);
            },
        }));
    });
})();
