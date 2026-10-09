<?php
namespace App\Libraries;

/** Called inside the sale confirmation transaction; never during printing. */
final class DeliveryNotePricing
{
    public function refresh(string $companyId, array $sale, array $items): array
    {
        $db = db_connect();
        foreach ($items as &$item) {
            $product = $db->table('inventory_products')->where('company_id', $companyId)->where('id', $item['product_id'])->where('active', 1)->get()->getRowArray();
            if (!$product) throw new \RuntimeException('Un producto del remito ya no esta disponible en esta empresa.');
            $price = (float) $product['sale_price'];
            if (!empty($sale['price_list_id'])) {
                $list = $db->table('sales_price_lists')->where('company_id', $companyId)->where('id', $sale['price_list_id'])->where('active', 1)->get()->getRowArray();
                if (!$list) throw new \RuntimeException('La lista de precios del remito ya no esta disponible.');
                $entry = $db->table('sales_price_list_items')->where('price_list_id', $list['id'])->where('product_id', $product['id'])->get()->getRowArray();
                if ($entry) $price = (float) $entry['price'];
            }
            $values = self::line($item, $price);
            if (!(new \App\Models\SaleItemModel())->update($item['id'], $values)) throw new \RuntimeException('No se pudo actualizar el precio del remito.');
            $item = array_merge($item, $values);
        }
        unset($item);
        $values = [
            'subtotal'=>round(array_sum(array_column($items,'subtotal')),2),
            'tax_total'=>round(array_sum(array_column($items,'tax_total')),2),
            'item_discount_total'=>round(array_sum(array_column($items,'discount_amount')),2),
        ];
        $values['total'] = max(0, round($values['subtotal'] + $values['tax_total'] - (float)($sale['global_discount_total'] ?? 0) + (float)($sale['payment_surcharge_amount'] ?? 0),2));
        $paid = (float)($sale['paid_total'] ?? 0);
        $values['payment_status'] = $paid <= 0 ? 'pending' : ($paid < $values['total'] ? 'partial' : 'paid');
        if (!(new \App\Models\SaleModel())->update($sale['id'], $values)) throw new \RuntimeException('No se pudieron actualizar los importes del remito.');
        return [array_merge($sale,$values),$items];
    }

    public static function line(array $item, float $price): array
    {
        if (!is_finite($price) || $price < 0) throw new \RuntimeException('El precio vigente del producto es invalido.');
        $discount = round((float)$item['quantity'] * $price * (float)($item['discount_rate'] ?? 0) / 100,2);
        $gross = round((float)$item['quantity'] * $price - $discount,2);
        $net = round($gross / (1 + (float)($item['tax_rate'] ?? 0) / 100),2);
        return ['unit_price'=>$price,'discount_amount'=>$discount,'subtotal'=>$net,'tax_total'=>round($gross-$net,2),'line_total'=>$gross];
    }
}
