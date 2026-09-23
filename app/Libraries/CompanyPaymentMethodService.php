<?php
namespace App\Libraries;

use App\Models\CompanyPaymentMethodModel;
use RuntimeException;

class CompanyPaymentMethodService
{
    public const TYPES = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card' => 'Tarjeta', 'check' => 'Cheque', 'wallet' => 'Billetera', 'other' => 'Otro'];
    public const DESTINATIONS = ['cash' => 'Caja', 'bank' => 'Banco', 'clearing' => 'Cuenta transitoria'];

    public function save(string $companyId, array $input, ?string $id = null): array
    {
        return (new SettingsService())->transaction($companyId, function () use ($companyId, $input, $id) {
            $model = new CompanyPaymentMethodModel();
            if ($id && ! $model->where('company_id', $companyId)->find($id)) {
                throw new RuntimeException('Medio de pago no disponible.');
            }
            $data = ['company_id' => $companyId];
            $percentage = $input['percentage'] ?? '0.00';
            if ((! is_string($percentage) && ! is_int($percentage) && ! is_float($percentage))
                || ! preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $percentage)
                || ! is_finite((float) $percentage) || (float) $percentage > 100) {
                throw new RuntimeException('El porcentaje debe estar entre 0 y 100, con un máximo de dos decimales.');
            }
            $data['percentage'] = round((float) $percentage, 2);
            foreach (['code' => 30, 'name' => 120] as $key => $limit) {
                $value = $input[$key] ?? '';
                if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $limit) {
                    throw new RuntimeException('Completa correctamente el campo ' . $key . '.');
                }
                $data[$key] = trim($value);
            }
            $data['code'] = strtoupper($data['code']);
            if (! preg_match('/^[A-Z0-9_ -]+$/D', $data['code'])) {
                throw new RuntimeException('El código admite letras sin acentos, números, espacios, guiones y guiones bajos.');
            }
            $duplicate = (new CompanyPaymentMethodModel())->withDeleted()->where('company_id', $companyId)->where('code', $data['code']);
            if ($id) { $duplicate->where('id !=', $id); }
            if ($duplicate->first()) { throw new RuntimeException('El código ya existe en esta empresa, incluso entre los medios eliminados.'); }
            foreach (['type' => self::TYPES, 'funds_destination' => self::DESTINATIONS] as $key => $options) {
                if (! is_string($input[$key] ?? null) || ! array_key_exists($input[$key], $options)) {
                    throw new RuntimeException('Selecciona un tipo y un destino de fondos válidos.');
                }
                $data[$key] = $input[$key];
            }
            foreach (['active', 'allows_installments', 'requires_confirmation'] as $key) {
                if (! in_array($input[$key] ?? null, ['0', '1', 0, 1], true)) { throw new RuntimeException('Indicador inválido: ' . $key); }
                $data[$key] = (int) $input[$key];
            }
            foreach (['currency_ids' => 'currencies', 'branch_ids' => 'branches', 'point_of_sale_ids' => 'sales_points_of_sale'] as $key => $table) {
                $ids = $input[$key] ?? [];
                if (! is_array($ids) || count($ids) > 1000) { throw new RuntimeException('Selección inválida.'); }
                foreach ($ids as $value) {
                    if (! is_string($value) || $value === '') { throw new RuntimeException('Selección inválida.'); }
                }
                $ids = array_values(array_unique($ids));
                if ($key === 'currency_ids' && ! $ids) { throw new RuntimeException('Selecciona al menos una moneda.'); }
                if ($ids && db_connect()->table($table)->where('company_id', $companyId)->whereIn('id', $ids)->countAllResults() !== count($ids)) {
                    throw new RuntimeException('La selección contiene registros ajenos a la empresa.');
                }
                $data[$key] = json_encode($ids, JSON_THROW_ON_ERROR);
            }
            $fields = $input['required_fields'] ?? [];
            if (! is_array($fields) || count($fields) > 30) { throw new RuntimeException('Datos obligatorios inválidos.'); }
            foreach ($fields as $field) {
                if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $field)) {
                    throw new RuntimeException('Los datos obligatorios deben usar identificadores como referencia o fecha_acreditacion.');
                }
            }
            $data['required_fields'] = json_encode(array_values(array_unique($fields)), JSON_THROW_ON_ERROR);
            if ($id) {
                if (! $model->update($id, $data)) { throw new RuntimeException('No se pudo actualizar el medio de pago.'); }
            } else {
                $id = $model->insert($data, true);
                if (! $id) { throw new RuntimeException('No se pudo crear el medio de pago.'); }
            }
            return $model->find($id);
        });
    }

    public function remove(string $companyId, string $id): void
    {
        (new SettingsService())->transaction($companyId, function () use ($companyId, $id) {
            $model = new CompanyPaymentMethodModel();
            if (! $model->where('company_id', $companyId)->find($id)) { throw new RuntimeException('Medio de pago no disponible.'); }
            // Logical deletion preserves the GUID and historical references.
            if (! $model->update($id, ['active' => 0]) || ! $model->delete($id)) { throw new RuntimeException('No se pudo eliminar el medio de pago.'); }
        });
    }
}
