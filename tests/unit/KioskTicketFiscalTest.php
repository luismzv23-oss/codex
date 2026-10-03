<?php

use App\Libraries\KioskTicketFiscal;
use CodeIgniter\Test\CIUnitTestCase;

final class KioskTicketFiscalTest extends CIUnitTestCase
{
    public function testUsesAuthorizedPayloadAndDoesNotExposeCredentials(): void
    {
        $result = ['status'=>'authorized','cae'=>'12345678901234','environment'=>'produccion','cae_due_date'=>'2026-10-10',
            'request_payload'=>['Auth'=>['Cuit'=>30123456789,'Token'=>'secret'], 'FeCAEReq'=>[
                'FeCabReq'=>['PtoVta'=>2,'CbteTipo'=>6], 'FeDetReq'=>['FECAEDetRequest'=>[
                    'CbteDesde'=>123,'CbteFch'=>'20260930','ImpTotal'=>121,'MonId'=>'PES','MonCotiz'=>1,
                    'ImpIVA'=>21,'ImpTrib'=>3,'Tributos'=>['Tributo'=>[['Id'=>1,'Importe'=>1],['Id'=>2,'Importe'=>2]]],
                ]]]]];
        $data = KioskTicketFiscal::fromResult($result);
        $payload = json_decode(base64_decode(explode('?p=', $data['qrUrl'])[1]), true);
        $this->assertSame('30123456789', $data['taxId']);
        $this->assertSame(121, $payload['importe']);
        $this->assertSame(123, $payload['nroCmp']);
        $this->assertSame('2026-09-30', $payload['fecha']);
        $this->assertSame(1.0, $data['nationalTaxes']);
        $this->assertSame(21.0, $data['containedVat']);
        $this->assertStringNotContainsString('secret', json_encode($data));
        $this->assertFalse($data['testEnvironment']);
        unset($result['request_payload']);
        $this->assertNull(KioskTicketFiscal::fromResult($result)['qrUrl']);
        $result['status'] = 'rejected';
        $this->assertNull(KioskTicketFiscal::fromResult($result)['cae']);
    }
}
