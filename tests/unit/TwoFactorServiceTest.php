<?php

use App\Libraries\TwoFactorService;
use CodeIgniter\Test\CIUnitTestCase;

final class TwoFactorServiceTest extends CIUnitTestCase
{
    public function testRejectsCodeGeneratedWithEmptySecret(): void
    {
        $service = new TwoFactorService();
        $this->assertFalse($service->verify('', $service->generateCode('', 1234567890), 1234567890));
    }

    public function testRejectsMalformedSecretAndCode(): void
    {
        $service = new TwoFactorService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $service->generateCode($secret, 1234567890);
        $this->assertFalse($service->verify($secret . '!', $code, 1234567890));
        $this->assertFalse($service->verify($secret, $code . "\n", 1234567890));
    }

    public function testAcceptsValidCodeWithinClockWindowOnly(): void
    {
        $service = new TwoFactorService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $service->generateCode($secret, 1234567890);
        $this->assertTrue($service->verify($secret, $code, 1234567890));
        $this->assertTrue($service->verify($secret, $code, 1234567920));
        $this->assertFalse($service->verify($secret, $code, 1234567980));
    }
}
