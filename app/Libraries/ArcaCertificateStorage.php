<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Stores the ARCA certificate and private key uploaded in the company setup.
 * Files live outside the public folder (writable/private/arca/{company}) with random names, are validated
 * as PEM with OpenSSL (and the key must match the certificate). Paths are never returned to the browser.
 */
class ArcaCertificateStorage
{
    public const MAX_BYTES = 65536;

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? WRITEPATH . 'private' . DIRECTORY_SEPARATOR . 'arca', '/\\');
    }

    /** Validates and stores both files; returns absolute paths ['certificate_path' => …, 'private_key_path' => …]. */
    public function store(string $companyId, string $certificateFile, string $keyFile): array
    {
        if (! preg_match('/^[a-zA-Z0-9-]{1,36}$/D', $companyId)) {
            throw new RuntimeException('Empresa no válida para guardar el certificado.');
        }
        if (! function_exists('openssl_x509_read')) {
            throw new RuntimeException('El servidor no tiene OpenSSL habilitado para validar el certificado.');
        }
        $certificate = $this->read($certificateFile, 'certificado');
        $key = $this->read($keyFile, 'clave privada');
        if (! str_contains($certificate, '-----BEGIN CERTIFICATE-----') || @openssl_x509_read($certificate) === false) {
            throw new RuntimeException('El archivo de certificado no es un certificado PEM válido (.crt / .pem).');
        }
        if (! preg_match('/-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/', $key) || @openssl_pkey_get_private($key) === false) {
            throw new RuntimeException('El archivo de clave no es una clave privada PEM válida sin contraseña (.key).');
        }
        if (! @openssl_x509_check_private_key($certificate, $key)) {
            throw new RuntimeException('La clave privada no corresponde al certificado.');
        }
        $directory = $this->root . DIRECTORY_SEPARATOR . $companyId;
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar la carpeta privada del certificado.');
        }
        $suffix = bin2hex(random_bytes(8));
        $paths = ['certificate_path' => $directory . DIRECTORY_SEPARATOR . 'cert-' . $suffix . '.crt', 'private_key_path' => $directory . DIRECTORY_SEPARATOR . 'key-' . $suffix . '.key'];
        foreach (['certificate_path' => $certificate, 'private_key_path' => $key] as $field => $content) {
            if (file_put_contents($paths[$field], $content, LOCK_EX) !== strlen($content)) {
                throw new RuntimeException('No se pudo guardar el ' . ($field === 'certificate_path' ? 'certificado' : 'archivo de clave') . '.');
            }
            @chmod($paths[$field], 0600);
        }

        return $paths;
    }

    private function read(string $path, string $label): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('Falta el archivo de ' . $label . '.');
        }
        if (filesize($path) <= 0 || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('El archivo de ' . $label . ' supera el tamaño admitido o está vacío.');
        }

        return (string) file_get_contents($path);
    }
}
