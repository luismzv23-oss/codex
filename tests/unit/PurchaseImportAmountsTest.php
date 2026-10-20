<?php
use App\Libraries\PurchaseImportAmounts;
use CodeIgniter\Test\CIUnitTestCase;

final class PurchaseImportAmountsTest extends CIUnitTestCase
{
    public function testStaleTaxIsRemovedAndPrecisionMatchesForm(): void
    {
        $data=PurchaseImportAmounts::normalize(['items'=>[['quantity'=>'15','unit_cost'=>'7149.580000','tax_rate'=>'21.0000','tax_amount'=>'0.00']]]);
        $this->assertSame('7149.58',$data['items'][0]['unit_cost']);
        $this->assertSame('21.00',$data['items'][0]['tax_rate']);
        $this->assertSame('',$data['items'][0]['tax_amount']);
        PurchaseImportAmounts::validate($data);
    }
    public function testDuplicatedNetIsRejectedWithoutRemovingAdjustment(): void
    {
        $data=['items'=>[['quantity'=>'2','unit_cost'=>'50.00','tax_rate'=>'21.00']], 'adjustments'=>[['kind'=>'tax','label'=>'Neto Gravado','amount'=>'100.00']]];
        $this->expectExceptionMessage('duplica');
        PurchaseImportAmounts::validate($data);
    }
    public function testDuplicatedVatIsRejected(): void
    {
        $this->expectExceptionMessage('duplica el IVA');
        PurchaseImportAmounts::validate(['items'=>[['quantity'=>'2','unit_cost'=>'50.00','tax_rate'=>'21.00']], 'adjustments'=>[['kind'=>'tax','label'=>'IVA 21%','amount'=>'21.00']]]);
    }
    public function testFractionalQuantityCannotBeRegistered(): void
    {
        $this->expectExceptionMessage('cantidad entera');
        PurchaseImportAmounts::validate(['items'=>[['quantity'=>'1.5','unit_cost'=>'50.00','tax_rate'=>'21.00']]]);
    }
    public function testPerceptionsAndExplicitRoundingRemainValid(): void
    {
        PurchaseImportAmounts::validate(['items'=>[['quantity'=>'2','unit_cost'=>'50.00','tax_rate'=>'21.00']], 'adjustments'=>[['kind'=>'tax','label'=>'Perc. IVA','amount'=>'3.00'],['kind'=>'vat','label'=>'Redondeo IVA del documento','amount'=>'0.01']]]);
        $this->addToAssertionCount(1);
    }
}
