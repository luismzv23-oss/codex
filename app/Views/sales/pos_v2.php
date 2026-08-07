<?= $this->extend('layouts/main_v2') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pos-v2.css') ?>">

<!-- Fast-Keys Shortcuts Bar -->
<div class="pos-fastkeys-bar">
    <span style="font-size:0.85rem; font-weight:600; color:var(--codex-text-muted);">Teclas Rápidas:</span>
    <span class="key-badge"><kbd>F2</kbd> Buscar SKU</span>
    <span class="key-badge"><kbd>F4</kbd> Cliente</span>
    <span class="key-badge"><kbd>F6</kbd> Descuento</span>
    <span class="key-badge"><kbd>F8</kbd> Pago Mixto</span>
    <span class="key-badge"><kbd>F10</kbd> Cobrar e Imprimir</span>
    <span class="key-badge"><kbd>ESC</kbd> Limpiar</span>
</div>

<div class="pos-layout">
    <!-- Products & Search Column -->
    <div style="display:flex; flex-direction:column;">
        <div style="margin-bottom:1rem;">
            <input type="text" id="pos-barcode-input" placeholder="Escanear código de barras o ingresar SKU (F2)..." autofocus style="width:100%; padding:0.85rem 1.25rem; font-size:1.1rem; font-family:var(--codex-font-sans); background:var(--codex-bg-surface); border:2px solid var(--codex-brand-primary); border-radius:var(--codex-radius-md); color:var(--codex-text-main); outline:none; box-shadow:var(--codex-shadow-glow);">
        </div>

        <!-- Product Grid -->
        <div class="pos-products-grid">
            <div class="product-card-v2" onclick="addToCart('P-001', 'Café Espresso Premium', 2500.00)">
                <div style="font-size:2rem; margin-bottom:0.5rem;">☕</div>
                <div style="font-weight:700; font-size:0.9rem;">Café Espresso</div>
                <div style="color:var(--codex-brand-primary); font-family:var(--codex-font-mono); font-weight:700; margin-top:0.3rem;">$2.500,00</div>
            </div>
            <div class="product-card-v2" onclick="addToCart('P-002', 'Medialuna de Manteca', 1200.00)">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🥐</div>
                <div style="font-weight:700; font-size:0.9rem;">Medialuna Manteca</div>
                <div style="color:var(--codex-brand-primary); font-family:var(--codex-font-mono); font-weight:700; margin-top:0.3rem;">$1.200,00</div>
            </div>
            <div class="product-card-v2" onclick="addToCart('P-003', 'Agua Mineral 500ml', 1800.00)">
                <div style="font-size:2rem; margin-bottom:0.5rem;">💧</div>
                <div style="font-weight:700; font-size:0.9rem;">Agua Mineral 500ml</div>
                <div style="color:var(--codex-brand-primary); font-family:var(--codex-font-mono); font-weight:700; margin-top:0.3rem;">$1.800,00</div>
            </div>
            <div class="product-card-v2" onclick="addToCart('P-004', 'Sandwich Jamón y Queso', 4500.00)">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🥪</div>
                <div style="font-weight:700; font-size:0.9rem;">Sandwich J&Q</div>
                <div style="color:var(--codex-brand-primary); font-family:var(--codex-font-mono); font-weight:700; margin-top:0.3rem;">$4.500,00</div>
            </div>
        </div>
    </div>

    <!-- POS Cart Sidebar -->
    <div class="pos-cart-sidebar">
        <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:0.75rem; border-bottom:1px solid var(--codex-border-color);">
            <div style="font-weight:800; font-size:1.1rem;">🛒 Detalle de Venta</div>
            <span style="font-size:0.75rem; background:var(--codex-brand-subtle); color:var(--codex-brand-primary); padding:0.2rem 0.5rem; border-radius:0.375rem; font-weight:700;">CONSUMIDOR FINAL</span>
        </div>

        <div id="pos-cart-items" class="pos-cart-items">
            <!-- Dynamically populated cart items -->
            <div style="text-align:center; padding:2rem; color:var(--codex-text-muted);">Escanea o selecciona artículos para comenzar</div>
        </div>

        <div style="padding-top:1rem; border-top:1px solid var(--codex-border-color);">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; color:var(--codex-text-muted);">
                <span>Subtotal:</span>
                <span id="pos-subtotal" style="font-family:var(--codex-font-mono); font-weight:600;">$0,00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:1rem; font-size:1.4rem; font-weight:800;">
                <span>TOTAL:</span>
                <span id="pos-total" style="color:var(--codex-success); font-family:var(--codex-font-mono);">$0,00</span>
            </div>

            <button type="button" onclick="checkoutPos()" class="codex-btn-v2 codex-btn-v2-primary" style="width:100%; font-size:1.1rem; padding:0.85rem;">
                ⚡ Cobrar e Imprimir (F10)
            </button>
        </div>
    </div>
</div>

<script>
    let cart = [];

    function addToCart(sku, name, price) {
        const existing = cart.find(i => i.sku === sku);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({ sku, name, price, qty: 1 });
        }
        updateCartUi();
    }

    function updateCartUi() {
        const container = document.getElementById('pos-cart-items');
        if (cart.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--codex-text-muted);">Escanea o selecciona artículos para comenzar</div>';
            document.getElementById('pos-subtotal').innerText = '$0,00';
            document.getElementById('pos-total').innerText = '$0,00';
            return;
        }

        let total = 0;
        container.innerHTML = cart.map(i => {
            const sub = i.price * i.qty;
            total += sub;
            return `
                <div class="pos-cart-item-row">
                    <div>
                        <div style="font-weight:700; font-size:0.875rem;">${i.name}</div>
                        <div style="font-size:0.75rem; color:var(--codex-text-muted);">${i.qty} x $${i.price.toLocaleString('es-AR')}</div>
                    </div>
                    <div style="font-family:var(--codex-font-mono); font-weight:700;">$${sub.toLocaleString('es-AR')}</div>
                </div>
            `;
        }).join('');

        document.getElementById('pos-subtotal').innerText = '$' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
        document.getElementById('pos-total').innerText = '$' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
    }

    function checkoutPos() {
        if (cart.length === 0) {
            alert('El carrito está vacío.');
            return;
        }
        alert('Venta procesada con éxito. Cobro registrado e instrucción de impresión de ticket enviada.');
        cart = [];
        updateCartUi();
    }
</script>
<?= $this->endSection() ?>
