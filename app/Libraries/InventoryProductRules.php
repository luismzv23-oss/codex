<?php
namespace App\Libraries;

/** Rules for the shared Inventory product form; no stock movements are made here. */
final class InventoryProductRules
{
    public static function normalize(array $input): array
    {
        $type = (string) ($input['product_type'] ?? 'simple');
        if (!in_array($type, ['simple', 'kit'], true)) {
            throw new \InvalidArgumentException('Seleccioná un tipo de producto válido.');
        }
        $result = ['product_type' => $type, 'unit' => $type === 'kit' ? 'pack' : 'unidad'];
        foreach (['min_stock', 'max_stock', 'initial_stock'] as $field) {
            if ($type === 'kit') {
                $result[$field] = 0.0;
                continue;
            }
            $raw = trim(str_replace([' ', ','], ['', '.'], (string) ($input[$field] ?? '')));
            if ($raw !== '' && (!is_numeric($raw) || !is_finite((float) $raw) || (float) $raw < 0)) {
                throw new \InvalidArgumentException('Las cantidades de stock deben ser números mayores o iguales a cero.');
            }
            $result[$field] = $field === 'initial_stock' && $raw === '' ? null : (float) $raw;
        }
        return $result;
    }
}
