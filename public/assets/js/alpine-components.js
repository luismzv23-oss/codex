/**
 * Codex ERP — Global Alpine.js Components
 */
document.addEventListener('alpine:init', () => {

    /**
     * System Switcher Dropdown & Modal State
     */
    Alpine.data('systemSwitcher', () => ({
        open: false,

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        }
    }));

    /**
     * Popup Shell / Overlay Manager
     */
    Alpine.data('popupOverlay', () => ({
        open: false,

        show() {
            this.open = true;
        },

        hide() {
            this.open = false;
        }
    }));

    /**
     * Toast Notifier Component
     */
    Alpine.data('toastNotifier', (initialMessage = '', initialType = 'info') => ({
        show: !!initialMessage,
        message: initialMessage,
        type: initialType,

        notify(msg, type = 'info', duration = 4000) {
            this.message = msg;
            this.type = type;
            this.show = true;
            setTimeout(() => {
                this.show = false;
            }, duration);
        },

        dismiss() {
            this.show = false;
        }
    }));

    /**
     * Declarative Reactive Table Pagination
     * Binds to tables with x-data="tablePagination(pageSize)"
     */
    Alpine.data('tablePagination', (initialPageSize = 10) => ({
        pageSize: initialPageSize,
        currentPage: 1,
        totalRows: 0,
        pageCount: 1,

        init() {
            this.$nextTick(() => {
                this.calculateRows();
            });
        },

        calculateRows() {
            const table = this.$el.querySelector('table') || this.$el;
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(
                row => !row.closest('tfoot') && row.children.length > 1
            );
            this.totalRows = rows.length;
            this.pageCount = Math.max(1, Math.ceil(this.totalRows / this.pageSize));
            if (this.currentPage > this.pageCount) {
                this.currentPage = 1;
            }
            this.updateDisplay(rows);
        },

        updateDisplay(rowsList) {
            const table = this.$el.querySelector('table') || this.$el;
            const rows = rowsList || Array.from(table.querySelectorAll('tbody tr')).filter(
                row => !row.closest('tfoot') && row.children.length > 1
            );
            
            const start = (this.currentPage - 1) * this.pageSize;
            const end = start + this.pageSize;

            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });
        },

        goToPage(page) {
            if (page >= 1 && page <= this.pageCount) {
                this.currentPage = page;
                this.updateDisplay();
            }
        },

        nextPage() {
            if (this.currentPage < this.pageCount) {
                this.currentPage++;
                this.updateDisplay();
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.updateDisplay();
            }
        },

        get displayRange() {
            if (this.totalRows === 0) return 'Sin registros';
            const start = (this.currentPage - 1) * this.pageSize + 1;
            const end = Math.min(this.currentPage * this.pageSize, this.totalRows);
            return `Mostrando ${start}-${end} de ${this.totalRows} registros`;
        }
    }));

});
