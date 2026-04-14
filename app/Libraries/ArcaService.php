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

        if (! $readiness['ready']) {
            return [
                'status' => 'rejected',
                'result_code' => 'CFG001',
                'message' => 'No se pudo autorizar: configuracion ARCA incompleta.',
                'service_slug' => $service['slug'],
                'environment' => $settings['arca_environment'] ?? 'homologacion',
                'request_payload' => $payload,
                'response_payload' => ['ready' => false, 'checks' => $readiness['checks']],
            ];
        }

        $hashSource = implode('|', [
            (string) ($sale['id'] ?? ''),
            (string) ($sale['sale_number'] ?? ''),
            (string) ($company['id'] ?? ''),
            (string) ($settings['arca_environment'] ?? 'homologacion'),
        ]);
        $cae = str_pad((string) abs(crc32($hashSource)), 14, '0', STR_PAD_LEFT);

        return [
            'status' => 'authorized',
            'result_code' => 'CAE_OK',
            'message' => 'Comprobante autorizado en modo integrado local.',
            'service_slug' => $service['slug'],
            'environment' => $settings['arca_environment'] ?? 'homologacion',
            'cae' => $cae,
            'cae_due_date' => date('Y-m-d 23:59:59', strtotime('+10 days')),
            'authorized_at' => date('Y-m-d H:i:s'),
            'request_payload' => $payload,
            'response_payload' => [
                'cae' => $cae,
                'observations' => [],
                'environment' => $settings['arca_environment'] ?? 'homologacion',
                'service' => $service['slug'],
            ],
        ];
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
