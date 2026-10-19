(function (root) {
    'use strict';
    // Aggregate shared components across all presentations before comparing stock.
    function shortages(lines, warehouse) {
        if (!warehouse) return [];
        const demand = new Map();
        lines.forEach(({product, quantity}) => {
            if (!product || !(quantity > 0)) return;
            const recipe = Object.keys(product.stock_requirements || {}).length
                ? product.stock_requirements : {[product.id]: 1};
            Object.entries(recipe).forEach(([id, units]) => {
                const balance = product.component_stocks?.[id]?.[warehouse]
                    || (id === product.id ? product.stocks?.[warehouse] : null);
                const row = demand.get(id) || {id, required: 0, available: Number(balance?.available || 0), products: []};
                row.required += Number(quantity) * Number(units);
                row.products.push(product.id);
                demand.set(id, row);
            });
        });
        return [...demand.values()].filter(row => row.required > row.available + 0.000001);
    }
    root.SalesPackStock = {shortages};
    if (typeof module !== 'undefined') module.exports = root.SalesPackStock;
})(typeof window !== 'undefined' ? window : globalThis);
