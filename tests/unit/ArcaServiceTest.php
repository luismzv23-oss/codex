<?php

use App\Libraries\ArcaService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ArcaServiceTest extends CIUnitTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'tests-arca';
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0775, true);
        }
    }

    public function testSanitizeSettingsCreatesDefaultWritableCachePath(): void
    {
        $service = new ArcaService();
        $settings = $service->sanitizeSettings([
            'certificate_path' => '{writable}/certs/cert.pem',
            'private_key_path' => '{writable}/certs/key.pem',
            'token_cache_path' => '',
        ], 'empresa-demo');

        $this->assertStringContainsString('writable', strtolower($settings['certificate_path']));
        $this->assertStringContainsString('writable', strtolower($settings['private_key_path']));
        $this->assertStringContainsString('empresa-demo', $settings['token_cache_path']);
    }

    public function testValidateSettingsRejectsInvalidCuitWhenArcaEnabled(): void
    {
        $service = new ArcaService();
        $validation = $service->validateSettings([
            'arca_enabled' => 1,
            'arca_cuit' => '123',
            'certificate_path' => '',
            'private_key_path' => '',
            'token_cache_path' => $this->tempDir,
        ], 'empresa-demo');

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function testTestAuthenticationWritesTokenCacheWhenReadinessIsComplete(): void
    {
        $service = new ArcaService();

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        if ($key === false) {
            $this->markTestSkipped('No fue posible generar una clave OpenSSL en este entorno.');
        }
        $dn = [
            'countryName' => 'AR',
            'organizationName' => 'Codex',
            'commonName' => 'Codex Test',
        ];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            $this->markTestSkipped('No fue posible generar un CSR OpenSSL en este entorno.');
        }
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if ($cert === false) {
            $this->markTestSkipped('No fue posible firmar un certificado OpenSSL en este entorno.');
        }

        $certPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test-cert.pem';
        $keyPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test-key.pem';
        $cachePath = $this->tempDir . DIRECTORY_SEPARATOR . 'cache';

        openssl_x509_export_to_file($cert, $certPath);
        openssl_pkey_export_to_file($key, $keyPath);

        $result = $service->testAuthentication([
            'arca_enabled' => 1,
            'arca_cuit' => '20123456789',
            'arca_iva_condition' => 'RI',
            'certificate_path' => $certPath,
            'private_key_path' => $keyPath,
            'token_cache_path' => $cachePath,
            'wsaa_enabled' => 1,
            'wsfev1_enabled' => 1,
            'arca_environment' => 'homologacion',
        ]);

        $this->assertSame('ok', $result['status']);
        $this->assertTrue((bool) ($result['token_cache_written'] ?? false));
        $this->assertNotEmpty($result['token_cache_file'] ?? '');
        $this->assertFileExists($result['token_cache_file']);
    }
}
