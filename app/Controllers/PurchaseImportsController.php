<?php
namespace App\Controllers;
use App\Libraries\{PurchaseImportService,PurchaseDocumentStorage};
use App\Models\{PurchaseImportModel,PurchaseDocumentModel};
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/** All actions repeat company/module/role authorization, including file previews. */
class PurchaseImportsController extends PurchasesController
{
    protected function importContext(): array
    {
        if (!in_array($this->roleSlug(),['admin','superadmin'],true)) throw new RuntimeException('Solo un administrador autorizado puede gestionar la carga de facturas.');
        $context=$this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) throw new RuntimeException('No tienes acceso de gestión a Compras en esta empresa.');
        return $context;
    }
    private function reply(array $data,int $status=200)
    {
        return $this->response->setHeader('Cache-Control','no-store')->setStatusCode($status)->setJSON($data+['csrf_token'=>csrf_hash()]);
    }
    private function payloadInput(): array {return (array)($this->request->getJSON(true) ?: $this->request->getPost());}
    public function index()
    {
        try {
            $context=$this->importContext();$companyId=$context['company']['id'];
            return view('purchases/imports',[
                'pageTitle'=>'Asistente de facturas','context'=>$context,'companyId'=>$companyId,'selectedCompanyId'=>$companyId,
                'isPopup'=>$this->isPopupRequest(),
                'drafts'=>array_merge(
                    (new PurchaseImportModel())->where('company_id',$companyId)->where('review_status !=','registered')->orderBy('created_at','DESC')->findAll(),
                    (new PurchaseImportModel())->where('company_id',$companyId)->where('review_status','registered')->orderBy('created_at','DESC')->findAll(50)),
                'suppliers'=>db_connect()->table('suppliers')->where('company_id',$companyId)->where('active',1)->orderBy('name')->get()->getResultArray(),
                'products'=>db_connect()->table('inventory_products')->select('id,sku,name')->where('company_id',$companyId)->where('active',1)->orderBy('name')->get()->getResultArray(),
                'receipts'=>db_connect()->table('purchase_receipts')->select('id,supplier_id,receipt_number,currency_code,status')->where('company_id',$companyId)->where('status !=','cancelled')->orderBy('created_at','DESC')->get()->getResultArray(),
                'currencies'=>db_connect()->table('currencies')->select('code,name')->where('company_id',$companyId)->where('active',1)->orderBy('code')->get()->getResultArray(),
                'ocrConfigured'=>(new \App\Libraries\PurchaseImport\AzureInvoiceExtractor())->configured(),
            ]);
        } catch (RuntimeException $e) {return redirect()->to('/compras')->with('error',$e->getMessage());}
    }
    public function upload()
    {
        try {
            $context=$this->importContext();$file=$this->request->getFile('invoice_file');
            if (!$file || !$file->isValid() || $file->hasMoved()) throw new RuntimeException('Selecciona un archivo válido. Si supera el límite del servidor, reduce su tamaño.');
            $draft=(new PurchaseImportService())->upload($context['company']['id'],$file->getTempName(),$file->getClientName(),$this->currentUser()['id']);
            return $this->reply(['draft'=>$draft],201);
        } catch (\Throwable $e) {return $this->reply(['error'=>$e->getMessage()],422);}
    }
    public function show(string $id)
    {
        try {$context=$this->importContext();return $this->reply(['draft'=>(new PurchaseImportService())->get($context['company']['id'],$id)]);}
        catch (\Throwable $e) {return $this->reply(['error'=>$e->getMessage()],404);}
    }
    public function save(string $id)
    {
        try {$context=$this->importContext();return $this->reply(['draft'=>(new PurchaseImportService())->save($context['company']['id'],$id,$this->payloadInput(),$this->currentUser()['id'])]);}
        catch (\Throwable $e) {return $this->reply(['error'=>$e->getMessage()],str_contains($e->getMessage(),'otra sesión')?409:422);}
    }
    public function analyze(string $id)
    {
        try {$context=$this->importContext();return $this->reply(['draft'=>(new PurchaseImportService())->queue($context['company']['id'],$id,$this->currentUser()['id'])],202);}
        catch (\Throwable $e) {return $this->reply(['error'=>$e->getMessage()],422);}
    }
    public function confirm(string $id)
    {
        try {$context=$this->importContext();return $this->reply(['draft'=>(new PurchaseImportService())->confirm($context['company']['id'],$id,$this->payloadInput(),$this->currentUser()['id'])]);}
        catch (\Throwable $e) {return $this->reply(['error'=>$e->getMessage()],422);}
    }
    public function file(string $id)
    {
        try {
            $context=$this->importContext();$row=(new PurchaseImportService())->owned($context['company']['id'],$id);
            $document=(new PurchaseDocumentModel())->where('company_id',$context['company']['id'])->find($row['document_id']);
            if (!$document) throw new RuntimeException('Documento no disponible.');
            $path=(new PurchaseDocumentStorage())->path($document);
            return $this->response->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff')
                ->setHeader('Content-Security-Policy',"sandbox; default-src 'none'; frame-ancestors 'self'")
                ->setHeader('Content-Disposition','inline; filename="factura.'.pathinfo($document['storage_path'],PATHINFO_EXTENSION).'"')
                ->setContentType($document['mime_type'])->setBody(file_get_contents($path));
        } catch (\Throwable $e) {return $this->response->setStatusCode(404)->setBody('Documento no disponible.');}
    }
}
