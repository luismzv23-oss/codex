<?php

namespace App\Libraries;

/** Extract only printable fields from the actual authorization request, never its credentials. */
final class KioskTicketFiscal
{
    public static function fromResult(array $result): array
    {
        $output = ['cae' => null, 'qrUrl' => null, 'caeDueDate' => null, 'processedAt' => null];
        if (($result['status'] ?? '') !== 'authorized' || ! preg_match('/^\d{14}$/', (string) ($result['cae'] ?? ''))) {
            return $output;
        }
        $output = array_merge($output, ['cae' => $result['cae'], 'caeDueDate' => $result['cae_due_date'] ?? null,
            'processedAt' => $result['authorized_at'] ?? null, 'testEnvironment' => ($result['environment'] ?? '') !== 'produccion']);
        $request = $result['request_payload'] ?? [];
        $head = $request['FeCAEReq']['FeCabReq'] ?? [];
        $detail = $request['FeCAEReq']['FeDetReq']['FECAEDetRequest'] ?? [];
        if (isset($detail[0])) { $detail = $detail[0]; }
        $mtx = $request['comprobanteCAERequest'] ?? [];
        $date = (string) ($detail['CbteFch'] ?? $mtx['fechaEmision'] ?? '');
        if (preg_match('/^\d{8}$/', $date)) { $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2); }
        $payload = [
            'ver' => 1, 'fecha' => $date,
            'cuit' => (int) ($request['Auth']['Cuit'] ?? $request['authRequest']['cuitRepresentada'] ?? 0),
            'ptoVta' => (int) ($head['PtoVta'] ?? $mtx['numeroPuntoVenta'] ?? 0),
            'tipoCmp' => (int) ($head['CbteTipo'] ?? $mtx['codigoTipoComprobante'] ?? 0),
            'nroCmp' => (int) ($detail['CbteDesde'] ?? $mtx['numeroComprobante'] ?? 0),
            'importe' => (float) ($detail['ImpTotal'] ?? $mtx['importeTotal'] ?? 0),
            'moneda' => $detail['MonId'] ?? $mtx['codigoMoneda'] ?? '',
            'ctz' => (float) ($detail['MonCotiz'] ?? $mtx['cotizacionMoneda'] ?? 0),
            'tipoCodAut' => 'E', 'codAut' => (int) $result['cae'],
        ];
        $docType = $detail['DocTipo'] ?? $mtx['codigoTipoDocumento'] ?? null;
        if ($docType !== null) {
            $payload['tipoDocRec'] = (int) $docType;
            $payload['nroDocRec'] = (int) ($detail['DocNro'] ?? $mtx['numeroDocumento'] ?? 0);
        }
        if (isset($detail['ImpIVA'])) { $output['containedVat'] = (float) $detail['ImpIVA']; }
        if ($mtx) {
            $subtotals = $mtx['arraySubtotalesIVA']['subtotalIVA'] ?? [];
            if (isset($subtotals['importe'])) { $subtotals = [$subtotals]; }
            $output['containedVat'] = array_sum(array_column($subtotals, 'importe'));
            if (isset($mtx['importeOtrosTributos']) && (float) $mtx['importeOtrosTributos'] === 0.0) { $output['nationalTaxes'] = 0.0; }
        }
        // Only national taxes (code 1), not provincial/municipal taxes.
        if (isset($detail['ImpTrib'])) {
            $tributes = $detail['Tributos']['Tributo'] ?? [];
            if (isset($tributes['Id'])) { $tributes = [$tributes]; }
            if ((float) $detail['ImpTrib'] === 0.0 || $tributes) {
                $output['nationalTaxes'] = array_reduce($tributes, static fn ($sum, $tax) => $sum + ((int) ($tax['Id'] ?? 0) === 1 ? (float) ($tax['Importe'] ?? 0) : 0), 0.0);
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strlen((string) $payload['cuit']) === 11
            && $payload['ptoVta'] > 0 && $payload['tipoCmp'] > 0 && $payload['nroCmp'] > 0 && $payload['ctz'] > 0 && $payload['moneda'] !== '') {
            $output['qrUrl'] = 'https://www.arca.gob.ar/fe/qr/?p=' . base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
            $output['documentNumber'] = sprintf('%05d-%08d', $payload['ptoVta'], $payload['nroCmp']);
            $output['documentTypeCode'] = str_pad((string)$payload['tipoCmp'], 3, '0', STR_PAD_LEFT);
        }
        return $output;
    }
}
