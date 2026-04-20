<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * AccountingSeeder — Standard Argentine chart of accounts.
 * Seeds a complete hierarchical plan de cuentas for a company.
 */
class AccountingSeeder extends Seeder
{
    public function run()
    {
        $companyId = $this->getCompanyId();
        if (!$companyId) {
            CLI::write('No company found. Skipping accounting seeder.', 'yellow');
            return;
        }

        $existing = $this->db->table('accounts')->where('company_id', $companyId)->countAllResults();
        if ($existing > 0) {
            CLI::write("Company already has {$existing} accounts. Skipping.", 'yellow');
            return;
        }

        $accounts = $this->standardArgentineChart();
        $now = date('Y-m-d H:i:s');

        foreach ($accounts as $account) {
            $this->db->table('accounts')->insert(array_merge($account, [
                'id' => $this->uuid(),
                'company_id' => $companyId,
                'active' => 1,
                'currency_code' => 'ARS',
                'created_at' => $now,
            ]));
        }

        CLI::write('Seeded ' . count($accounts) . ' accounts for company ' . $companyId, 'green');

        // Seed default fiscal period
        $year = date('Y');
        $existing = $this->db->table('fiscal_periods')->where('company_id', $companyId)->where('start_date', "{$year}-01-01")->countAllResults();
        if ($existing === 0) {
            $this->db->table('fiscal_periods')->insert([
                'id' => $this->uuid(), 'company_id' => $companyId,
                'name' => "Ejercicio {$year}", 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31",
                'status' => 'open', 'created_at' => $now,
            ]);
            CLI::write("Seeded fiscal period: Ejercicio {$year}", 'green');
        }
    }

    private function standardArgentineChart(): array
    {
        return [
            // ── ACTIVO ──
            ['code' => '1',     'name' => 'ACTIVO',                        'account_type' => 'asset',     'is_group' => 1, 'level' => 1, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '1.1',   'name' => 'Activo Corriente',              'account_type' => 'asset',     'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '1.1.01','name' => 'Caja',                          'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.02','name' => 'Banco Cuenta Corriente',        'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.03','name' => 'Valores a Depositar',           'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.04','name' => 'Deudores por Ventas',           'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.05','name' => 'Documentos a Cobrar',           'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.06','name' => 'IVA Credito Fiscal',            'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.07','name' => 'Anticipos a Proveedores',       'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.1.08','name' => 'Bienes de Cambio',              'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.2',   'name' => 'Activo No Corriente',           'account_type' => 'asset',     'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '1.2.01','name' => 'Rodados',                       'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.2.02','name' => 'Muebles y Utiles',             'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.2.03','name' => 'Equipos de Computacion',        'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.2.04','name' => 'Inmuebles',                     'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '1.2.05','name' => 'Depreciacion Acumulada',        'account_type' => 'asset',     'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],

            // ── PASIVO ──
            ['code' => '2',     'name' => 'PASIVO',                        'account_type' => 'liability', 'is_group' => 1, 'level' => 1, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '2.1',   'name' => 'Pasivo Corriente',              'account_type' => 'liability', 'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '2.1.01','name' => 'Proveedores',                   'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.02','name' => 'Documentos a Pagar',            'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.03','name' => 'IVA Debito Fiscal',             'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.04','name' => 'Cargas Sociales a Pagar',       'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.05','name' => 'Sueldos a Pagar',               'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.06','name' => 'Impuestos a Pagar',             'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.07','name' => 'Retenciones a Depositar',       'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.08','name' => 'Percepciones a Depositar',      'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.1.09','name' => 'Anticipos de Clientes',         'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '2.2',   'name' => 'Pasivo No Corriente',           'account_type' => 'liability', 'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '2.2.01','name' => 'Prestamos Bancarios',           'account_type' => 'liability', 'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],

            // ── PATRIMONIO NETO ──
            ['code' => '3',     'name' => 'PATRIMONIO NETO',               'account_type' => 'equity',    'is_group' => 1, 'level' => 1, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '3.1.01','name' => 'Capital Social',                'account_type' => 'equity',    'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '3.1.02','name' => 'Reserva Legal',                 'account_type' => 'equity',    'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '3.1.03','name' => 'Resultados Acumulados',         'account_type' => 'equity',    'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '3.1.04','name' => 'Resultado del Ejercicio',       'account_type' => 'equity',    'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],

            // ── INGRESOS ──
            ['code' => '4',     'name' => 'INGRESOS',                      'account_type' => 'revenue',   'is_group' => 1, 'level' => 1, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '4.1.01','name' => 'Ventas',                        'account_type' => 'revenue',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '4.1.02','name' => 'Intereses Ganados',             'account_type' => 'revenue',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '4.1.03','name' => 'Descuentos Obtenidos',          'account_type' => 'revenue',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '4.1.04','name' => 'Resultado por Tenencia',        'account_type' => 'revenue',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],

            // ── EGRESOS ──
            ['code' => '5',     'name' => 'EGRESOS',                       'account_type' => 'expense',   'is_group' => 1, 'level' => 1, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '5.1',   'name' => 'Costo de Ventas',               'account_type' => 'expense',   'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '5.1.01','name' => 'Costo Mercaderias Vendidas',    'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2',   'name' => 'Gastos de Administracion',      'account_type' => 'expense',   'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '5.2.01','name' => 'Sueldos y Jornales',            'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.02','name' => 'Cargas Sociales',               'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.03','name' => 'Alquileres',                    'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.04','name' => 'Servicios (Luz, Gas, Tel)',      'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.05','name' => 'Depreciaciones',                'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.06','name' => 'Honorarios Profesionales',      'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.07','name' => 'Gastos Bancarios',              'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.2.08','name' => 'Impuesto Ingresos Brutos',      'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.3',   'name' => 'Gastos de Comercializacion',    'account_type' => 'expense',   'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '5.3.01','name' => 'Comisiones por Ventas',         'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.3.02','name' => 'Publicidad y Propaganda',       'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.3.03','name' => 'Fletes y Acarreos',            'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.3.04','name' => 'Descuentos Otorgados',          'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.4',   'name' => 'Gastos Financieros',            'account_type' => 'expense',   'is_group' => 1, 'level' => 2, 'accepts_entries' => 0, 'parent_id' => null],
            ['code' => '5.4.01','name' => 'Intereses Pagados',             'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
            ['code' => '5.4.02','name' => 'Diferencia de Cambio',          'account_type' => 'expense',   'is_group' => 0, 'level' => 3, 'accepts_entries' => 1, 'parent_id' => null],
        ];
    }

    private function getCompanyId(): ?string
    {
        $company = $this->db->table('companies')->orderBy('name', 'ASC')->limit(1)->get()->getRowArray();
        return $company['id'] ?? null;
    }

    private function uuid(): string
    {
        return app_uuid();
    }
}
