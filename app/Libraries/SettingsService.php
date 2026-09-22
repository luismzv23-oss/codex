<?php

namespace App\Libraries;

use App\Models\{CompanyModel, BranchModel, TaxModel, CurrencyModel, VoucherSequenceModel};
use RuntimeException;

class SettingsService
{
    public function transaction(?string $companyId, callable $operation)
    {
        if (! $companyId) { throw new RuntimeException('Selecciona una empresa.'); }
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $operation) {
            if (! (new CompanyModel())->find($companyId)) { throw new RuntimeException('Empresa no disponible.'); }
            return $operation();
        });
    }

    private function text($value, string $field, int $max, bool $required = false): string
    {
        if (! is_scalar($value) && $value !== null) { throw new RuntimeException('Valor invalido: ' . $field); }
        $value = trim((string) $value);
        if (($required && $value === '') || mb_strlen($value) > $max) { throw new RuntimeException('Valor vacio o demasiado largo: ' . $field); }
        return $value;
    }

    private function number($value, string $field, float $min, float $max, bool $integer = false): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || $value < $min || $value > $max || ($integer && floor((float) $value) != $value)) {
            throw new RuntimeException('Valor numerico invalido: ' . $field);
        }
        return (float) $value;
    }

    private function flag($value): int
    {
        if (! in_array($value, [0, 1, '0', '1', false, true], true)) { throw new RuntimeException('Estado invalido.'); }
        return (int) $value;
    }

    private function unique(string $table, string $companyId, string $code, ?string $except = null): void
    {
        $q = db_connect()->table($table)->where('company_id', $companyId)->where('code', $code);
        if ($except) { $q->where('id !=', $except); }
        if ($q->countAllResults()) { throw new RuntimeException('El codigo ya existe en la empresa.'); }
    }

    public function company(string $companyId, array $input): array
    {
        return $this->transaction($companyId, function () use ($companyId, $input) {
            $changes = [];
            foreach (['name' => 150, 'legal_name' => 180, 'tax_id' => 30, 'email' => 150, 'phone' => 40, 'address' => 10000] as $field => $max) {
                if (array_key_exists($field, $input)) { $changes[$field] = $this->text($input[$field], $field, $max, $field === 'name'); }
            }
            if (! empty($changes['email']) && ! filter_var($changes['email'], FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Correo invalido.'); }
            if (array_key_exists('currency_code', $input)) {
                $code = strtoupper($this->text($input['currency_code'], 'moneda', 10, true));
                $currency = (new CurrencyModel())->where('company_id', $companyId)->where('code', $code)->where('active', 1)->first();
                if (! $currency || (float) $currency['exchange_rate'] <= 0) { throw new RuntimeException('La moneda base debe estar activa y tener cambio positivo.'); }
                $changes['currency_code'] = $code;
                $this->defaultCurrency($companyId, $currency['id']);
            }
            if (array_key_exists('max_cash_registers', $input)) {
                $limit = $this->number($input['max_cash_registers'], 'limite de cajas', 0, 2147483647, true);
                $this->setting($companyId, 'max_cash_registers', (string) (int) $limit);
            }
            $model = new CompanyModel();
            if ($changes && ! $model->update($companyId, $changes)) { throw new RuntimeException('No se pudo actualizar la empresa.'); }
            return $model->find($companyId);
        });
    }

    public function branch(string $companyId, array $input): array
    {
        return $this->transaction($companyId, function () use ($companyId, $input) {
            $data = ['company_id' => $companyId, 'name' => $this->text($input['name'] ?? '', 'nombre', 150, true),
                'code' => $this->text($input['code'] ?? '', 'codigo', 20, true), 'address' => $this->text($input['address'] ?? '', 'direccion', 10000),
                'phone' => $this->text($input['phone'] ?? '', 'telefono', 40), 'active' => $this->flag($input['active'] ?? 1)];
            $this->unique('branches', $companyId, $data['code']);
            $model = new BranchModel(); $id = $model->insert($data, true);
            if (! $id) { throw new RuntimeException('No se pudo guardar la sucursal.'); }
            return $model->find($id);
        });
    }

    public function tax(string $companyId, array $input, ?string $id = null): array
    {
        return $this->transaction($companyId, function () use ($companyId, $input, $id) {
            $model = new TaxModel();
            $old = $id ? $model->where('company_id', $companyId)->find($id) : [];
            if ($id && ! $old) { throw new RuntimeException('Impuesto no disponible.'); }
            $values = array_merge($old, $input);
            $data = ['company_id' => $companyId, 'name' => $this->text($values['name'] ?? '', 'nombre', 120, true),
                'code' => $this->text($values['code'] ?? '', 'codigo', 30, true), 'rate' => $this->number($values['rate'] ?? '', 'tasa', 0, 100),
                'active' => $this->flag($values['active'] ?? 1), 'is_default' => $this->flag($values['is_default'] ?? 0),
                'afip_code' => ($values['afip_code'] ?? '') === '' || ($values['afip_code'] ?? null) === null ? null : (int) $this->number($values['afip_code'], 'codigo AFIP', 0, 65535, true)];
            $this->unique('taxes', $companyId, $data['code'], $id);
            if ($data['is_default'] && ! $data['active']) { throw new RuntimeException('El impuesto predeterminado debe estar activo.'); }
            if (! empty($old['is_default']) && ! $data['is_default']) { throw new RuntimeException('Selecciona otro impuesto predeterminado antes de quitar este.'); }
            if ($data['is_default']) { $model->where('company_id', $companyId)->set(['is_default' => 0])->update(); }
            if ($id) { $ok = $model->update($id, $data); } else { $id = $model->insert($data, true); $ok = $id; }
            if (! $ok) { throw new RuntimeException('No se pudo guardar el impuesto.'); }
            return $model->find($id);
        });
    }

    public function taxAction(string $companyId, string $id, string $action): void
    {
        $this->transaction($companyId, function () use ($companyId, $id, $action) {
            $model = new TaxModel(); $row = $model->where('company_id', $companyId)->find($id);
            if (! $row) { throw new RuntimeException('Impuesto no disponible.'); }
            if ($action === 'default') { $this->tax($companyId, ['is_default' => 1], $id); return; }
            if ($row['is_default']) { throw new RuntimeException('Selecciona otro impuesto predeterminado antes de desactivar o eliminar este.'); }
            $ok = $action === 'delete' ? $model->delete($id) : $model->update($id, ['active' => $row['active'] ? 0 : 1]);
            if (! $ok) { throw new RuntimeException('No se pudo actualizar el impuesto.'); }
        });
    }

    private function defaultCurrency(string $companyId, string $id): void
    {
        (new CurrencyModel())->where('company_id', $companyId)->set(['is_default' => 0])->update();
        (new CurrencyModel())->update($id, ['is_default' => 1]);
    }

    public function currency(string $companyId, array $input): array
    {
        return $this->transaction($companyId, function () use ($companyId, $input) {
            $code = strtoupper($this->text($input['code'] ?? '', 'moneda', 10, true));
            if (! preg_match('/^[A-Z][A-Z0-9]{1,9}$/D', $code)) { throw new RuntimeException('Codigo de moneda invalido.'); }
            $this->unique('currencies', $companyId, $code);
            $company = (new CompanyModel())->find($companyId);
            $default = $this->flag($input['is_default'] ?? 0) || $company['currency_code'] === $code;
            $active = $this->flag($input['active'] ?? 1);
            if ($default && ! $active) { throw new RuntimeException('La moneda base debe estar activa.'); }
            $data = ['company_id' => $companyId, 'code' => $code, 'name' => $this->text($input['name'] ?? '', 'nombre', 80, true),
                'symbol' => $this->text($input['symbol'] ?? '', 'simbolo', 10), 'exchange_rate' => $this->number($input['exchange_rate'] ?? 1, 'tipo de cambio', 0.0001, 99999999.9999),
                'is_default' => (int) $default, 'active' => $active];
            $model = new CurrencyModel(); $id = $model->insert($data, true);
            if (! $id) { throw new RuntimeException('No se pudo guardar la moneda.'); }
            if ($default) { $this->defaultCurrency($companyId, $id); (new CompanyModel())->update($companyId, ['currency_code' => $code]); }
            return $model->find($id);
        });
    }

    public function sequence(string $companyId, array $input): array
    {
        return $this->transaction($companyId, function () use ($companyId, $input) {
            $branch = $this->text($input['branch_id'] ?? '', 'sucursal', 36) ?: null;
            if ($branch && ! (new BranchModel())->where('company_id', $companyId)->where('active', 1)->find($branch)) { throw new RuntimeException('Sucursal no disponible en esta empresa.'); }
            $type = strtoupper($this->text($input['document_type'] ?? '', 'tipo de documento', 60, true));
            if (! preg_match('/^[A-Z0-9_]+$/D', $type)) { throw new RuntimeException('Tipo de documento invalido.'); }
            if ((new VoucherSequenceModel())->where('company_id', $companyId)->where('branch_id', $branch)->where('document_type', $type)->first()) { throw new RuntimeException('Ya existe una numeracion para este documento y sucursal.'); }
            $prefix = strtoupper($this->text($input['prefix'] ?? '', 'prefijo', 20, true));
            if (! preg_match('/^[A-Z0-9_-]+$/D', $prefix)) { throw new RuntimeException('Prefijo invalido.'); }
            // Document numbers are unique per company in downstream modules.
            if ((new VoucherSequenceModel())->where('company_id', $companyId)->where('prefix', $prefix)->first()) { throw new RuntimeException('Usa un prefijo distinto para cada numeracion de la empresa.'); }
            $model = new VoucherSequenceModel();
            $id = $model->insert(['company_id' => $companyId, 'branch_id' => $branch, 'document_type' => $type, 'prefix' => $prefix,
                'current_number' => (int) $this->number($input['current_number'] ?? 1, 'correlativo', 1, 2147483646, true), 'active' => $this->flag($input['active'] ?? 1)], true);
            if (! $id) { throw new RuntimeException('No se pudo guardar la numeracion.'); }
            return $model->find($id);
        });
    }

    private function setting(string $companyId, string $key, string $value): void
    {
        $db = db_connect(); $row = $db->table('company_settings')->where('company_id', $companyId)->where('key', $key)->get()->getRowArray();
        $data = ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')];
        $ok = $row ? $db->table('company_settings')->where('id', $row['id'])->update($data)
            : $db->table('company_settings')->insert(array_merge($data, ['id' => app_uuid(), 'company_id' => $companyId, 'key' => $key, 'created_at' => date('Y-m-d H:i:s')]));
        if (! $ok) { throw new RuntimeException('No se pudo guardar la configuracion.'); }
    }

    public function tickets(string $companyId, array $input, bool $admin): void
    {
        $this->transaction($companyId, function () use ($companyId, $input, $admin) {
            $flags = ['bold_top_left', 'bold_top_right', 'show_sku', 'show_brand', 'show_item_breakdown', 'show_customer', 'show_user'];
            $restricted = ['custom_text_top_left', 'custom_text_top_right', 'custom_text_bottom_left', 'custom_text_bottom_right', 'bold_top_left', 'bold_top_right', 'font_family'];
            $keys = array_merge($flags, ['header_title', 'company_subtitle', 'company_address', 'company_phone', 'footer_notes', 'paper_width', 'font_size', 'font_family', 'custom_text_top_left', 'custom_text_top_right', 'custom_text_bottom_left', 'custom_text_bottom_right']);
            foreach (['ticket_pos_', 'ticket_kiosk_'] as $prefix) {
                foreach ($keys as $key) {
                    $full = $prefix . $key;
                    if (! array_key_exists($full, $input)) { continue; }
                    if (! $admin && in_array($key, $restricted, true)) { throw new RuntimeException('No tienes permiso para modificar los textos personalizados o la fuente.'); }
                    $value = in_array($key, $flags, true) ? (string) $this->flag($input[$full]) : $this->text($input[$full], $key, 4000);
                    $options = ['paper_width' => $prefix === 'ticket_pos_' ? ['A4', 'letter'] : ['58mm', '80mm'],
                        'font_size' => ['small', 'medium', 'large'], 'font_family' => ['DejaVu Sans', 'DejaVu Serif', 'Courier', 'Helvetica', 'Helvetica 75 Bold', 'Times-Roman']];
                    if (isset($options[$key]) && ! in_array($value, $options[$key], true)) { throw new RuntimeException('Opcion de impresion invalida: ' . $key); }
                    $this->setting($companyId, $full, $value);
                }
            }
        });
    }
}
