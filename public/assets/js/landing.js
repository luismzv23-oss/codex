/* All landing state and interactions belong to this Alpine CSP component. */
document.addEventListener('alpine:init', () => {
    Alpine.data('codexLanding', () => ({
        menuOpen: false,
        selected: 'sales',
        reducedMotion: true,
        motionQuery: null,
        motionListener: null,
        modules: ['sales', 'inventory', 'purchases', 'cash', 'collections'],
        init() {
            this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.motionQuery.matches;
            this.motionListener = (event) => { this.reducedMotion = event.matches; };
            this.motionQuery.addEventListener('change', this.motionListener);
        },
        destroy() {
            this.motionQuery.removeEventListener('change', this.motionListener);
        },
        get motionClass() { return this.reducedMotion ? '' : 'motion-enabled'; },
        get menuClass() { return this.menuOpen ? 'nav-ready is-open' : 'nav-ready'; },
        toggleMenu() { this.menuOpen = !this.menuOpen; },
        closeMenu() {
            if (!this.menuOpen) return;
            this.menuOpen = false;
            this.$refs.menuToggle.focus();
        },
        followLink() { this.menuOpen = false; },
        resize() { if (window.innerWidth > 760) this.menuOpen = false; },
        select(key) { if (this.modules.includes(key)) this.selected = key; },
        moveTab(event) {
            let index = this.modules.indexOf(this.selected);
            if (event.key === 'ArrowRight') index = (index + 1) % this.modules.length;
            else if (event.key === 'ArrowLeft') index = (index + this.modules.length - 1) % this.modules.length;
            else if (event.key === 'Home') index = 0;
            else if (event.key === 'End') index = this.modules.length - 1;
            else return;
            event.preventDefault();
            this.selected = this.modules[index];
            this.$nextTick(() => this.$refs['tab_' + this.selected].focus());
        },
    }));
});
