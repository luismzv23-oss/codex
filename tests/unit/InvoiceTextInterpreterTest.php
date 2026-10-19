<?php
use App\Libraries\PurchaseImport\InvoiceTextInterpreter;
use CodeIgniter\Test\CIUnitTestCase;
final class InvoiceTextInterpreterTest extends CIUnitTestCase
{
    private function parse(array $lines): array
    {
        return (new InvoiceTextInterpreter())->interpret([['pageNumber'=>1,'width'=>100,'height'=>100,'lines'=>array_map(static fn($text)=>['text'=>$text],$lines)]]);
    }
    public function testLocalizedAmountsAreReadWithoutLosingPrecision(): void
    {
        $parser=new InvoiceTextInterpreter();
        foreach (['$ 1.234,56'=>'1234.56','1,234.56'=>'1234.56','242,00'=>'242.00','1.234'=>'1234.00','0'=>'0.00'] as $input=>$expected) $this->assertSame($expected,$parser->money((string)$input));
        $this->assertSame('1.234567',$parser->money('1,234567',6));
        $this->assertNull($parser->money('-12'));$this->assertNull($parser->money('12,34,56'));
    }
    public function testHeaderAndExplicitTableBecomeReviewableSuggestions(): void
    {
        $result=$this->parse(['FACTURA A 00001-00000123','Proveedor: Ejemplo SRL','CUIT: 30-12345678-9','Cliente CUIT: 30-71234567-8',
            'Fecha: 16/10/2026','Vencimiento: 30/10/2026','Moneda: ARS',
            'Código  Descripción  Cantidad  Precio unitario  IVA %  Importe',
            'SKU1  Producto uno  2,125  100,00  21  257,13','TOTAL 257,13']);
        $fields=$result['documents'][0]['fields'];
        $this->assertSame('00001-00000123',$fields['InvoiceId']['valueString']);
        $this->assertSame('30123456789',$fields['VendorTaxId']['valueString']);
        $this->assertSame('30712345678',$fields['CustomerTaxId']['valueString']);
        $this->assertSame('2026-10-16',$fields['InvoiceDate']['valueString']);
        $this->assertSame('ARS',$fields['InvoiceTotal']['valueCurrency']['currencyCode']);
        $this->assertSame('2.125000',$fields['Items']['valueArray'][0]['valueObject']['Quantity']['valueString']);
        $this->assertArrayNotHasKey('confidence',$fields['InvoiceId']);
    }
    public function testConflictingTotalsAndUnlabeledCuitsAreLeftForManualReview(): void
    {
        $result=$this->parse(['FACTURA B 00002-00000003','CUIT: 30-12345678-9','CUIT: 30-71234567-8','TOTAL 100,00','TOTAL 200,00']);
        $this->assertArrayNotHasKey('InvoiceTotal',$result['documents'][0]['fields']);
        $this->assertArrayNotHasKey('VendorTaxId',$result['documents'][0]['fields']);
        $this->assertCount(3,$result['warnings']);
    }
    public function testDifferentInvoiceNumbersAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parse(['FACTURA A 00001-00000001','FACTURA A 00001-00000002','TOTAL 100,00']);
    }
    public function testInvalidDatesAndUnrecognizedTablesAreNotInvented(): void
    {
        $result=$this->parse(['FACTURA C 00001-00000001','Fecha: 31/02/2026','Producto 2 100 200','TOTAL 200,00']);
        $this->assertArrayNotHasKey('InvoiceDate',$result['documents'][0]['fields']);
        $this->assertArrayNotHasKey('Items',$result['documents'][0]['fields']);
    }
    public function testLayoutHeaderMultilineDescriptionAndTaxBreakdownArePreserved(): void
    {
        $result=$this->parse(['EMBOTELLADORA EJEMPLO S.A.     A       FACTURA','Fecha:','Nº 1002 - 00118817','30/04/2026',
            'CUIT: 30-12345678-9','Establec. Nº: 05-5059312-01','Sr.(s)  Cliente Ejemplo','F. Pago: CONTADO    CUIT: 30-71234567-8',
            'Cod  Descripcion  Cant.  Precio Unit.  %Dto.  Imp.Int.  Importe',
            '5670  Bebida uno  2.00  10.00  0  1.60  20.00',
            '8670  Agua mineral fuente del  3.00  10.00  0  2.40  30.00','MILAGRO',
            'Subtotal  50.00','Perc. IVA  2.00','Perc.IB S.J.  3.00','Imp. Int  4.00','Neto Grav  50.00','IVA 21%  10.50','Total  69.50',
            'CAEA 12345678901234','Fecha Vto. 30/04/2026']);
        $fields=$result['documents'][0]['fields'];
        $this->assertSame('01002-00118817',$fields['InvoiceId']['valueString']);
        $this->assertSame('FACTURA_A',$fields['DocumentType']['valueString']);
        $this->assertSame('2026-04-30',$fields['InvoiceDate']['valueString']);
        $this->assertArrayNotHasKey('DueDate',$fields);
        $this->assertSame('30123456789',$fields['VendorTaxId']['valueString']);
        $this->assertSame('30712345678',$fields['CustomerTaxId']['valueString']);
        $this->assertCount(2,$fields['Items']['valueArray']);
        $this->assertSame('Agua mineral fuente del MILAGRO',$fields['Items']['valueArray'][1]['valueObject']['Description']['valueString']);
        $this->assertSame('21',$fields['Items']['valueArray'][0]['valueObject']['TaxRate']['valueString']);
        $this->assertCount(3,$result['adjustments']);$this->assertSame([],$result['warnings']);
    }
}
