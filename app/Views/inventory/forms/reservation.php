<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$selectedProductId = old('product_id', '');
$selectedProduct = null;
if (!empty($selectedProductId)) {
    foreach ($products as $p) {
        if ($p['id'] === $selectedProductId) {
            $selectedProduct = $p;
            break;
        }
    }
}
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Reserva de stock</h2>
            <p class="text-secondary mb-0">Compromete existencias para pedidos, operaciones internas o salidas planificadas.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3" id="reservation-form">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            
            <!-- Searchable Product Select -->
            <div class="col-md-7">
                <label class="form-label" for="product-search-input">Producto</label>
                <input type="hidden" name="product_id" id="selected-product-id" value="<?= esc($selectedProductId) ?>" required>
                
                <div class="position-relative" id="product-search-container">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               id="product-search-input" 
                               class="form-control border-start-0 ps-0 shadow-none" 
                               placeholder="Buscar por código (SKU) o nombre..." 
                               value="<?= esc($selectedProduct ? ($selectedProduct['sku'] . ' - ' . $selectedProduct['name']) : '') ?>"
                               autocomplete="off"
                               required>
                        <button class="btn btn-outline-secondary border-start-0" 
                                type="button" 
                                id="product-search-toggle" 
                                title="Ver lista completa"
                                aria-label="Ver lista completa">
                            <i class="bi bi-chevron-down small"></i>
                        </button>
                        <button class="btn btn-outline-danger border-start-0 <?= $selectedProduct ? '' : 'd-none' ?>" 
                                type="button" 
                                id="product-search-clear" 
                                title="Limpiar selección"
                                aria-label="Limpiar selección">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Dropdown Results Menu -->
                    <div id="product-dropdown-menu" 
                         class="dropdown-menu w-100 shadow-lg border rounded-3 p-1 mt-1 overflow-auto" 
                         style="max-height: 260px; display: none; z-index: 1055;">
                        <?php foreach ($products as $product): ?>
                            <?php 
                            $sku = $product['sku'] ?? '';
                            $name = $product['name'] ?? '';
                            $catBrand = trim(($product['category'] ?? '') . ' ' . ($product['brand'] ?? ''));
                            $unit = $product['unit'] ?? 'unidad';
                            ?>
                            <button type="button" 
                                    class="dropdown-item py-2 px-3 rounded-2 product-option-item" 
                                    data-id="<?= esc($product['id']) ?>" 
                                    data-sku="<?= esc($sku) ?>" 
                                    data-name="<?= esc($name) ?>"
                                    data-cat-brand="<?= esc($catBrand) ?>"
                                    data-display="<?= esc($sku . ' - ' . $name) ?>">
                                <div class="text-truncate">
                                    <div class="d-flex align-items-center gap-2 mb-0">
                                        <span class="badge bg-light text-dark border font-monospace small px-1.5 py-0.5"><?= esc($sku) ?></span>
                                        <strong class="text-dark product-item-name text-truncate"><?= esc($name) ?></strong>
                                    </div>
                                    <?php if ($catBrand !== ''): ?>
                                        <div class="small text-secondary ps-1 text-truncate"><?= esc($catBrand) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary small flex-shrink-0"><?= esc($unit) ?></span>
                            </button>
                        <?php endforeach; ?>
                        <div id="product-no-matches" class="text-center text-secondary py-3 small d-none">
                            No se encontraron productos coincidentes.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <label class="form-label" for="warehouse-select">Deposito</label>
                <select name="warehouse_id" id="warehouse-select" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= old('warehouse_id') === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cantidad a reservar</label>
                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" value="<?= esc(old('quantity', '1')) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Referencia</label>
                <input type="text" name="reference" class="form-control" value="<?= esc(old('reference')) ?>" placeholder="PED-001 / OT-004 / RESERVA INTERNA">
            </div>
            <div class="col-12">
                <label class="form-label">Observacion</label>
                <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>

<style>
    #product-dropdown-menu .product-option-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }
    #product-dropdown-menu .product-option-item:hover,
    #product-dropdown-menu .product-option-item:focus,
    #product-dropdown-menu .product-option-item.active-item {
        background-color: var(--bs-light, #f8f9fa);
        color: var(--bs-dark, #212529);
    }
    #product-dropdown-menu .product-option-item.selected {
        background-color: rgba(33, 37, 41, 0.08);
        font-weight: 600;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('product-search-container');
    const searchInput = document.getElementById('product-search-input');
    const hiddenInput = document.getElementById('selected-product-id');
    const dropdownMenu = document.getElementById('product-dropdown-menu');
    const toggleBtn = document.getElementById('product-search-toggle');
    const clearBtn = document.getElementById('product-search-clear');
    const noMatches = document.getElementById('product-no-matches');
    const options = Array.from(dropdownMenu.querySelectorAll('.product-option-item'));

    let activeIndex = -1;

    function openDropdown() {
        filterOptions(searchInput.value.trim());
        dropdownMenu.style.display = 'block';
        dropdownMenu.classList.add('show');
    }

    function closeDropdown() {
        dropdownMenu.style.display = 'none';
        dropdownMenu.classList.remove('show');
        activeIndex = -1;
        options.forEach(opt => opt.classList.remove('active-item'));
    }

    function isDropdownOpen() {
        return dropdownMenu.style.display === 'block';
    }

    function filterOptions(query) {
        const q = query.toLowerCase();
        let visibleCount = 0;

        options.forEach(opt => {
            const sku = (opt.dataset.sku || '').toLowerCase();
            const name = (opt.dataset.name || '').toLowerCase();
            const catBrand = (opt.dataset.catBrand || '').toLowerCase();

            // Match if query is empty or matched against sku, name or category/brand
            const matches = !q || sku.includes(q) || name.includes(q) || catBrand.includes(q);

            opt.classList.toggle('d-none', !matches);
            if (matches) {
                visibleCount++;
            }
        });

        noMatches.classList.toggle('d-none', visibleCount > 0);
    }

    function selectProduct(id, display) {
        hiddenInput.value = id;
        searchInput.value = display;
        clearBtn.classList.remove('d-none');
        options.forEach(opt => {
            if (opt.dataset.id === id) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });
        closeDropdown();
        searchInput.setCustomValidity('');
    }

    function clearSelection() {
        hiddenInput.value = '';
        searchInput.value = '';
        clearBtn.classList.add('d-none');
        options.forEach(opt => opt.classList.remove('selected'));
        openDropdown();
        searchInput.focus();
    }

    // Input events
    searchInput.addEventListener('focus', () => {
        openDropdown();
    });

    searchInput.addEventListener('input', () => {
        hiddenInput.value = '';
        clearBtn.classList.add('d-none');
        openDropdown();
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', (e) => {
        const visibleOptions = options.filter(opt => !opt.classList.contains('d-none'));

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isDropdownOpen()) {
                openDropdown();
                return;
            }
            if (visibleOptions.length > 0) {
                activeIndex = (activeIndex + 1) % visibleOptions.length;
                highlightOption(visibleOptions, activeIndex);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!isDropdownOpen()) {
                openDropdown();
                return;
            }
            if (visibleOptions.length > 0) {
                activeIndex = (activeIndex - 1 + visibleOptions.length) % visibleOptions.length;
                highlightOption(visibleOptions, activeIndex);
            }
        } else if (e.key === 'Enter') {
            if (isDropdownOpen() && activeIndex >= 0 && visibleOptions[activeIndex]) {
                e.preventDefault();
                const opt = visibleOptions[activeIndex];
                selectProduct(opt.dataset.id, opt.dataset.display);
            }
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    function highlightOption(visibleOptions, idx) {
        options.forEach(opt => opt.classList.remove('active-item'));
        if (idx >= 0 && visibleOptions[idx]) {
            const current = visibleOptions[idx];
            current.classList.add('active-item');
            current.scrollIntoView({ block: 'nearest' });
        }
    }

    // Option click
    options.forEach(opt => {
        opt.addEventListener('click', (e) => {
            e.preventDefault();
            selectProduct(opt.dataset.id, opt.dataset.display);
        });
    });

    // Toggle button
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (isDropdownOpen()) {
            closeDropdown();
        } else {
            searchInput.focus();
            openDropdown();
        }
    });

    // Clear button
    clearBtn.addEventListener('click', (e) => {
        e.preventDefault();
        clearSelection();
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            closeDropdown();
            // If user left an invalid search without choosing an item, reset to chosen or empty
            if (!hiddenInput.value) {
                searchInput.value = '';
                clearBtn.classList.add('d-none');
            }
        }
    });

    // Form submit validation
    const form = document.getElementById('reservation-form');
    form.addEventListener('submit', (e) => {
        if (!hiddenInput.value) {
            e.preventDefault();
            searchInput.setCustomValidity('Por favor seleccione un producto de la lista.');
            searchInput.reportValidity();
            searchInput.focus();
            openDropdown();
        } else {
            searchInput.setCustomValidity('');
        }
    });
});
</script>
<?= $this->endSection() ?>

