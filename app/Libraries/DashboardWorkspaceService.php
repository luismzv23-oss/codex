<?php

namespace App\Libraries;

use DateTimeImmutable;

/** Read-only dashboard projections. Scope is derived from the authenticated user. */
class DashboardWorkspaceService
{
    private const MODULES = ['ventas' => 'Ventas', 'inventario' => 'Inventario', 'compras' => 'Compras', 'caja' => 'Caja'];

    public function build(array $user, array $input = []): array
    {
        $db = db_connect();
        $super = ($user['role_slug'] ?? '') === 'superadmin';
        $role = $user['role_slug'] ?? 'operador';
        $days = in_array((int) ($input['days'] ?? 30), [7, 30, 90], true) ? (int) ($input['days'] ?? 30) : 30;
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $end = $today->modify('+1 day')->format('Y-m-d');
        $companies = $super ? $db->table('companies')->select('id, name')->where('active', 1)->orderBy('name')->get()->getResultArray() : [];
        $companyId = $super ? (string) ($input['company_id'] ?? $user['company_id'] ?? ($companies[0]['id'] ?? '')) : (string) ($user['company_id'] ?? '');
        if ($super && $companyId === '' && ! isset($input['company_id'])) { $companyId = $companies[0]['id'] ?? ''; }
        $company = $companyId !== '' ? $db->table('companies')->select('id, name, currency_code')->where('id', $companyId)->where('active', 1)->get()->getRowArray() : null;
        $titles = ['superadmin' => ['Control de tus empresas', 'Elige una empresa y encuentra dónde actuar.'], 'admin' => ['El pulso de tu empresa', 'Resultados, pendientes y próximos pasos en un mismo lugar.'], 'vendedor' => ['Tu jornada de ventas', 'Tus operaciones y accesos rápidos para atender mejor.'], 'operador' => ['Tu centro de operaciones', 'Las tareas y los módulos que tienes asignados.']];
        $out = ['title' => ($titles[$role] ?? $titles['operador'])[0], 'description' => ($titles[$role] ?? $titles['operador'])[1],
            'role' => $role, 'role_label' => $user['role_name'] ?? ucfirst($role), 'company' => $company, 'companies' => $companies,
            'days' => $days, 'start' => $start, 'end' => $today->format('Y-m-d'), 'updated' => date('d/m/Y H:i'),
            'modules' => [], 'metrics' => [], 'tasks' => [], 'actions' => [], 'activity' => [], 'scope' => 'Empresa seleccionada', 'error' => null];
        if (! $company) { $out['error'] = 'No hay una empresa activa disponible. Solicita la asignación correspondiente.'; return $out; }
        $branchId = null;
        if (! $super && ! empty($user['branch_id'])) {
            $branch = $db->table('branches')->where('id', $user['branch_id'])->where('company_id', $companyId)->where('active', 1)->get()->getRowArray();
            if (! $branch) { $out['error'] = 'Tu sucursal no está disponible en esta empresa.'; return $out; }
            if ($role !== 'admin' || ($branch['code'] ?? '') !== 'MAIN') { $branchId = $branch['id']; $out['scope'] = 'Sucursal: ' . $branch['name']; }
        }
        $access = $this->access($user, $companyId);
        $ownSales = $role === 'vendedor' || ($access['ventas'] ?? '') === 'vendedor';
        if ($ownSales) { $out['scope'] .= ' · Ventas registradas por ti'; }
        $url = static fn(string $path): string => site_url($path) . ($super ? '?company_id=' . rawurlencode($companyId) : '');
        foreach ($access as $slug => $level) {
            if (! isset(self::MODULES[$slug])) { continue; }
            $module = ['id' => $slug, 'name' => self::MODULES[$slug], 'level' => $level, 'url' => $url($slug), 'series' => [], 'statuses' => [], 'total' => 0];
            if ($slug === 'inventario') {
                $query = $db->table('inventory_products p')->select('p.id, p.name, st.warehouse_id, SUM(COALESCE(st.quantity, 0) - COALESCE(st.reserved_quantity, 0)) AS available, MAX(COALESCE(st.min_stock, p.min_stock, 0)) AS minimum', false)
                    ->join('inventory_stock_levels st', 'st.product_id = p.id AND st.company_id = p.company_id', 'left')
                    ->where('p.company_id', $companyId)->where('p.active', 1);
                if ($branchId) { $query->join('inventory_warehouses w', 'w.id = st.warehouse_id AND w.company_id = p.company_id')->where('w.branch_id', $branchId); }
                $stock = $query->groupBy('p.id, p.name, st.warehouse_id')->get()->getResultArray();
                $low = array_values(array_filter($stock, static fn(array $r): bool => (float) $r['available'] <= (float) $r['minimum']));
                $out['metrics'][] = ['label' => 'Productos / depósitos por reponer', 'value' => count($low), 'note' => 'Disponibilidad actual, descontando reservas', 'url' => $url($slug), 'module' => $slug];
                if ($low) { $out['tasks'][] = ['label' => 'Revisar reposición de stock', 'value' => count($low), 'detail' => 'Productos por debajo del mínimo disponible.', 'url' => $url($slug), 'module' => $slug]; }
                $module['statuses'] = [['label' => 'Por reponer', 'value' => count($low)], ['label' => 'Sobre el mínimo', 'value' => count($stock) - count($low)]];
                $module['status_note'] = 'Disponibilidad actual por producto y depósito';
                $base = $db->table('inventory_movements m')->where('m.company_id', $companyId);
                if ($branchId) {
                    $warehouses = array_column($db->table('inventory_warehouses')->select('id')->where('company_id', $companyId)->where('branch_id', $branchId)->get()->getResultArray(), 'id');
                    if ($warehouses) { $base->groupStart()->whereIn('m.source_warehouse_id', $warehouses)->orWhereIn('m.destination_warehouse_id', $warehouses)->groupEnd(); }
                    else { $base->where('m.id', '__no_access__'); }
                }
                $date = 'm.occurred_at'; $idField = 'm.id'; $reference = 'm.source_document'; $status = 'm.movement_type';
            } else {
                $definitions = ['ventas' => ['sales', 'issue_date', 'sale_number', 'status'], 'compras' => ['purchase_orders', 'issued_at', 'order_number', 'status'], 'caja' => ['cash_movements', 'occurred_at', 'reference_number', 'movement_type']];
                [$table, $column, $ref, $state] = $definitions[$slug];
                $base = $db->table($table . ' m')->where('m.company_id', $companyId);
                if ($slug === 'ventas' && $ownSales) { $base->where('m.created_by', (string) ($user['id'] ?? '__no_user__')); }
                if ($slug === 'caja') {
                    $base->join('cash_sessions cs', 'cs.id = m.cash_session_id AND cs.company_id = m.company_id')->join('cash_registers cr', 'cr.id = cs.cash_register_id AND cr.company_id = m.company_id');
                    if ($branchId) { $base->where('cr.branch_id', $branchId); }
                    if ($role === 'vendedor') { $base->where('cs.opened_by', $user['id'] ?? '__no_user__'); }
                    $sessions = $db->table('cash_sessions cs')->join('cash_registers cr', 'cr.id = cs.cash_register_id AND cr.company_id = cs.company_id')->where('cs.company_id', $companyId)->where('cs.status', 'open');
                    if ($branchId) { $sessions->where('cr.branch_id', $branchId); }
                    if ($role === 'vendedor') { $sessions->where('cs.opened_by', $user['id'] ?? '__no_user__'); }
                    $open = $sessions->countAllResults();
                    $out['metrics'][] = ['label' => $role === 'vendedor' ? 'Tus cajas abiertas' : 'Cajas abiertas', 'value' => $open, 'note' => 'Estado actual de las sesiones', 'url' => $url($slug), 'module' => $slug];
                    $out['tasks'][] = ['label' => $open ? 'Revisar sesiones de caja' : 'Preparar la caja', 'value' => $open, 'detail' => $open ? 'Consulta movimientos y prepara el cierre.' : 'No hay sesiones abiertas en este alcance.', 'url' => $url($slug), 'module' => $slug];
                } elseif ($branchId) { $base->where('m.branch_id', $branchId); }
                $date = 'm.' . $column; $idField = 'm.id'; $reference = 'm.' . $ref; $status = 'm.' . $state;
            }
            $period = (clone $base)->where($date . ' >=', $start . ' 00:00:00')->where($date . ' <', $end . ' 00:00:00');
            $series = (clone $period)->select('DATE(' . $date . ') AS day, COUNT(*) AS quantity', false)->groupBy('DATE(' . $date . ')', false)->orderBy('day')->get()->getResultArray();
            $byDate = array_column($series, 'quantity', 'day');
            for ($i = 0; $i < $days; $i++) { $day = (new DateTimeImmutable($start))->modify('+' . $i . ' days')->format('Y-m-d'); $module['series'][] = ['day' => $day, 'value' => (int) ($byDate[$day] ?? 0)]; }
            $module['total'] = array_sum(array_column($module['series'], 'value'));
            if (! $module['statuses']) {
                $states = (clone $period)->select($status . ' AS state, COUNT(*) AS quantity', false)->groupBy($status)->orderBy('quantity', 'DESC')->get()->getResultArray();
                $module['statuses'] = array_map(fn(array $r): array => ['label' => $this->statusLabel($r['state']), 'value' => (int) $r['quantity']], $states);
                $module['status_note'] = 'Distribución de los registros del período';
            }
            if ($slug === 'compras') {
                $out['metrics'][] = ['label' => 'Órdenes de compra', 'value' => $module['total'],
                    'note' => 'Registros de los últimos ' . $days . ' días · Todos los estados', 'url' => $url($slug), 'module' => $slug];
            }
            if (in_array($slug, ['ventas', 'compras'], true)) {
                $pending = (clone $base)->whereIn('m.status', $slug === 'ventas' ? ['draft'] : ['approved', 'received_partial'])->countAllResults();
                if ($pending) { $out['tasks'][] = ['label' => $slug === 'ventas' ? 'Continuar borradores de venta' : 'Compras pendientes de recepción', 'value' => $pending, 'detail' => 'Pendientes actuales, incluyendo períodos anteriores.', 'url' => $url($slug), 'module' => $slug]; }
                if ($slug === 'ventas') {
                    $amounts = (clone $period)->select('m.currency_code, SUM(m.total) AS amount', false)->whereIn('m.status', ['confirmed', 'returned_partial', 'returned_total'])->groupBy('m.currency_code')->get()->getResultArray();
                    foreach ($amounts as $amount) { $out['metrics'][] = ['label' => $ownSales ? 'Tus ventas emitidas' : 'Ventas emitidas', 'value' => number_format((float) $amount['amount'], 2, ',', '.') . ' ' . $amount['currency_code'], 'note' => 'Total original emitido; no descuenta devoluciones', 'url' => $url($slug), 'module' => $slug]; }
                }
            }
            $recent = (clone $period)->select($idField . ' AS id, ' . $reference . ' AS reference, ' . $status . ' AS state, ' . $date . ' AS occurred')->orderBy($date, 'DESC')->limit(8)->get()->getResultArray();
            foreach ($recent as $row) { $out['activity'][] = ['module' => $slug, 'name' => self::MODULES[$slug], 'reference' => $row['reference'] ?: 'Movimiento', 'status' => $this->statusLabel($row['state']), 'date' => $row['occurred'], 'url' => $url($slug)]; }
            $out['modules'][] = $module;
            $out['actions'][] = ['label' => 'Abrir ' . self::MODULES[$slug], 'description' => $level === 'view' ? 'Consulta autorizada' : 'Continuar tu trabajo', 'url' => $url($slug), 'module' => $slug];
            if ($slug === 'ventas' && in_array($level, ['manage', 'vendedor'], true)) { $out['actions'][] = ['label' => 'Abrir punto de venta', 'description' => 'Atender y registrar una venta', 'url' => $url('ventas/pos'), 'module' => $slug]; }
        }
        if ($super) { array_unshift($out['metrics'], ['label' => 'Empresas activas', 'value' => count($companies), 'note' => 'Plataforma · Indicador global', 'url' => site_url('empresas'), 'module' => 'plataforma']); }
        usort($out['activity'], static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        $out['activity'] = array_slice($out['activity'], 0, 24);
        return $out;
    }

    private function access(array $user, string $companyId): array
    {
        $super = ($user['role_slug'] ?? '') === 'superadmin';
        if (! $super && ! in_array('systems.view', $user['permissions'] ?? [], true)) { return []; }
        $query = db_connect()->table('systems s')->select('s.slug')->where('s.active', 1)->whereIn('s.slug', array_keys(self::MODULES));
        if (! $super) {
            $query->select('us.access_level')->join('company_systems cs', 'cs.system_id = s.id')->join('user_systems us', 'us.system_id = s.id AND us.company_id = cs.company_id')
                ->where('cs.company_id', $companyId)->where('cs.active', 1)->where('us.user_id', $user['id'] ?? '__no_user__')->where('us.active', 1);
        }
        $out = [];
        foreach ($query->get()->getResultArray() as $row) {
            if (($user['role_slug'] ?? '') === 'vendedor' && ! in_array($row['slug'], ['ventas', 'caja'], true)) { continue; }
            $level = $super ? 'manage' : $row['access_level'];
            if (in_array($level, ['view', 'manage', 'vendedor'], true)) { $out[$row['slug']] = $level; }
        }
        return $out;
    }

    private function statusLabel(?string $state): string
    {
        return ['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'cancelled' => 'Anulada', 'returned_partial' => 'Devolución parcial', 'returned_total' => 'Devuelta',
            'approved' => 'Aprobada', 'received_partial' => 'Recepción parcial', 'received_total' => 'Recibida', 'ingreso' => 'Ingreso', 'egreso' => 'Egreso',
            'transferencia' => 'Transferencia', 'ajuste' => 'Ajuste', 'sale_payment' => 'Cobro de venta', 'purchase_payment' => 'Pago a proveedor', 'income' => 'Ingreso', 'expense' => 'Egreso'][$state ?? ''] ?? ucfirst(str_replace('_', ' ', $state ?? 'Sin estado'));
    }
}
