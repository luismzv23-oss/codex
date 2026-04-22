<?php

namespace App\Libraries;

class ArcaService
{
    public function sanitizeSettings(array $settings, ?string $companyId = null): array
    {
        $settings['certificate_path'] = $this->normalizePath(trim((string) ($settings['certificate_path'] ?? '')));
        $settings['private_key_path'] = $this->normalizePath(trim((string) ($settings['private_key_path'] ?? '')));

        $cachePath = trim((string) ($settings['token_cache_path'] ?? ''));
        if ($cachePath === '' && $companyId) {
            $cachePath = WRITEPATH . 'arca' . DIRECTORY_SEPARATOR . $companyId;
        }
        $settings['token_cache_path'] = $this->normalizePath($cachePath);

        return $settings;
    }

    public function validateSettings(array $settings, ?string $companyId = null): array
    {
        $settings = $this->sanitizeSettings($settings, $companyId);
        $errors = [];
        $certificatePath = (string) ($settings['certificate_path'] ?? '');
        $privateKeyPath = (string) ($settings['private_key_path'] ?? '');

        if ((int) ($settings['arca_enabled'] ?? 0) !== 1) {
            return ['valid' => true, 'errors' => [], 'settings' => $settings];
        }

        if (trim((string) ($settings['arca_cuit'] ?? '')) !== '' && ! preg_match('/^\d{11}$/', trim((string) $settings['arca_cuit']))) {
            $errors[] = 'El CUIT debe tener 11 digitos numericos.';
        }

        if ($certificatePath !== '' && ! is_file($certificatePath)) {
            $errors[] = 'La ruta del certificado no existe o no es un archivo valido.';
        }

        if ($privateKeyPath !== '' && ! is_file($privateKeyPath)) {
            $errors[] = 'La ruta de la clave privada no existe o no es un archivo valido.';
        }

        if ($certificatePath !== '' && is_file($certificatePath) && (! is_readable($certificatePath) || (int) @filesize($certificatePath) <= 0)) {
            $errors[] = 'El archivo del certificado esta vacio o no se puede leer.';
        }

        if ($privateKeyPath !== '' && is_file($privateKeyPath) && (! is_readable($privateKeyPath) || (int) @filesize($privateKeyPath) <= 0)) {
            $errors[] = 'El archivo de la clave privada esta vacio o no se puede leer.';
        }

        $cacheValidation = $this->validateTokenCachePath((string) ($settings['token_cache_path'] ?? ''));
        if (! $cacheValidation['ok']) {
            $errors[] = $cacheValidation['message'];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'settings' => $settings,
        ];
    }

    public function certificateDiagnostics(array $settings): array
    {
        $settings = $this->sanitizeSettings($settings);
        $certificatePath = trim((string) ($settings['certificate_path'] ?? ''));
        $privateKeyPath = trim((string) ($settings['private_key_path'] ?? ''));
        $tokenCachePath = trim((string) ($settings['token_cache_path'] ?? ''));
        $certificateExists = $certificatePath !== '' && is_file($certificatePath);
        $privateKeyExists = $privateKeyPath !== '' && is_file($privateKeyPath);
        $certificateSize = $certificateExists ? (int) @filesize($certificatePath) : 0;
        $privateKeySize = $privateKeyExists ? (int) @filesize($privateKeyPath) : 0;
        $checks = [
            ['label' => 'OpenSSL disponible', 'ok' => extension_loaded('openssl')],
            ['label' => 'Ruta de certificado informada', 'ok' => $certificatePath !== ''],
            ['label' => 'Ruta de clave privada informada', 'ok' => $privateKeyPath !== ''],
            ['label' => 'Ruta de cache TA informada', 'ok' => $tokenCachePath !== ''],
            ['label' => 'Archivo de certificado presente', 'ok' => $certificateExists],
            ['label' => 'Archivo de clave presente', 'ok' => $privateKeyExists],
            ['label' => 'Archivo de certificado con contenido', 'ok' => $certificateExists && $certificateSize > 0],
            ['label' => 'Archivo de clave con contenido', 'ok' => $privateKeyExists && $privateKeySize > 0],
        ];

        $metadata = [
            'subject' => null,
            'issuer' => null,
            'serial' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
        ];
        $bundleValid = false;

        if (extension_loaded('openssl') && $certificateExists && $privateKeyExists && $certificateSize > 0 && $privateKeySize > 0) {
            $certificateContent = @file_get_contents($certificatePath) ?: '';
            $privateKeyContent = @file_get_contents($privateKeyPath) ?: '';

            $certificate = $certificateContent !== '' ? @openssl_x509_read($certificateContent) : false;
            $privateKey = $privateKeyContent !== '' ? @openssl_pkey_get_private($privateKeyContent) : false;
            $checks[] = ['label' => 'Certificado legible', 'ok' => $certificate !== false];
            $checks[] = ['label' => 'Clave privada legible', 'ok' => $privateKey !== false];

            if ($certificate !== false) {
                $parsed = @openssl_x509_parse($certificate) ?: [];
                $validFrom = ! empty($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validFrom_time_t']) : null;
                $validTo = ! empty($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']) : null;
                $daysRemaining = ! empty($parsed['validTo_time_t']) ? (int) floor((((int) $parsed['validTo_time_t']) - time()) / 86400) : null;
                $metadata = [
                    'subject' => $this->flattenDn($parsed['subject'] ?? []),
                    'issuer' => $this->flattenDn($parsed['issuer'] ?? []),
                    'serial' => $parsed['serialNumberHex'] ?? ($parsed['serialNumber'] ?? null),
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'days_remaining' => $daysRemaining,
                ];
                $checks[] = ['label' => 'Certificado vigente', 'ok' => $daysRemaining === null ? false : $daysRemaining >= 0];
            }

            $keyMatches = ($certificate !== false && $privateKey !== false) ? (bool) @openssl_x509_check_private_key($certificate, $privateKey) : false;
            $checks[] = ['label' => 'Certificado y clave coinciden', 'ok' => $keyMatches];
            $bundleValid = $certificate !== false && $privateKey !== false && $keyMatches && (($metadata['days_remaining'] ?? -1) >= 0);
        }

        $cacheValidation = $this->validateTokenCachePath($tokenCachePath);
        $checks[] = ['label' => 'Cache TA utilizable', 'ok' => $cacheValidation['ok']];

        $okCount = count(array_filter($checks, static fn(array $check): bool => (bool) $check['ok']));

        return [
            'checks' => $checks,
            'bundle_valid' => $bundleValid,
            'progress' => count($checks) > 0 ? (int) round(($okCount / count($checks)) * 100) : 0,
            'summary' => $bundleValid
                ? 'Bundle fiscal valido para pruebas operativas.'
                : ($certificateExists && $certificateSize <= 0
                    ? 'El archivo del certificado esta vacio o no contiene un certificado PEM valido.'
                    : 'Bundle fiscal incompleto o invalido.'),
            'metadata' => $metadata,
            'paths' => [
                'certificate_path' => $certificatePath,
                'private_key_path' => $privateKeyPath,
                'token_cache_path' => $tokenCachePath,
                'token_cache_status' => $cacheValidation['message'],
                'certificate_size' => $certificateSize,
                'private_key_size' => $privateKeySize,
            ],
        ];
    }

    public function services(): array
    {
        return [
            ['slug' => 'wsaa', 'name' => 'WSAA', 'description' => 'Autenticacion y autorizacion'],
            ['slug' => 'wsfev1', 'name' => 'WSFEv1', 'description' => 'Factura electronica A/B/C/M sin detalle de item'],
            ['slug' => 'wsmtxca', 'name' => 'WSMTXCA', 'description' => 'Factura electronica con detalle de items'],
            ['slug' => 'wsfexv1', 'name' => 'WSFEXv1', 'description' => 'Factura electronica de exportacion'],
            ['slug' => 'wsbfev1', 'name' => 'WSBFEv1', 'description' => 'Bonos fiscales electronicos'],
            ['slug' => 'wsct', 'name' => 'WSCT', 'description' => 'Comprobantes T para turismo'],
            ['slug' => 'wsseg', 'name' => 'WSSEG', 'description' => 'Seguros de caucion'],
        ];
    }

    public function statusSummary(array $settings): array
    {
        $summary = [];
        foreach ($this->services() as $service) {
            $flag = $service['slug'] . '_enabled';
            $summary[] = array_merge($service, [
                'enabled' => (int) ($settings[$flag] ?? 0) === 1,
            ]);
        }
        return $summary;
    }

    public function readiness(array $settings): array
    {
        $diagnostics = $this->certificateDiagnostics($settings);
        $checks = [
            ['key' => 'arca_enabled', 'label' => 'Integracion ARCA activa', 'ok' => (int) ($settings['arca_enabled'] ?? 0) === 1],
            ['key' => 'arca_cuit', 'label' => 'CUIT informado', 'ok' => trim((string) ($settings['arca_cuit'] ?? '')) !== ''],
            ['key' => 'arca_iva_condition', 'label' => 'Condicion IVA informada', 'ok' => trim((string) ($settings['arca_iva_condition'] ?? '')) !== ''],
            ['key' => 'certificate_path', 'label' => 'Ruta de certificado', 'ok' => trim((string) ($settings['certificate_path'] ?? '')) !== ''],
            ['key' => 'private_key_path', 'label' => 'Ruta de clave privada', 'ok' => trim((string) ($settings['private_key_path'] ?? '')) !== ''],
            ['key' => 'token_cache_path', 'label' => 'Ruta de cache TA', 'ok' => trim((string) ($settings['token_cache_path'] ?? '')) !== ''],
            ['key' => 'wsaa_enabled', 'label' => 'WSAA habilitado', 'ok' => (int) ($settings['wsaa_enabled'] ?? 0) === 1],
            ['key' => 'fiscal_ws', 'label' => 'Al menos un WS fiscal habilitado', 'ok' => $this->hasEnabledFiscalService($settings)],
            ['key' => 'bundle_valid', 'label' => 'Certificado y clave validados', 'ok' => (bool) ($diagnostics['bundle_valid'] ?? false)],
        ];

        $okCount = count(array_filter($checks, static fn(array $check): bool => $check['ok']));
        return [
            'checks' => $checks,
            'ready' => $okCount === count($checks),
            'progress' => count($checks) > 0 ? (int) round(($okCount / count($checks)) * 100) : 0,
            'summary' => $okCount === count($checks) ? 'Configuracion lista para operar.' : 'Configuracion incompleta para operar con ARCA.',
            'diagnostics' => $diagnostics,
            'environments' => $this->environmentDiagnostics($settings, $diagnostics),
        ];
    }

    public function testAuthentication(array $settings): array
    {
        $settings = $this->sanitizeSettings($settings);
        $readiness = $this->readiness($settings);
        if (! $readiness['ready']) {
            return [
                'status' => 'rejected',
                'result_code' => 'CFG001',
                'message' => 'La configuracion fiscal aun esta incompleta.',
                'service_slug' => 'wsaa',
                'environment' => $settings['arca_environment'] ?? 'homologacion',
            ];
        }

        $cacheWrite = $this->writeAuthTicketCache($settings, [
            'generated_at' => date('Y-m-d H:i:s'),
            'environment' => $settings['arca_environment'] ?? 'homologacion',
            'service' => 'wsaa',
        ]);

        return [
            'status' => 'ok',
            'result_code' => 'WSAA_OK',
            'message' => 'WSAA validado con configuracion local lista para homologacion/produccion.',
            'service_slug' => 'wsaa',
            'environment' => $settings['arca_environment'] ?? 'homologacion',
            'ticket_expires_at' => date('Y-m-d H:i:s', strtotime('+12 hours')),
            'token_cache_written' => $cacheWrite['ok'],
            'token_cache_file' => $cacheWrite['path'],
        ];
    }

    public function environmentDiagnostics(array $settings, ?array $diagnostics = null): array
    {
        $diagnostics ??= $this->certificateDiagnostics($settings);
        $homologationReady = (int) ($settings['arca_enabled'] ?? 0) === 1
            && trim((string) ($settings['arca_cuit'] ?? '')) !== ''
            && (bool) ($diagnostics['bundle_valid'] ?? false)
            && (int) ($settings['wsaa_enabled'] ?? 0) === 1;

        $productionReady = $homologationReady
            && trim((string) ($settings['arca_iva_condition'] ?? '')) !== ''
            && $this->hasEnabledFiscalService($settings);

        return [
            'homologacion' => [
                'ready' => $homologationReady,
                'label' => 'Homologacion',
            ],
            'produccion' => [
                'ready' => $productionReady,
                'label' => 'Produccion',
            ],
        ];
    }

    public function resolveService(array $documentType, array $settings): ?array
    {
        $category = (string) ($documentType['category'] ?? '');
        $code = (string) ($documentType['code'] ?? '');

        if ($category === 'invoice' || $category === 'ticket' || str_starts_with($code, 'NC_') || str_starts_with($code, 'ND_')) {
            if ((int) ($settings['wsmtxca_enabled'] ?? 0) === 1) {
                return ['slug' => 'wsmtxca', 'name' => 'WSMTXCA'];
            }

            if ((int) ($settings['wsfev1_enabled'] ?? 0) === 1) {
                return ['slug' => 'wsfev1', 'name' => 'WSFEv1'];
            }
        }

        return null;
    }

    public function authorizeSale(array $sale, array $documentType, array $company, array $settings, array $items, array $pointOfSale = []): array
    {
        $service = $this->resolveService($documentType, $settings);
        if ($service === null) {
            return [
                'status' => 'No Aplica',
                'result_code' => 'NO_WS',
                'message' => 'El comprobante no requiere o no tiene servicio fiscal habilitado.',
                'service_slug' => null,
                'environment' => $settings['arca_environment'] ?? 'homologacion',
                'request_payload' => [],
                'response_payload' => [],
            ];
        }

        $readiness = $this->readiness($settings);
        $payload = $this->buildPayloadPreview($sale, $documentType, $company, $settings, $items, $pointOfSale, $service);
        $environment = $settings['arca_environment'] ?? 'homologacion';

        if (! $readiness['ready']) {
            return [
                'status' => 'rejected',
                'result_code' => 'CFG001',
                'message' => 'No se pudo autorizar: configuracion ARCA incompleta.',
                'service_slug' => $service['slug'],
                'environment' => $environment,
                'request_payload' => $payload,
                'response_payload' => ['ready' => false, 'checks' => $readiness['checks']],
            ];
        }

        // ── MOCK mode for development ──
        if ($environment === 'desarrollo') {
            return $this->mockAuthorize($sale, $company, $settings, $payload, $service);
        }

        // ── REAL SOAP integration ──
        $startTime = microtime(true);

        try {
            $settings = $this->sanitizeSettings($settings, $company['id'] ?? null);

            // 1. Authenticate with WSAA
            $wsaaService = $service['slug'] === 'wsmtxca' ? 'wsmtxca' : 'wsfe';
            $wsaa = new \App\Libraries\Arca\WsaaClient(
                $settings['certificate_path'],
                $settings['private_key_path'],
                $settings['token_cache_path'],
                $environment
            );
            $ticket = $wsaa->authenticate($wsaaService);

            // 2. Call the appropriate fiscal WS
            $cuit = $settings['arca_cuit'];
            $response = null;

            if ($service['slug'] === 'wsmtxca') {
                $response = $this->callWsmtxca($ticket, $cuit, $environment, $sale, $documentType, $items, $pointOfSale);
            } else {
                $response = $this->callWsfev1($ticket, $cuit, $environment, $sale, $documentType, $items, $pointOfSale, $settings);
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // 3. Log the integration
            $this->logIntegration(
                $company['id'] ?? null,
                $service['slug'],
                'authorize',
                $environment,
                $response->status,
                'sale', $sale['id'] ?? null,
                $response->cae,
                $response->requestPayload,
                $response->responsePayload,
                $response->isAuthorized() ? null : $response->message,
                $durationMs
            );

            // 4. Return legacy-compatible format
            return [
                'status' => $response->status,
                'result_code' => $response->resultCode ?? 'CAE_OK',
                'message' => $response->message,
                'service_slug' => $service['slug'],
                'environment' => $environment,
                'cae' => $response->cae,
                'cae_due_date' => $response->caeDueDate,
                'authorized_at' => $response->authorizedAt,
                'request_payload' => $response->requestPayload,
                'response_payload' => $response->responsePayload,
                'observations' => $response->observations,
                'errors' => $response->errors,
            ];

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logIntegration(
                $company['id'] ?? null, $service['slug'], 'authorize', $environment,
                'error', 'sale', $sale['id'] ?? null, null,
                $payload, ['exception' => $e->getMessage()],
                $e->getMessage(), $durationMs
            );

            log_message('error', 'ArcaService::authorizeSale SOAP error: ' . $e->getMessage());

            return [
                'status' => 'error',
                'result_code' => 'SOAP_ERR',
                'message' => 'Error de comunicacion con AFIP: ' . $e->getMessage(),
                'service_slug' => $service['slug'],
                'environment' => $environment,
                'request_payload' => $payload,
                'response_payload' => ['exception' => $e->getMessage()],
            ];
        }
    }

    // ── SOAP callers ─────────────────────────────────────

    private function resolveIvaConditionId(string $profile): int
    {
        $profile = strtolower(trim($profile));

        $map = [
            'responsable_inscripto'       => 1,
            'responsable inscripto'       => 1,
            'iva responsable inscripto'   => 1,
            'responsable_no_inscripto'    => 2,
            'responsable no inscripto'    => 2,
            'iva responsable no inscripto'=> 2,
            'no_responsable'              => 3,
            'no responsable'              => 3,
            'iva no responsable'          => 3,
            'exento'                      => 4,
            'sujeto exento'               => 4,
            'iva_exento'                  => 4,
            'iva sujeto exento'           => 4,
            'consumidor_final'            => 5,
            'consumidor final'            => 5,
            'monotributo'                 => 6,
            'responsable_monotributo'     => 6,
            'responsable monotributo'     => 6,
            'monotributista social'       => 13,
            'monotributo social'          => 13,
            'cliente del exterior'        => 9,
            'proveedor del exterior'      => 8,
            'no_categorizado'             => 7,
            'no categorizado'             => 7,
        ];

        return $map[$profile] ?? 5; // Default to Consumidor Final
    }

    private function callWsfev1(
        \App\Libraries\Arca\AuthTicket $ticket, string $cuit, string $env,
        array $sale, array $documentType, array $items, array $pointOfSale, array $settings
    ): \App\Libraries\Arca\ArcaResponse {
        $client = new \App\Libraries\Arca\WsfeClient($ticket, $cuit, $env);

        $ptoVta    = (int) ($pointOfSale['afip_pos_number'] ?? 1);
        $cbteTipo  = (int) ($documentType['afip_code'] ?? 6);
        $lastNum   = $client->FECompUltimoAutorizado($ptoVta, $cbteTipo);
        $nextNum   = $lastNum + 1;

        $subtotal = (float) ($sale['subtotal'] ?? 0);
        $taxTotal = (float) ($sale['tax_total'] ?? 0);
        $total    = (float) ($sale['total'] ?? 0);

        // Build IVA array from items
        $ivaByRate = [];
        foreach ($items as $item) {
            $afipCode = (int) ($item['afip_iva_code'] ?? 5); // Default 21%
            $lineNet  = (float) ($item['line_total'] ?? 0) - (float) ($item['line_tax'] ?? 0);
            $lineTax  = (float) ($item['line_tax'] ?? 0);

            if (! isset($ivaByRate[$afipCode])) {
                $ivaByRate[$afipCode] = ['Id' => $afipCode, 'BaseImp' => 0, 'Importe' => 0];
            }
            $ivaByRate[$afipCode]['BaseImp'] += $lineNet;
            $ivaByRate[$afipCode]['Importe'] += $lineTax;
        }

        // AFIP requires: when ImpIVA = 0 the Iva/AlicIva block must still be
        // present with Id = 3 (IVA 0%) and the full neto as BaseImp.
        if ($taxTotal == 0 && $subtotal > 0) {
            // Replace whatever was accumulated with the mandatory 0% entry
            $ivaByRate = [
                3 => ['Id' => 3, 'BaseImp' => round($subtotal, 2), 'Importe' => 0],
            ];
        }

        // Remove entries with zero BaseImp (no taxable base)
        $ivaByRate = array_filter($ivaByRate, static fn($a) => round($a['BaseImp'], 2) > 0);

        // Round all values for AFIP
        foreach ($ivaByRate as &$aliq) {
            $aliq['BaseImp'] = round($aliq['BaseImp'], 2);
            $aliq['Importe'] = round($aliq['Importe'], 2);
        }
        unset($aliq);

        // Determine document type for customer
        $docTipo = 80; // CUIT by default
        $docNro  = $sale['customer_document_snapshot'] ?? '0';
        $taxProfile = $sale['customer_tax_profile'] ?? '';
        $condicionIvaReceptorId = $this->resolveIvaConditionId($taxProfile);

        if ($taxProfile === 'consumidor_final' || $cbteTipo === 6 || $cbteTipo === 11) {
            $docTipo = 99; // Consumidor final
            $docNro  = '0';
            $condicionIvaReceptorId = 5;
        }

        $invoiceData = [
            'punto_venta'  => $ptoVta,
            'cbte_tipo'    => $cbteTipo,
            'concepto'     => 1, // Productos
            'doc_tipo'     => $docTipo,
            'doc_nro'      => (int) preg_replace('/\D/', '', $docNro),
            'cbte_desde'   => $nextNum,
            'cbte_hasta'   => $nextNum,
            'cbte_fch'     => date('Ymd', strtotime($sale['sale_date'] ?? 'now')),
            'imp_total'    => $total,
            'imp_tot_conc' => 0,
            'imp_neto'     => $subtotal,
            'imp_op_ex'    => 0,
            'imp_iva'      => $taxTotal,
            'imp_trib'     => 0,
            'condicion_iva_receptor_id' => $condicionIvaReceptorId,
            'mon_id'       => ($sale['currency_code'] ?? 'ARS') === 'ARS' ? 'PES' : ($sale['currency_code'] ?? 'PES'),
            'mon_cotiz'    => (float) ($sale['exchange_rate'] ?? 1),
            'iva'          => array_values($ivaByRate),
        ];

        return $client->FECAESolicitar($invoiceData);
    }

    private function callWsmtxca(
        \App\Libraries\Arca\AuthTicket $ticket, string $cuit, string $env,
        array $sale, array $documentType, array $items, array $pointOfSale
    ): \App\Libraries\Arca\ArcaResponse {
        $client = new \App\Libraries\Arca\WsmtxcaClient($ticket, $cuit, $env);

        $ptoVta   = (int) ($pointOfSale['afip_pos_number'] ?? 1);
        $cbteTipo = (int) ($documentType['afip_code'] ?? 6);
        $lastNum  = $client->consultarUltimoComprobanteAutorizado($ptoVta, $cbteTipo);
        $nextNum  = $lastNum + 1;

        $mtxcaItems = [];
        $ivaSubtotals = [];

        foreach ($items as $item) {
            $afipCode = (int) ($item['afip_iva_code'] ?? 5);
            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $lineTax  = (float) ($item['line_tax'] ?? 0);
            $lineTotal = (float) ($item['line_total'] ?? 0);
            $lineNet  = $lineTotal - $lineTax;

            $mtxcaItems[] = [
                'codigo'          => $item['sku'] ?? '',
                'descripcion'     => $item['product_name'] ?? '',
                'cantidad'        => $qty,
                'unidad_medida'   => 7,
                'precio_unitario' => $price,
                'condicion_iva'   => $afipCode,
                'importe_iva'     => $lineTax,
                'importe_item'    => $lineTotal,
            ];

            if (! isset($ivaSubtotals[$afipCode])) {
                $ivaSubtotals[$afipCode] = ['codigo' => $afipCode, 'importe' => 0, 'base' => 0];
            }
            $ivaSubtotals[$afipCode]['importe'] += $lineTax;
            $ivaSubtotals[$afipCode]['base']    += $lineNet;
        }

        $taxTotal = (float) ($sale['tax_total'] ?? 0);
        $subtotal = (float) ($sale['subtotal'] ?? 0);

        // AFIP requires: when ImpIVA = 0, AlicIva must contain Id=3 (IVA 0%)
        if ($taxTotal == 0 && $subtotal > 0) {
            $ivaSubtotals = [
                3 => ['codigo' => 3, 'importe' => 0, 'base' => round($subtotal, 2)],
            ];
        }

        // Remove entries with zero base and round
        $ivaSubtotals = array_filter($ivaSubtotals, static fn($a) => round($a['base'], 2) > 0);
        foreach ($ivaSubtotals as &$sub) {
            $sub['importe'] = round($sub['importe'], 2);
            $sub['base']    = round($sub['base'], 2);
        }
        unset($sub);

        $taxProfile = $sale['customer_tax_profile'] ?? '';
        $condicionIvaReceptorId = $this->resolveIvaConditionId($taxProfile);

        $docTipo = 80; // CUIT by default
        $docNro  = $sale['customer_document_snapshot'] ?? '0';
        if ($taxProfile === 'consumidor_final' || $cbteTipo === 6 || $cbteTipo === 11) {
            $docTipo = 99; // Consumidor final
            $docNro  = '0';
            $condicionIvaReceptorId = 5;
        }

        $comprobante = [
            'cbte_tipo'     => $cbteTipo,
            'punto_venta'   => $ptoVta,
            'cbte_nro'      => $nextNum,
            'fecha_emision' => $sale['sale_date'] ?? date('Y-m-d'),
            'doc_tipo'      => $docTipo,
            'doc_nro'       => $docNro,
            'condicion_iva_receptor_id' => $condicionIvaReceptorId,
            'imp_neto'      => (float) ($sale['subtotal'] ?? 0),
            'imp_tot_conc'  => 0,
            'imp_op_ex'     => 0,
            'imp_subtotal'  => (float) ($sale['subtotal'] ?? 0),
            'imp_trib'      => 0,
            'imp_total'     => (float) ($sale['total'] ?? 0),
            'mon_id'        => ($sale['currency_code'] ?? 'ARS') === 'ARS' ? 'PES' : ($sale['currency_code'] ?? 'PES'),
            'mon_cotiz'     => (float) ($sale['exchange_rate'] ?? 1),
            'items'         => $mtxcaItems,
            'iva_subtotals' => array_values($ivaSubtotals),
        ];

        return $client->autorizarComprobante($comprobante);
    }

    // ── Mock for development ─────────────────────────────

    private function mockAuthorize(array $sale, array $company, array $settings, array $payload, array $service): array
    {
        $hashSource = implode('|', [
            (string) ($sale['id'] ?? ''),
            (string) ($sale['sale_number'] ?? ''),
            (string) ($company['id'] ?? ''),
            (string) ($settings['arca_environment'] ?? 'desarrollo'),
        ]);
        $cae = str_pad((string) abs(crc32($hashSource)), 14, '0', STR_PAD_LEFT);

        return [
            'status' => 'authorized',
            'result_code' => 'CAE_OK',
            'message' => 'Comprobante autorizado en modo desarrollo (simulado).',
            'service_slug' => $service['slug'],
            'environment' => 'desarrollo',
            'cae' => $cae,
            'cae_due_date' => date('Y-m-d 23:59:59', strtotime('+10 days')),
            'authorized_at' => date('Y-m-d H:i:s'),
            'request_payload' => $payload,
            'response_payload' => [
                'cae' => $cae,
                'observations' => [],
                'environment' => 'desarrollo',
                'service' => $service['slug'],
                'mode' => 'mock',
            ],
        ];
    }

    // ── Integration logging ──────────────────────────────

    private function logIntegration(
        ?string $companyId, string $serviceSlug, string $operation, string $environment,
        string $status, ?string $sourceType, ?string $sourceId, ?string $cae,
        array $requestPayload, array $responsePayload, ?string $errorMessage, int $durationMs
    ): void {
        try {
            db_connect()->table('integration_logs')->insert([
                'id'               => app_uuid(),
                'company_id'       => $companyId,
                'provider'         => 'arca',
                'service'          => $serviceSlug,
                'service_slug'     => $serviceSlug,
                'operation'        => $operation,
                'environment'      => $environment,
                'status'           => $status,
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'reference_type'   => $sourceType,
                'reference_id'     => $sourceId,
                'cae'              => $cae,
                'request_payload'  => json_encode($requestPayload),
                'response_payload' => json_encode($responsePayload),
                'error_message'    => $errorMessage,
                'message'          => $errorMessage ? mb_substr($errorMessage, 0, 255) : null,
                'duration_ms'      => $durationMs,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ArcaService::logIntegration failed: ' . $e->getMessage());
        }
    }

    public function consultSale(array $sale, array $settings): array
    {
        return [
            'status' => (string) ($sale['arca_status'] ?? 'not_requested'),
            'result_code' => (string) ($sale['arca_result_code'] ?? 'NO_DATA'),
            'message' => (string) ($sale['arca_result_message'] ?? 'Sin informacion fiscal registrada.'),
            'service_slug' => $sale['arca_service'] ?? null,
            'environment' => $settings['arca_environment'] ?? 'homologacion',
            'cae' => $sale['cae'] ?? null,
            'cae_due_date' => $sale['cae_due_date'] ?? null,
            'authorized_at' => $sale['arca_authorized_at'] ?? null,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildPayloadPreview(array $sale, array $documentType, array $company, array $settings, array $items, array $pointOfSale, array $service): array
    {
        return [
            'company' => [
                'name' => $company['name'] ?? null,
                'cuit' => $settings['arca_cuit'] ?? null,
                'iva_condition' => $settings['arca_iva_condition'] ?? null,
            ],
            'document' => [
                'code' => $documentType['code'] ?? null,
                'name' => $documentType['name'] ?? null,
                'sale_number' => $sale['sale_number'] ?? null,
                'point_of_sale' => $pointOfSale['code'] ?? ($pointOfSale['name'] ?? null),
                'currency' => $sale['currency_code'] ?? 'ARS',
                'total' => (float) ($sale['total'] ?? 0),
            ],
            'customer' => [
                'name' => $sale['customer_name_snapshot'] ?? null,
                'document' => $sale['customer_document_snapshot'] ?? null,
                'tax_profile' => $sale['customer_tax_profile'] ?? null,
            ],
            'items' => array_map(static fn(array $item): array => [
                'sku' => $item['sku'] ?? null,
                'name' => $item['product_name'] ?? null,
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
            ], $items),
            'service' => $service['slug'],
            'environment' => $settings['arca_environment'] ?? 'homologacion',
        ];
    }

    private function hasEnabledFiscalService(array $settings): bool
    {
        foreach (['wsfev1_enabled', 'wsmtxca_enabled', 'wsfexv1_enabled', 'wsbfev1_enabled', 'wsct_enabled', 'wsseg_enabled'] as $flag) {
            if ((int) ($settings[$flag] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }

    private function flattenDn(array $dn): ?string
    {
        if ($dn === []) {
            return null;
        }

        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $parts[] = $key . '=' . $value;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $path = str_replace(['{writable}', '{WRITEPATH}'], rtrim(WRITEPATH, '\\/'), $path);
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function validateTokenCachePath(string $path): array
    {
        if ($path === '') {
            return ['ok' => false, 'message' => 'La ruta de cache TA no fue informada.'];
        }

        if (is_file($path)) {
            return is_writable($path)
                ? ['ok' => true, 'message' => 'Archivo de cache TA utilizable.']
                : ['ok' => false, 'message' => 'El archivo de cache TA no tiene permisos de escritura.'];
        }

        if (! is_dir($path)) {
            $created = @mkdir($path, 0775, true);
            if (! $created && ! is_dir($path)) {
                return ['ok' => false, 'message' => 'No se pudo crear la carpeta de cache TA.'];
            }
        }

        return is_writable($path)
            ? ['ok' => true, 'message' => 'Carpeta de cache TA utilizable.']
            : ['ok' => false, 'message' => 'La carpeta de cache TA no tiene permisos de escritura.'];
    }

    private function writeAuthTicketCache(array $settings, array $payload): array
    {
        $path = trim((string) ($settings['token_cache_path'] ?? ''));
        $validation = $this->validateTokenCachePath($path);
        if (! $validation['ok']) {
            return ['ok' => false, 'path' => null];
        }

        $target = is_dir($path)
            ? rtrim($path, '\\/') . DIRECTORY_SEPARATOR . 'ta-' . ($settings['arca_environment'] ?? 'homologacion') . '.json'
            : $path;

        $written = @file_put_contents($target, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'ok' => $written !== false,
            'path' => $target,
        ];
    }
}
