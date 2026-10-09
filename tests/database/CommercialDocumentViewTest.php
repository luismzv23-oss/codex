<?php
use CodeIgniter\Test\CIUnitTestCase;
final class CommercialDocumentViewTest extends CIUnitTestCase {
 public function testBothFormatsRenderAndDeliveryNoteOmitsMoney():void {
  $data=['title'=>'Remito','unpriced'=>true,'document'=>['number'=>'RTO-1','date'=>'2026-10-09','status'=>'pending','subtotal'=>1000,'tax_total'=>210,'total'=>1210],'company'=>['name'=>'Demo'],'items'=>[['product_name'=>'Producto','quantity'=>2,'unit_price'=>605,'line_total'=>1210]]];
  $html=view('sales/pdf/commercial',$data,['saveData'=>false]);
  $this->assertStringContainsString('Producto',$html);$this->assertStringNotContainsString('1.210,00',$html);$this->assertStringNotContainsString('Precio unitario',$html);
  $pdf=new Dompdf\Dompdf();$pdf->loadHtml($html);$pdf->render();$this->assertStringStartsWith('%PDF-',$pdf->output());
  $data['unpriced']=false;$data['title']='Presupuesto';$html=view('sales/pdf/commercial',$data,['saveData'=>false]);$this->assertStringContainsString('1.210,00',$html);$this->assertStringContainsString('Precio unitario',$html);
 }
 public function testActionsIncludeCompanyAndSourceWithoutWritePermissionDependency():void {
 helper('url');$html=view('sales/document_actions',['row'=>['id'=>'q','source_type'=>'cycle'],'kind'=>'presupuesto','companyId'=>'company'],['saveData'=>false]);
 $this->assertStringContainsString('ventas/documentos/presupuesto/cycle/q',$html);$this->assertStringContainsString('company_id=company',$html);$this->assertStringContainsString('download=1',$html);$this->assertStringContainsString('data-popup="true"',$html);$this->assertStringContainsString('data-popup-pdf="true"',$html);$this->assertStringNotContainsString('target="_blank"',$html);
 }
}
