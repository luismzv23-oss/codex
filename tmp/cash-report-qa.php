<?php
require getcwd().'/vendor/codeigniter4/framework/system/Test/bootstrap.php';
$data = [
    'register'=>['name'=>'Caja QA', 'code'=>'QA'],
    'company'=>['legal_name'=>'Empresa de prueba', 'name'=>'QA', 'tax_id'=>'-', 'address'=>'-'],
    'session'=>['status'=>'closed','opened_at'=>'2026-10-13 08:00','closed_at'=>'2026-10-13 18:00','opening_amount'=>1500,'expected_closing_amount'=>36192,'actual_closing_amount'=>36192,'difference_amount'=>0,'notes'=>''],
    'openedBy'=>['name'=>'Operador QA'],'closedBy'=>['name'=>'Operador QA'],
    'paymentMethods'=>[['payment_method'=>'cash','total'=>12300],['payment_method'=>'qr','total'=>16320],['payment_method'=>'card','total'=>6072]],
    'sales'=>[['total'=>17520],['total'=>10700],['total'=>6472]],
];
$html = view('cash/pdf/session', $data, ['saveData'=>false]);
if (strpos($html,'34.692,00') === false || strpos($html,'COMPROBANTES EMITIDOS') === false || strpos($html,'Facturas y Ventas Emitidas') !== false) throw new RuntimeException('Invalid KPI');
$pdf = new Dompdf\Dompdf();
$pdf->loadHtml($html); $pdf->setPaper('A4'); $pdf->render();
file_put_contents(ROOTPATH.'tmp/cash-report-qa.pdf',$pdf->output());
echo 'PDF generated: '.$pdf->getCanvas()->get_page_count().' page(s)'.PHP_EOL;
