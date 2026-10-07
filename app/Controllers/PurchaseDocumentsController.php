<?php
namespace App\Controllers;
use App\Libraries\PurchaseDocumentService;
use App\Libraries\PurchasePdfExtractor;
use CodeIgniter\HTTP\RedirectResponse;

class PurchaseDocumentsController extends PurchasesController
{
    private function options(string $company): array
    {
        $db=db_connect();
        return [
            'suppliers'=>$db->table('suppliers')->select('id,name,tax_id')->where('company_id',$company)->where('active',1)->orderBy('name')->get()->getResultArray(),
            'products'=>$db->table('inventory_products')->select('id,sku,name')->where('company_id',$company)->where('active',1)->orderBy('name')->get()->getResultArray(),
            'receipts'=>$db->table('purchase_receipts')->select('id,receipt_number,supplier_id,currency_code')->where('company_id',$company)->where('status !=','cancelled')->orderBy('received_at','DESC')->get()->getResultArray(),
            'currencies'=>$this->companyCurrencyOptions($company,'ARS'),
        ];
    }
    public function wizard()
    {
        $context=$this->purchaseContext('manage'); if($context instanceof RedirectResponse) return $context;
        $company=$context['company']['id'];
        return view('purchases/import', ['pageTitle'=>'Importar factura','context'=>$context,'companyId'=>$company,'isPopup'=>$this->isPopupRequest(),'options'=>$this->options($company)]);
    }
    public function catalogue()
    {
        $context=$this->purchaseContext('manage'); if($context instanceof RedirectResponse) return $this->json(['error'=>'No tienes acceso de gestion a Compras.'],403);
        return $this->json($this->options($context['company']['id']));
    }
    public function documents()
    {
        $context=$this->purchaseContext('manage'); if($context instanceof RedirectResponse) return $this->json(['error'=>'No tienes acceso de gestion a Compras.'],403);
        $rows=db_connect()->table('purchase_documents')->select('id,original_name,status,purchase_invoice_id,created_at')->where('company_id',$context['company']['id'])->orderBy('created_at','DESC')->get()->getResultArray();
        return $this->json(['documents'=>$rows]);
    }
    public function document(string $id)
    {
        return $this->operation(fn($company,$user,$service)=>$service->data($service->owned($company,$id)));
    }
    public function upload()
    {
        return $this->operation(function($company,$user,$service) {
            $file=$this->request->getFile('document');
            if(!$file || !$file->isValid() || $file->getSize()>10*1024*1024 || !in_array(strtolower($file->getExtension()),['pdf','jpg','jpeg','png'],true)) throw new \RuntimeException('Selecciona un PDF, JPG, JPEG o PNG valido dentro del limite de carga del servidor.');
            return $service->upload($company,$user,$file->getClientName(),(string)file_get_contents($file->getTempName()));
        });
    }
    public function analyze(string $id)
    {
        return $this->operation(function($company,$user,$service) use($id) {
            $document=$service->data($service->owned($company,$id));
            if($document['status']!=='draft' || $document['revision']!==0) return $document;
            set_time_limit(120);
            $extracted=(new PurchasePdfExtractor())->extract($service->pdf($company,$id));
            $options=$this->options($company);
            $draft=(new PurchasePdfExtractor())->suggest($extracted['text'],$options['suppliers'],$options['products']);
            $draft['source_text']=$extracted['text'];$draft['extraction']=$extracted['methods'];$draft['pages']=$extracted['pages'];
            return $service->save($company,$id,$user,$draft,0);
        });
    }
    public function save(string $id)
    {
        return $this->operation(function($company,$user,$service) use($id) {
            $payload=$this->request->getJSON(true)?:[];
            return $service->save($company,$id,$user,(array)($payload['draft']??[]),(int)($payload['revision']??-1));
        });
    }
    public function confirm(string $id)
    {
        return $this->operation(function($company,$user,$service) use($id) {
            $payload=$this->request->getJSON(true)?:[];
            return $service->confirm($company,$id,$user,(int)($payload['revision']??-1),array_keys($this->companyCurrencyOptions($company,'ARS')));
        });
    }
    public function file(string $id)
    {
        $context=$this->purchaseContext('view'); if($context instanceof RedirectResponse) return $context;
        try {
            $company=$context['company']['id'];$service=new PurchaseDocumentService();$pdf=$service->pdf($company,$id);
            $service->event($company,$id,$this->currentUser()['id'],$this->request->getGet('download')==='1'?'download':'preview');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($pdf);
            $extension=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime]??'bin';
            return $this->response->setHeader('Content-Type',$mime)->setHeader('Cache-Control','private, no-store')
                ->setHeader('Content-Security-Policy',"sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'")
                ->setHeader('Content-Disposition',($this->request->getGet('download')==='1'?'attachment':'inline').'; filename="factura-'.$id.'.'.$extension.'"')->setBody($pdf);
        } catch(\Throwable $e) { return $this->response->setStatusCode(404)->setBody('Documento no disponible.'); }
    }
    public function preview(string $id,string $page)
    {
        $context=$this->purchaseContext('view'); if($context instanceof RedirectResponse) return $context;
        try {
            if(!ctype_digit($page) || (int)$page<1 || (int)$page>20) throw new \RuntimeException('Pagina no valida.');
            $company=$context['company']['id'];$service=new PurchaseDocumentService();
            $image=(new PurchasePdfExtractor())->preview($service->pdf($company,$id),(int)$page);
            $service->event($company,$id,$this->currentUser()['id'],'preview');
            return $this->response->setHeader('Content-Type','image/png')->setHeader('Cache-Control','private, no-store')->setBody($image);
        } catch(\Throwable $e) { return $this->response->setStatusCode(404)->setBody('Vista previa no disponible.'); }
    }
    private function operation(callable $operation)
    {
        $context=$this->purchaseContext('manage'); if($context instanceof RedirectResponse) return $this->json(['error'=>'No tienes acceso de gestion a Compras.'],403);
        try { return $this->json($operation($context['company']['id'],$this->currentUser()['id'],new PurchaseDocumentService())); }
        catch(\Throwable $e) {
            log_message('error','Importacion de compras: {message}',['message'=>$e->getMessage()]);
            $message=$e instanceof \RuntimeException && !$e instanceof \CodeIgniter\Database\Exceptions\DatabaseException ? $e->getMessage() : 'No se pudo completar la operacion. El borrador se conserva; revisa los datos e intenta nuevamente.';
            return $this->json(['error'=>$message],422);
        }
    }
    private function json(array $data,int $status=200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control','no-store')->setJSON($data+['csrf'=>csrf_hash()]);
    }
}
