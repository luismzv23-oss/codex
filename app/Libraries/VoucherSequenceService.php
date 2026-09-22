<?php

namespace App\Libraries;

use App\Models\{BranchModel, VoucherSequenceModel};
use RuntimeException;

class VoucherSequenceService
{
    public function next(string $companyId, string $type, string $prefix, ?string $branchId = null, bool $preview = false): string
    {
        return (new SettingsService())->transaction($companyId, function () use ($companyId, $type, $prefix, $branchId, $preview) {
            $model = new VoucherSequenceModel();
            if ($branchId && ! (new BranchModel())->where('company_id', $companyId)->where('active', 1)->find($branchId)) {
                throw new RuntimeException('La sucursal de numeracion no pertenece a la empresa o esta inactiva.');
            }
            $rows = [];
            if ($branchId) { $rows = $model->where('company_id', $companyId)->where('document_type', $type)->where('branch_id', $branchId)->findAll(); }
            if (! $rows) { $rows = $model->where('company_id', $companyId)->where('document_type', $type)->where('branch_id', null)->findAll(); }
            // Legacy defaults were attached to MAIN even for users without a branch.
            if (! $rows && ! $branchId) {
                $main = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->where('active', 1)->first();
                if ($main) { $rows = $model->where('company_id', $companyId)->where('document_type', $type)->where('branch_id', $main['id'])->findAll(); }
            }
            if (count($rows) > 1) { throw new RuntimeException('Hay numeraciones duplicadas; revisa la configuracion antes de emitir.'); }
            if (! $rows) {
                if ($preview) { return strtoupper($prefix) . '-00000001'; }
                $rows[] = (new SettingsService())->sequence($companyId, ['document_type' => $type, 'prefix' => $prefix, 'branch_id' => $branchId, 'current_number' => 1]);
            }
            $row = $rows[0];
            if (! $row['active']) { throw new RuntimeException('La numeracion del documento esta inactiva.'); }
            $number = (int) $row['current_number'];
            if ($number < 1 || $number >= 2147483647) { throw new RuntimeException('El correlativo no es valido o esta agotado.'); }
            $actualPrefix = strtoupper(trim((string) $row['prefix'])) ?: strtoupper($prefix);
            if (! $preview && ! $model->update($row['id'], ['current_number' => $number + 1])) { throw new RuntimeException('No se pudo reservar el correlativo.'); }
            return $actualPrefix . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
        });
    }
}
