<?php

namespace App\Libraries\Arca;

/**
 * WSAA Client — Authenticates against AFIP's Web Service de Autenticación y Autorización.
 * Generates Login Ticket Requests (TRA), signs with PKCS#7, and obtains Token + Sign.
 */
class WsaaClient
{
    private const WSDL_HOMOLOGACION = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL';
    private const WSDL_PRODUCCION   = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL';
    private const TICKET_TTL        = 43200; // 12 hours in seconds

    private string $certPath;
    private string $keyPath;
    private string $cachePath;
    private string $environment;

    public function __construct(string $certPath, string $keyPath, string $cachePath, string $environment = 'homologacion')
    {
        $this->certPath    = $certPath;
        $this->keyPath     = $keyPath;
        $this->cachePath   = $cachePath;
        $this->environment = $environment;
    }

    /**
     * Authenticate and obtain a ticket for the given service.
     * Returns cached ticket if still valid.
     */
    public function authenticate(string $service = 'wsfe'): AuthTicket
    {
        // Try cached ticket first
        $cached = $this->getCachedTicket($service);

        if ($cached && $this->isTicketValid($cached)) {
            return $cached;
        }

        // Generate new ticket
        return $this->requestNewTicket($service);
    }

    /**
     * Retrieve a cached ticket for a service.
     */
    public function getCachedTicket(string $service): ?AuthTicket
    {
        $cacheFile = $this->getCacheFile($service);

        if (! is_file($cacheFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($cacheFile), true);

        if (! is_array($data) || empty($data['token'])) {
            return null;
        }

        return new AuthTicket(
            token:       $data['token'],
            sign:        $data['sign'],
            service:     $data['service'] ?? $service,
            expiresAt:   new \DateTime($data['expires_at'] ?? 'now'),
            environment: $data['environment'] ?? $this->environment
        );
    }

    public function isTicketValid(AuthTicket $ticket): bool
    {
        return $ticket->expiresAt > new \DateTime();
    }

    // ── Private: SOAP request ────────────────────────────

    private function requestNewTicket(string $service): AuthTicket
    {
        $tra = $this->buildTra($service);
        $cms = $this->signTra($tra);

        $wsdl = $this->environment === 'produccion' ? self::WSDL_PRODUCCION : self::WSDL_HOMOLOGACION;

        try {
            $client = new \SoapClient($wsdl, [
                'soap_version'   => \SOAP_1_2,
                'trace'          => true,
                'exceptions'     => true,
                'stream_context' => stream_context_create([
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]),
            ]);

            $response = $client->loginCms(['in0' => $cms]);
            $loginResponse = $response->loginCmsReturn ?? '';

        } catch (\SoapFault $e) {
            $msg = $e->getMessage();

            // Translate known AFIP errors to actionable messages
            if (str_contains($msg, 'Zero length BigInteger')) {
                throw new \RuntimeException(
                    'WSAA: El certificado no esta asociado al CUIT en AFIP. '
                    . 'Ingresa a https://auth.afip.gob.ar → Administracion de Certificados Digitales → '
                    . 'Asociar certificado al servicio (wsfe/wsmtxca). Error original: ' . $msg,
                    0, $e
                );
            }

            if (str_contains($msg, 'ns1:cms.sign.invalid')) {
                throw new \RuntimeException(
                    'WSAA: La firma CMS es invalida. Verifica que el certificado (.crt) y la clave privada (.key) '
                    . 'coincidan y sean los que generaste con el CSR enviado a AFIP. Error: ' . $msg,
                    0, $e
                );
            }

            throw new \RuntimeException('WSAA SOAP error: ' . $msg, (int) $e->getCode(), $e);
        }

        // Parse response XML
        $xml = new \SimpleXMLElement((string) $loginResponse);
        $token = (string) ($xml->credentials->token ?? '');
        $sign  = (string) ($xml->credentials->sign ?? '');

        if ($token === '' || $sign === '') {
            throw new \RuntimeException('WSAA returned empty credentials. Response: ' . $loginResponse);
        }

        $expirationStr = (string) ($xml->header->expirationTime ?? '');
        $expiresAt = $expirationStr !== ''
            ? new \DateTime($expirationStr)
            : new \DateTime('+12 hours');

        $ticket = new AuthTicket(
            token:       $token,
            sign:        $sign,
            service:     $service,
            expiresAt:   $expiresAt,
            environment: $this->environment
        );

        // Cache the ticket
        $this->cacheTicket($ticket);

        return $ticket;
    }

    /**
     * Build the Login Ticket Request (TRA) XML.
     */
    private function buildTra(string $service): string
    {
        $uniqueId  = (string) time();
        $generTime = date('c', time() - 60);
        $expTime   = date('c', time() + self::TICKET_TTL);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$generTime}</generationTime>
        <expirationTime>{$expTime}</expirationTime>
    </header>
    <service>{$service}</service>
</loginTicketRequest>
XML;
    }

    /**
     * Sign the TRA with the certificate and key using PKCS#7 (CMS).
     * Uses file:// protocol for cert/key — the standard approach for AFIP integration.
     */
    private function signTra(string $tra): string
    {
        $tmpTra  = tempnam(sys_get_temp_dir(), 'tra_');
        $tmpCms  = tempnam(sys_get_temp_dir(), 'cms_');

        file_put_contents($tmpTra, $tra);

        $certRealPath = realpath($this->certPath);
        $keyRealPath  = realpath($this->keyPath);

        if (! $certRealPath || ! $keyRealPath) {
            @unlink($tmpTra);
            @unlink($tmpCms);
            throw new \RuntimeException(
                'Cannot find certificate or key file. Cert: ' . $this->certPath . ' Key: ' . $this->keyPath
            );
        }

        $signed = openssl_pkcs7_sign(
            $tmpTra,
            $tmpCms,
            'file://' . $certRealPath,
            ['file://' . $keyRealPath, ''],
            [],
            !defined('PKCS7_BINARY') ? 0x80 : \PKCS7_BINARY
        );

        if (! $signed) {
            $error = '';
            while ($msg = openssl_error_string()) {
                $error .= $msg . '; ';
            }
            @unlink($tmpTra);
            @unlink($tmpCms);
            throw new \RuntimeException('PKCS#7 signing failed: ' . $error);
        }

        $cmsContent = (string) file_get_contents($tmpCms);

        @unlink($tmpTra);
        @unlink($tmpCms);

        // The CMS output is in S/MIME format. We need only the base64 body.
        // Remove everything before the first empty line (MIME headers).
        // Then remove any MIME boundary lines (------xxxx--).
        $inf = fopen('php://memory', 'r+');
        fwrite($inf, $cmsContent);
        rewind($inf);

        $inHeader = true;
        $body = '';
        while (($line = fgets($inf)) !== false) {
            // Skip until we pass the empty line separating headers from body
            if ($inHeader) {
                if (trim($line) === '') {
                    $inHeader = false;
                }
                continue;
            }
            // Skip MIME boundary lines
            if (str_starts_with(trim($line), '------')) {
                continue;
            }
            $body .= trim($line);
        }
        fclose($inf);

        if ($body === '') {
            throw new \RuntimeException('CMS signing produced empty body. Raw length: ' . strlen($cmsContent));
        }

        return $body;
    }

    private function cacheTicket(AuthTicket $ticket): void
    {
        $cacheFile = $this->getCacheFile($ticket->service);
        $dir = dirname($cacheFile);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($cacheFile, json_encode([
            'token'       => $ticket->token,
            'sign'        => $ticket->sign,
            'service'     => $ticket->service,
            'expires_at'  => $ticket->expiresAt->format('Y-m-d H:i:s'),
            'environment' => $ticket->environment,
            'cached_at'   => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT));
    }

    private function getCacheFile(string $service): string
    {
        $dir = rtrim($this->cachePath, \DIRECTORY_SEPARATOR);
        return $dir . \DIRECTORY_SEPARATOR . "ta-{$service}-{$this->environment}.json";
    }
}
