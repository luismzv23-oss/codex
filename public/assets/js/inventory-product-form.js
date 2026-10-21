/* Alpine CSP: shared by Stock por producto and Productos inventariables. */
document.addEventListener('alpine:init', () => {
    Alpine.data('inventoryProductForm', () => ({
        productType: 'simple',
        init() {
            this.productType = this.$el.dataset.productType;
            this.applyType();
        },
        get isKit() { return this.productType === 'kit'; },
        get isSimple() { return !this.isKit; },
        get unit() { return this.isKit ? 'pack' : 'unidad'; },
        applyType() {
            if (this.isKit) {
                this.$refs.minimum.value = '0';
                this.$refs.maximum.value = '0';
                this.$refs.initialStock.value = '0';
                this.$refs.warehouse.value = '';
            }
            this.$refs.unit.value = this.unit;
        },
        close() {
            window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin);
        }
    }));
});
