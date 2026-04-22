<?php

namespace App\Libraries\Arca;

/**
 * WSMTXCA Client — Factura Electrónica con detalle de ítems.
 * Permite informar líneas de producto individuales a AFIP.
 */
class WsmtxcaClient
{
    private const WSDL_HOMO = 'https://fwshomo.afip.gov.ar/wsmtxca/services/MTXCAService?WSDL';
    private const WSDL_PROD = 'https://serviciosjava.afip.gob.ar/wsmtxca/services/MTXCAService?WSDL';

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
     * autorizarComprobante — Autorizar un comprobante con detalle de ítems.
     */
    public function autorizarComprobante(array $comprobante): ArcaResponse
    {
        $request = [
            'authRequest' => $this->authArray(),
            'comprobanteCAERequest' => $this->buildComprobanteRequest($comprobante),
        ];

        try {
            $result = $this->getClient()->autorizarComprobante($request);
            return $this->parseResponse($result, $request);
        } catch (\SoapFault $e) {
            return new ArcaResponse(
                status:         'error',
                resultCode:     'SOAP_ERROR',
                message:        'Error SOAP WSMTXCA: ' . $e->getMessage(),
                errors:         [['code' => $e->getCode(), 'msg' => $e->getMessage()]],
                requestPayload: $request,
                serviceSlug:    'wsmtxca',
                environment:    $this->environment
            );
        }
    }

    /**
     * consultarComprobante — Consultar un comprobante autorizado.
     */
    public function consultarComprobante(int $ptoVenta, int $tipoCbte, int $nro): array
    {
        $result = $this->getClient()->consultarComprobante([
            'authRequest' => $this->authArray(),
            'consultaComprobanteRequest' => [
                'codigoTipoComprobante' => $tipoCbte,
                'numeroPuntoVenta'      => $ptoVenta,
                'numeroComprobante'     => $nro,
            ],
        ]);

        $data = $result->comprobante ?? null;
        return $data ? (json_decode(json_encode($data), true) ?: []) : [];
    }

    /**
     * consultarUltimoComprobanteAutorizado
     */
    public function consultarUltimoComprobanteAutorizado(int $ptoVenta, int $tipoCbte): int
    {
        $result = $this->getClient()->consultarUltimoComprobanteAutorizado([
            'authRequest' => $this->authArray(),
            'consultaUltimoComprobanteAutorizadoRequest' => [
                'codigoTipoComprobante' => $tipoCbte,
                'numeroPuntoVenta'      => $ptoVenta,
            ],
        ]);

        return (int) ($result->numeroComprobante ?? 0);
    }

    // ── Private ──────────────────────────────────────────

    private function buildComprobanteRequest(array $data): array
    {
        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $items[] = [
                'unidadesMtx'           => (int) ($item['unidades'] ?? 1),
                'codigoMtx'             => $item['codigo'] ?? '',
                'codigo'                => $item['codigo'] ?? '',
                'descripcion'           => $item['descripcion'] ?? '',
                'cantidad'              => (float) ($item['cantidad'] ?? 1),
                'codigoUnidadMedida'    => (int) ($item['unidad_medida'] ?? 7), // 7=unidad
                'precioUnitario'        => (float) ($item['precio_unitario'] ?? 0),
                'codigoCondicionIVA'    => (int) ($item['condicion_iva'] ?? 5), // 5=21%
                'importeIVA'            => (float) ($item['importe_iva'] ?? 0),
                'importeItem'           => (float) ($item['importe_item'] ?? 0),
            ];
        }

        $req = [
            'codigoTipoComprobante'  => (int) ($data['cbte_tipo'] ?? 6),
            'numeroPuntoVenta'       => (int) ($data['punto_venta'] ?? 1),
            'numeroComprobante'      => (int) ($data['cbte_nro'] ?? 0),
            'fechaEmision'           => $data['fecha_emision'] ?? date('Y-m-d'),
            'codigoTipoDocumento'    => (int) ($data['doc_tipo'] ?? 80),
            'numeroDocumento'        => (string) ($data['doc_nro'] ?? ''),
            'codigoCondicionIVAReceptor' => (int) ($data['condicion_iva_receptor_id'] ?? 5),
            'importeGravado'         => (float) ($data['imp_neto'] ?? 0),
            'importeNoGravado'       => (float) ($data['imp_tot_conc'] ?? 0),
            'importeExento'          => (float) ($data['imp_op_ex'] ?? 0),
            'importeSubtotal'        => (float) ($data['imp_subtotal'] ?? 0),
            'importeOtrosTributos'   => (float) ($data['imp_trib'] ?? 0),
            'importeTotal'           => (float) ($data['imp_total'] ?? 0),
            'codigoMoneda'           => $data['mon_id'] ?? 'PES',
            'cotizacionMoneda'       => (float) ($data['mon_cotiz'] ?? 1),
            'arrayItems'             => ['item' => $items],
        ];

        // Add IVA subtotals
        if (! empty($data['iva_subtotals'])) {
            $subtotals = [];
            foreach ($data['iva_subtotals'] as $ivaSt) {
                $subtotals[] = [
                    'codigo'    => (int) ($ivaSt['codigo'] ?? 5),
                    'importe'   => (float) ($ivaSt['importe'] ?? 0),
                    'baseImponible' => (float) ($ivaSt['base'] ?? 0),
                ];
            }
            $req['arraySubtotalesIVA'] = ['subtotalIVA' => $subtotals];
        }

        // Service dates
        $concepto = (int) ($data['concepto'] ?? 1);
        if ($concepto >= 2) {
            $req['codigoConcepto']              = $concepto;
            $req['fechaServicioDesde']           = $data['fch_serv_desde'] ?? date('Y-m-d');
            $req['fechaServicioHasta']           = $data['fch_serv_hasta'] ?? date('Y-m-d');
            $req['fechaVencimientoPago']          = $data['fch_vto_pago'] ?? date('Y-m-d', strtotime('+10 days'));
        }

        return $req;
    }

    private function parseResponse($result, array $request): ArcaResponse
    {
        $responseArray = json_decode(json_encode($result ?? new \stdClass()), true) ?: [];

        $errors = [];
        $observations = [];

        if (isset($result->arrayErrores->codigoDescripcion)) {
            $errs = is_array($result->arrayErrores->codigoDescripcion)
                ? $result->arrayErrores->codigoDescripcion
                : [$result->arrayErrores->codigoDescripcion];
            foreach ($errs as $err) {
                $errors[] = ['code' => $err->codigo ?? '', 'msg' => $err->descripcion ?? ''];
            }
        }

        if (isset($result->arrayObservaciones->codigoDescripcion)) {
            $obs = is_array($result->arrayObservaciones->codigoDescripcion)
                ? $result->arrayObservaciones->codigoDescripcion
                : [$result->arrayObservaciones->codigoDescripcion];
            foreach ($obs as $ob) {
                $observations[] = ['code' => $ob->codigo ?? '', 'msg' => $ob->descripcion ?? ''];
            }
        }

        $cae = (string) ($result->comprobanteResponse->CAE ?? '');
        $caeDueDate = (string) ($result->comprobanteResponse->CAEFchVto ?? '');
        $resultado = (string) ($result->comprobanteResponse->resultado ?? '');

        $status = match ($resultado) {
            'A' => 'authorized',
            'R' => 'rejected',
            'O' => 'partial',
            default => ! empty($errors) ? 'error' : 'authorized',
        };

        return new ArcaResponse(
            status:          $status,
            cae:             $cae !== '' ? $cae : null,
            caeDueDate:      $caeDueDate !== '' ? $caeDueDate : null,
            resultCode:      $resultado !== '' ? "MTXCA_{$resultado}" : 'MTXCA_ERR',
            message:         $status === 'authorized' ? "CAE: {$cae}" : ($errors[0]['msg'] ?? 'Error WSMTXCA'),
            observations:    $observations,
            errors:          $errors,
            requestPayload:  $request,
            responsePayload: $responseArray,
            serviceSlug:     'wsmtxca',
            environment:     $this->environment,
            authorizedAt:    $status === 'authorized' ? date('Y-m-d H:i:s') : null
        );
    }

    private function authArray(): array
    {
        return [
            'token'  => $this->ticket->token,
            'sign'   => $this->ticket->sign,
            'cuitRepresentada' => $this->cuit,
        ];
    }

    private function getClient(): \SoapClient
    {
        if (! $this->client) {
            $wsdl = $this->environment === 'produccion' ? self::WSDL_PROD : self::WSDL_HOMO;
            $this->client = new \SoapClient($wsdl, [
                'soap_version'   => \SOAP_1_1,
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
}
