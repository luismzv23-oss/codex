<?php
namespace App\Libraries;

final class InventoryDashboard
{
    public function build(string $companyId, array $input = []): array
    {
        $db = db_connect();
        $warehouses = $db->table('inventory_warehouses')->select('id,name')->where('company_id', $companyId)->orderBy('name')->get()->getResultArray();
        $categories = array_column($db->table('inventory_products')->select('category')->where('company_id', $companyId)->where('active', 1)->where('category !=', '')->groupBy('category')->orderBy('category')->get()->getResultArray(), 'category');
        $date = static function ($value, $default) {
            $d = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            return $d && $d->format('Y-m-d') === $value ? $value : $default;
        };
        $to = $date($input['to'] ?? null, date('Y-m-d'));
        $from = $date($input['from'] ?? null, date('Y-m-d', strtotime($to . ' -29 days')));
        if ($from > $to) { $from = $to; }
        $from = max($from, date('Y-m-d', strtotime($to . ' -89 days')));
        $warehouse = in_array($input['warehouse'] ?? '', array_column($warehouses, 'id'), true) ? $input['warehouse'] : '';
        $category = in_array($input['category'] ?? '', $categories, true) ? $input['category'] : '';
        $products = $db->table('inventory_products')->select('id,sku,name,category,unit,min_stock,cost_price')->where('company_id', $companyId)->where('active', 1)->where('product_type !=', 'service');
        if ($category !== '') { $products->where('category', $category); }
        $products = $products->get()->getResultArray();
        $stock = $db->table('inventory_stock_levels')->select('product_id')->selectSum('quantity')->selectSum('reserved_quantity')->where('company_id', $companyId);
        if ($warehouse !== '') { $stock->where('warehouse_id', $warehouse); }
        $stock = array_column($stock->groupBy('product_id')->get()->getResultArray(), null, 'product_id');
        $d = ['out'=>0,'low'=>0,'reserved'=>0,'value'=>0.0,'products'=>count($products),'movement_count'=>0,'alerts'=>[], 'days'=>[], 'types'=>[], 'warehouses'=>$warehouses,'categories'=>$categories,'filters'=>compact('from','to','warehouse','category'),'updated'=>date('H:i:s')];
        foreach ($products as $p) {
            $row = $stock[$p['id']] ?? [];
            $qty = (float)($row['quantity'] ?? 0); $reserved = (float)($row['reserved_quantity'] ?? 0);
            $p['available'] = $qty - $reserved;
            $d['value'] += $qty * (float)$p['cost_price'];
            if ($reserved > 0) { $d['reserved']++; }
            if ($p['available'] <= 0) { $d['out']++; $p['state'] = 'Sin disponible'; }
            elseif ($p['available'] <= (float)$p['min_stock']) { $d['low']++; $p['state'] = 'Bajo mínimo'; }
            else { continue; }
            $d['alerts'][] = $p;
        }
        usort($d['alerts'], static fn($a,$b) => $a['available'] <=> $b['available']);
        for ($day = $from; $day <= $to; $day = date('Y-m-d', strtotime($day . ' +1 day'))) { $d['days'][$day] = ['ingreso'=>0,'egreso'=>0,'otros'=>0]; }
        $moves = $db->table('inventory_movements m')->select('DATE(m.occurred_at) AS day, m.movement_type, COUNT(*) AS count', false)
            ->join('inventory_products p', 'p.id=m.product_id AND p.company_id=m.company_id')
            ->where('m.company_id',$companyId)->where('m.occurred_at >=',$from.' 00:00:00')->where('m.occurred_at <',date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00');
        if ($category !== '') { $moves->where('p.category',$category); }
        if ($warehouse !== '') { $moves->groupStart()->where('m.source_warehouse_id',$warehouse)->orWhere('m.destination_warehouse_id',$warehouse)->groupEnd(); }
        foreach ($moves->groupBy('DATE(m.occurred_at), m.movement_type',false)->get()->getResultArray() as $m) {
            $type = in_array($m['movement_type'],['ingreso','egreso'],true) ? $m['movement_type'] : 'otros';
            $n = (int)$m['count']; $d['days'][$m['day']][$type] += $n;
            $d['types'][$m['movement_type']] = ($d['types'][$m['movement_type']] ?? 0) + $n;
            $d['movement_count'] += $n;
        }
        return $d;
    }
}
