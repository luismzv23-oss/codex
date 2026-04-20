<?php

namespace App\Libraries\Arca;

/**
 * WSFEv1 Client — Factura Electrónica sin detalle de ítems.
 * Cubre comprobantes tipo A, B, C, M (facturas, NC, ND).
 */
class WsfeClient
{
    private const WSDL_HOMO = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    private const WSDL_PROD = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';

    private AuthTicket $ticket;
    private string $cuit;
    private string $environment;
    private ?\SoapClient $client = null;

    public function __construct(AuthTicket $ticket, string $cuit, string $environment = 'homologacion')
    {
        $this->ticket      = $ticket;
        $this->cuit        = $cuit;
        $this->environment = $environment;
    }

    /**
     * FECAESolicitar — Solicitar CAE para un comprobante.
     */
    public function FECAESolicitar(array $invoiceData): ArcaResponse
    {
        $request = [
            'Auth' => $this->authArray(),
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg'    => 1,
                    'PtoVta'     => (int) ($invoiceData['punto_venta'] ?? 1),
                    'CbteTipo'   => (int) ($invoiceData['cbte_tipo'] ?? 6), // 6=B, 1=A, 6=B, 11=C
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto'    => (int) ($invoiceData['concepto'] ?? 1),        // 1=Productos, 2=Servicios, 3=Ambos
                        'DocTipo'     => (int) ($invoiceData['doc_tipo'] ?? 80),        // 80=CUIT, 96=DNI, 99=Consumidor Final
                        'DocNro'      => (int) ($invoiceData['doc_nro'] ?? 0),
                        'CbteDesde'   => (int) ($invoiceData['cbte_desde'] ?? 0),
                        'CbteHasta'   => (int) ($invoiceData['cbte_hasta'] ?? 0),
                        'CbteFch'     => $invoiceData['cbte_fch'] ?? date('Ymd'),
                        'ImpTotal'    => (float) ($invoiceData['imp_total'] ?? 0),
                        'ImpTotConc'  => (float) ($invoiceData['imp_tot_conc'] ?? 0),   // No gravado
                        'ImpNeto'     => (float) ($invoiceData['imp_neto'] ?? 0),       // Neto gravado
                        'ImpOpEx'     => (float) ($invoiceData['imp_op_ex'] ?? 0),      // Exento
                        'ImpIVA'      => (float) ($invoiceData['imp_iva'] ?? 0),        // IVA total
                        'ImpTrib'     => (float) ($invoiceData['imp_trib'] ?? 0),       // Otros tributos
                        'MonId'       => $invoiceData['mon_id'] ?? 'PES',
                        'MonCotiz'    => (float) ($invoiceData['mon_cotiz'] ?? 1),
                    ],
                ],
            ],
        ];

        // Add IVA breakdown if present
        if (! empty($invoiceData['iva'])) {
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['Iva'] = [
                'AlicIva' => $invoiceData['iva'],
            ];
        }

        // Add optional tributos
        if (! empty($invoiceData['tributos'])) {
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['Tributos'] = [
                'Tributo' => $invoiceData['tributos'],
            ];
        }

        // Add CbtesAsoc (associated invoices for NC/ND)
        if (! empty($invoiceData['cbtes_asoc'])) {
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['CbtesAsoc'] = [
                'CbteAsoc' => $invoiceData['cbtes_asoc'],
            ];
        }

        // Add service dates (required for Concepto 2 or 3)
        $concepto = (int) ($invoiceData['concepto'] ?? 1);
        if ($concepto >= 2) {
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchServDesde'] = $invoiceData['fch_serv_desde'] ?? date('Ymd');
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchServHasta'] = $invoiceData['fch_serv_hasta'] ?? date('Ymd');
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchVtoPago']   = $invoiceData['fch_vto_pago'] ?? date('Ymd', strtotime('+10 days'));
        }

        try {
            $result = $this->getClient()->FECAESolicitar($request);
            return $this->parseCAEResponse($result, $request);
        } catch (\SoapFault $e) {
            return new ArcaResponse(
                status:          'error',
                resultCode:      'SOAP_ERROR',
                message:         'Error SOAP WSFEv1: ' . $e->getMessage(),
                errors:          [['code' => $e->getCode(), 'msg' => $e->getMessage()]],
                requestPayload:  $request,
                serviceSlug:     'wsfev1',
                environment:     $this->environment
            );
        }
    }

    /**
     * FECompUltimoAutorizado — Obtener último número de comprobante autorizado.
     */
    public function FECompUltimoAutorizado(int $puntoVenta, int $tipoComprobante): int
    {
        $result = $this->getClient()->FECompUltimoAutorizado([
            'Auth'    => $this->authArray(),
            'PtoVta'  => $puntoVenta,
            'CbteTipo' => $tipoComprobante,
        ]);

        return (int) ($result->FECompUltimoAutorizadoResult->CbteNro ?? 0);
    }

    /**
     * FECompConsultar — Consultar un comprobante autorizado.
     */
    public function FECompConsultar(int $puntoVenta, int $tipoComprobante, int $numero): array
    {
        $result = $this->getClient()->FECompConsultar([
            'Auth' => $this->authArray(),
            'FeCompConsReq' => [
                'CbteTipo' => $tipoComprobante,
                'CbteNro'  => $numero,
                'PtoVta'   => $puntoVenta,
            ],
        ]);

        $data = $result->FECompConsultarResult->ResultGet ?? null;

        if (! $data) {
            return [];
        }

        return json_decode(json_encode($data), true) ?: [];
    }

    /**
     * FEParamGetTiposCbte — Obtener tipos de comprobante.
     */
    public function FEParamGetTiposCbte(): array
    {
        $result = $this->getClient()->FEParamGetTiposCbte(['Auth' => $this->authArray()]);
        $items = $result->FEParamGetTiposCbteResult->ResultGet->CbteTipo ?? [];
        return $this->toArray($items);
    }

    /**
     * FEParamGetTiposIva — Obtener alícuotas de IVA.
     */
    public function FEParamGetTiposIva(): array
    {
        $result = $this->getClient()->FEParamGetTiposIva(['Auth' => $this->authArray()]);
        $items = $result->FEParamGetTiposIvaResult->ResultGet->IvaTipo ?? [];
        return $this->toArray($items);
    }

    /**
     * FEParamGetTiposDoc — Obtener tipos de documento.
     */
    public function FEParamGetTiposDoc(): array
    {
        $result = $this->getClient()->FEParamGetTiposDoc(['Auth' => $this->authArray()]);
        $items = $result->FEParamGetTiposDocResult->ResultGet->DocTipo ?? [];
        return $this->toArray($items);
    }

    /**
     * FEParamGetPtosVenta — Obtener puntos de venta habilitados.
     */
    public function FEParamGetPtosVenta(): array
    {
        $result = $this->getClient()->FEParamGetPtosVenta(['Auth' => $this->authArray()]);
        $items = $result->FEParamGetPtosVentaResult->ResultGet->PtoVenta ?? [];
        return $this->toArray($items);
    }

    // ── Private ──────────────────────────────────────────

    private function parseCAEResponse($result, array $request): ArcaResponse
    {
        $det = $result->FECAESolicitarResult->FeDetResp->FECAEDetResponse ?? null;
        $responseArray = json_decode(json_encode($result->FECAESolicitarResult ?? new \stdClass()), true) ?: [];

        // Collect errors
        $errors = [];
        if (isset($result->FECAESolicitarResult->Errors->Err)) {
            $errs = $result->FECAESolicitarResult->Errors->Err;
            $errs = is_array($errs) ? $errs : [$errs];
            foreach ($errs as $err) {
                $errors[] = ['code' => $err->Code ?? '', 'msg' => $err->Msg ?? ''];
            }
        }

        // Collect observations
        $observations = [];
        if ($det && isset($det->Observaciones->Obs)) {
            $obs = $det->Observaciones->Obs;
            $obs = is_array($obs) ? $obs : [$obs];
            foreach ($obs as $ob) {
                $observations[] = ['code' => $ob->Code ?? '', 'msg' => $ob->Msg ?? ''];
            }
        }

        if (! $det) {
            return new ArcaResponse(
                status:          'error',
                resultCode:      'NO_RESPONSE',
                message:         'AFIP no devolvió detalle de respuesta. ' . ($errors[0]['msg'] ?? ''),
                errors:          $errors,
                observations:    $observations,
                requestPayload:  $request,
                responsePayload: $responseArray,
                serviceSlug:     'wsfev1',
                environment:     $this->environment
            );
        }

        $resultado = (string) ($det->Resultado ?? '');
        $cae = (string) ($det->CAE ?? '');
        $caeDueDate = (string) ($det->CAEFchVto ?? '');

        if ($caeDueDate !== '' && strlen($caeDueDate) === 8) {
            $caeDueDate = substr($caeDueDate, 0, 4) . '-' . substr($caeDueDate, 4, 2) . '-' . substr($caeDueDate, 6, 2);
        }

        $status = match ($resultado) {
            'A' => 'authorized',
            'R' => 'rejected',
            'P' => 'partial',
            default => 'error',
        };

        $message = match ($status) {
            'authorized' => "Comprobante autorizado. CAE: {$cae}",
            'rejected'   => 'Comprobante rechazado por AFIP. ' . ($observations[0]['msg'] ?? ''),
            'partial'    => 'Comprobante aprobado con observaciones.',
            default      => 'Error desconocido en la respuesta.',
        };

        return new ArcaResponse(
            status:          $status,
            cae:             $cae !== '' ? $cae : null,
            caeDueDate:      $caeDueDate !== '' ? $caeDueDate : null,
            resultCode:      $resultado !== '' ? "WSFE_{$resultado}" : 'WSFE_ERR',
            message:         $message,
            observations:    $observations,
            errors:          $errors,
            requestPayload:  $request,
            responsePayload: $responseArray,
            serviceSlug:     'wsfev1',
            environment:     $this->environment,
            authorizedAt:    $status === 'authorized' ? date('Y-m-d H:i:s') : null
        );
    }

    private function authArray(): array
    {
        return [
            'Token' => $this->ticket->token,
            'Sign'  => $this->ticket->sign,
            'Cuit'  => $this->cuit,
        ];
    }

    private function getClient(): \SoapClient
    {
        if (! $this->client) {
            $wsdl = $this->environment === 'produccion' ? self::WSDL_PROD : self::WSDL_HOMO;

            $this->client = new \SoapClient($wsdl, [
                'soap_version'   => \SOAP_1_2,
                'trace'          => true,
                'exceptions'     => true,
                'cache_wsdl'     => \WSDL_CACHE_BOTH,
                'stream_context' => stream_context_create([
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]),
            ]);
        }

        return $this->client;
    }

    private function toArray($items): array
    {
        if (! $items) {
            return [];
        }

        $items = is_array($items) ? $items : [$items];
        return array_map(static fn($item) => json_decode(json_encode($item), true) ?: [], $items);
    }
}
