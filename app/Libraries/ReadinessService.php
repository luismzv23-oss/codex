<?php

namespace App\Libraries;

use App\Models\{CompanyModel, SalesSettingModel};

/** ERP readiness checklist of a company (moved from DashboardController so the company setup can reuse it). */
class ReadinessService
{
    public function evaluate(?string $companyId): array
    {
        if (! $companyId) {
            return ['status' => 'blocked', 'score' => 0, 'blocking' => ['No hay empresa seleccionada para evaluar readiness.'], 'warnings' => [], 'ok' => [], 'checks' => []];
        }
        $db = db_connect();
        $count = fn(string $table, array $where = ['active' => 1]) => $db->table($table)->where('company_id', $companyId)->where($where)->countAllResults();
        $company = (new CompanyModel())->find($companyId);
        $salesSettings = (new SalesSettingModel())->where('company_id', $companyId)->first();
        $checks = [
            ['label' => 'Empresa activa', 'ok' => (bool) ($company && (int) ($company['active'] ?? 0) === 1), 'critical' => true],
            ['label' => 'Monedas activas configuradas', 'ok' => $count('currencies') > 0, 'critical' => true],
            ['label' => 'Sucursales activas', 'ok' => $count('branches') > 0, 'critical' => true],
            ['label' => 'Usuarios activos', 'ok' => $count('users') > 0, 'critical' => true],
            ['label' => 'Sistemas asignados', 'ok' => $count('company_systems') > 0, 'critical' => true],
            ['label' => 'Depositos activos', 'ok' => $count('inventory_warehouses') > 0, 'critical' => true],
            ['label' => 'Productos activos', 'ok' => $count('inventory_products') > 0, 'critical' => false],
            ['label' => 'Comprobantes activos', 'ok' => $count('sales_document_types') > 0, 'critical' => true],
            ['label' => 'Puntos de venta activos', 'ok' => $count('sales_points_of_sale') > 0, 'critical' => true],
            ['label' => 'Cajas definidas', 'ok' => $count('cash_registers') > 0, 'critical' => false],
            ['label' => 'Caja operativa abierta', 'ok' => $count('cash_sessions', ['status' => 'open']) > 0, 'critical' => false],
            ['label' => 'Configuracion ARCA completa', 'ok' => self::arcaReady($salesSettings), 'critical' => false],
            ['label' => 'Bitacora ARCA inicial', 'ok' => $count('sales_arca_events', []) > 0, 'critical' => false],
        ];

        return self::summarize($checks) + ['company' => $company];
    }

    /** Groups checks into ok / blocking (critical) / warnings with a score and an overall status. */
    public static function summarize(array $checks): array
    {
        $blocking = $warnings = $ok = [];
        foreach ($checks as $check) {
            if ($check['ok']) {
                $ok[] = $check['label'];
            } elseif ($check['critical']) {
                $blocking[] = $check['label'];
            } else {
                $warnings[] = $check['label'];
            }
        }

        return [
            'status' => $blocking !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'score' => $checks ? (int) round(count($ok) / count($checks) * 100) : 0,
            'blocking' => $blocking, 'warnings' => $warnings, 'ok' => $ok, 'checks' => $checks,
        ];
    }

    public static function arcaReady(?array $settings): bool
    {
        if (! $settings || (int) ($settings['arca_enabled'] ?? 0) !== 1) {
            return false;
        }
        $arca = new ArcaService();
        $normalized = $arca->sanitizeSettings($settings);
        $certificate = trim((string) ($normalized['certificate_path'] ?? ''));
        $key = trim((string) ($normalized['private_key_path'] ?? ''));

        return trim((string) ($settings['arca_cuit'] ?? '')) !== '' && $certificate !== '' && $key !== '' && is_file($certificate) && is_file($key);
    }
}
