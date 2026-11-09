<?php
use App\Libraries\ArcaCertificateStorage;
use CodeIgniter\Test\CIUnitTestCase;

final class ArcaCertificateStorageTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('Requiere OpenSSL.');
        }
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arca-storage-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/in', 0700, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    /** Self-signed certificate + key written to the input folder; returns [certPath, keyPath]. */
    private function bundle(string $name): array
    {
        // Windows builds need an explicit openssl.cnf to create test certificates.
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        foreach ([dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf', (string) getenv('OPENSSL_CONF')] as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                $options['config'] = $candidate;
                break;
            }
        }
        $key = @openssl_pkey_new($options);
        $csr = $key ? @openssl_csr_new(['commonName' => 'codex-test', 'serialNumber' => 'CUIT 30719167124'], $key, $options) : false;
        $cert = $csr ? @openssl_csr_sign($csr, null, $key, 30, $options) : false;
        if (! $cert) {
            $this->markTestSkipped('OpenSSL no puede generar certificados de prueba en este entorno.');
        }
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, $options);
        file_put_contents($certPath = $this->dir . "/in/$name.crt", $certPem);
        file_put_contents($keyPath = $this->dir . "/in/$name.key", $keyPem);

        return [$certPath, $keyPath];
    }

    public function testStoresValidBundleInPrivateFolderWithRandomNames(): void
    {
        [$cert, $key] = $this->bundle('ok');
        $storage = new ArcaCertificateStorage($this->dir . '/private');
        $paths = $storage->store('company-1', $cert, $key);
        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $this->assertStringStartsWith(realpath($this->dir . '/private/company-1'), realpath($path));
            $this->assertStringNotContainsString('public', strtolower($path));
        }
        $this->assertMatchesRegularExpression('/cert-[0-9a-f]{16}\.crt$/', $paths['certificate_path']);
        $this->assertSame(file_get_contents($cert), file_get_contents($paths['certificate_path']));
    }

    public function testRejectsMismatchedKeyNonPemAndOversizedFiles(): void
    {
        [$cert] = $this->bundle('a');
        [, $otherKey] = $this->bundle('b');
        $storage = new ArcaCertificateStorage($this->dir . '/private');
        try {
            $storage->store('company-1', $cert, $otherKey);
            $this->fail('A key from another certificate must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no corresponde', $e->getMessage());
        }
        file_put_contents($text = $this->dir . '/in/text.crt', 'no es un certificado');
        try {
            $storage->store('company-1', $text, $otherKey);
            $this->fail('A non PEM file must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('PEM', $e->getMessage());
        }
        file_put_contents($big = $this->dir . '/in/big.crt', str_repeat('A', ArcaCertificateStorage::MAX_BYTES + 1));
        $this->expectExceptionMessage('tamaño');
        $storage->store('company-1', $big, $otherKey);
    }

    public function testRejectsUnsafeCompanyIdentifiers(): void
    {
        [$cert, $key] = $this->bundle('c');
        $this->expectExceptionMessage('Empresa no válida');
        (new ArcaCertificateStorage($this->dir . '/private'))->store('../../public', $cert, $key);
    }
}
