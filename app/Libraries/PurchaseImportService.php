<?php
namespace App\Libraries;
use App\Models\{PurchaseImportModel,PurchaseDocumentModel,PurchaseImportItemModel,PurchaseImportAuditModel,PurchaseImportJobModel,SupplierProductLinkModel};
use RuntimeException;

class PurchaseImportService
{
    private PurchaseDocumentStorage $storage;
    public function __construct(?PurchaseDocumentStorage $storage=null) {$this->storage=$storage ?? new PurchaseDocumentStorage();}
    public function owned(string $companyId,string $id): array
    {
        $row=(new PurchaseImportModel())->where('company_id',$companyId)->find($id);
        if (!$row) throw new RuntimeException('Importación no disponible en esta empresa.');
        return $row;
    }
    public function upload(string $companyId,string $path,string $filename,string $userId): array
    {
        $info=$this->storage->inspect($path,$filename);$stored=null;
        try {
            return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$path,$info,$userId,&$stored) {
                $document=(new PurchaseDocumentModel())->where('company_id',$companyId)->where('sha256',$info['sha256'])->first();
                if ($document) {
                    $old=(new PurchaseImportModel())->where('company_id',$companyId)->where('document_id',$document['id'])->first();
                    if ($old) return $this->get($companyId,$old['id'])+['reused'=>true];
                }
                $stored=$this->storage->store($companyId,$path,$info);
                $documentId=(new PurchaseDocumentModel())->insert(array_diff_key($info,['extension'=>true])+['company_id'=>$companyId,'storage_path'=>$stored,'created_by'=>$userId],true);
                if (!$documentId) throw new RuntimeException('No se pudo guardar el adjunto.');
                $company=db_connect()->table('companies')->where('id',$companyId)->get()->getRowArray();
                $id=(new PurchaseImportModel())->insert(['company_id'=>$companyId,'document_id'=>$documentId,'processing_status'=>'idle','review_status'=>'draft',
                    'version'=>1,'generation'=>0,'data_json'=>json_encode(['currency_code'=>$company['currency_code'] ?? 'ARS','exchange_rate'=>'1','purchase_kind'=>'goods','adjustments'=>[]],JSON_THROW_ON_ERROR),'created_by'=>$userId],true);
                if (!$id) throw new RuntimeException('No se pudo iniciar el borrador.');
                $this->audit($companyId,$id,$userId,'uploaded',['sha256'=>$info['sha256'],'pages'=>$info['page_count']]);
                return $this->get($companyId,$id)+['reused'=>false];
            });
        } catch (\Throwable $e) {
            if ($stored) $this->storage->discard($stored);
            throw $e;
        }
    }
    public function get(string $companyId,string $id): array
    {
        $row=$this->owned($companyId,$id);$data=json_decode($row['data_json'] ?: '{}',true,512,JSON_THROW_ON_ERROR);
        $data['items']=array_map(static fn($item)=>json_decode($item['data_json'],true,512,JSON_THROW_ON_ERROR),
            (new PurchaseImportItemModel())->where('import_id',$id)->orderBy('line_number')->findAll());
        $document=(new PurchaseDocumentModel())->where('company_id',$companyId)->find($row['document_id']);
        if (!$document) throw new RuntimeException('El adjunto no está disponible.');
        $extraction=json_decode($row['extraction_json'] ?: '{}',true,512,JSON_THROW_ON_ERROR);
        return ['id'=>$id,'version'=>(int)$row['version'],'processing_status'=>$row['processing_status'],'review_status'=>$row['review_status'],
            'invoice_id'=>$row['invoice_id'],'data'=>$data,'extraction'=>$extraction,
            'document'=>array_intersect_key($document,array_flip(['id','original_name','mime_type','byte_size','page_count'])),
            'last_error'=>(new PurchaseImportJobModel())->select('error_message')->where('company_id',$companyId)->where('import_id',$id)->where('generation',(int)$row['generation'])->orderBy('created_at','DESC')->first()['error_message'] ?? null];
    }
    public function save(string $companyId,string $id,array $input,string $userId): array
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$id,$input,$userId) {
            $row=$this->owned($companyId,$id);
            if ($row['review_status']==='registered') throw new RuntimeException('La factura ya está registrada.');
            if ((string)($input['version'] ?? '')!==(string)$row['version']) throw new RuntimeException('El borrador cambió en otra sesión. Recarga antes de continuar.');
            $data=$input['data'] ?? null;
            if (!is_array($data) || strlen(json_encode($data,JSON_THROW_ON_ERROR))>1500000) throw new RuntimeException('Borrador inválido o demasiado grande.');
            $items=$data['items'] ?? [];
            if (!is_array($items) || count($items)>300) throw new RuntimeException('Se admiten hasta 300 conceptos.');
            unset($data['items']);$data=$this->cleanData($data);
            $model=new PurchaseImportItemModel();$model->where('import_id',$id)->delete();
            foreach (array_values($items) as $index=>$item) {
                if (!is_array($item)) throw new RuntimeException('Concepto inválido.');
                $item=array_intersect_key($item,array_flip(['description','quantity','unit_cost','tax_rate','tax_amount','product_id','supplier_code']));
                foreach ($item as $value) PurchaseInvoiceService::text($value,500);
                if (!$model->insert(['import_id'=>$id,'line_number'=>$index+1,'data_json'=>json_encode($item,JSON_THROW_ON_ERROR)])) throw new RuntimeException('No se pudo guardar el detalle.');
            }
            $ready=false;
            try {(new PurchaseInvoiceService())->normalize($companyId,$data+['items'=>$items],true);$ready=true;} catch (RuntimeException $e) {}
            if (!(new PurchaseImportModel())->update($id,['data_json'=>json_encode($data,JSON_THROW_ON_ERROR),'version'=>(int)$row['version']+1,'review_status'=>$ready?'ready':'needs_review'])) throw new RuntimeException('No se pudo guardar el borrador.');
            $this->audit($companyId,$id,$userId,'saved',['previous_data'=>json_decode($row['data_json'] ?: '{}',true),'data'=>$data,'items'=>$items,'version'=>(int)$row['version']+1]);
            return $this->get($companyId,$id);
        });
    }
    private function cleanData(array $data): array
    {
        $data=array_intersect_key($data,array_flip(['supplier_id','supplier_tax_id','supplier_name','receiver_tax_id','invoice_number','document_type','point_of_sale','invoice_sequence',
            'currency_code','exchange_rate','purchase_kind','purchase_receipt_id','issue_date','due_date','notes','reviewed_total','adjustments','identity_reviewed']));
        foreach ($data as $key=>$value) {
            if ($key==='adjustments') {
                if (!is_array($value) || count($value)>40) throw new RuntimeException('Se admiten hasta 40 ajustes.');
                foreach ($value as $adjustment) {
                    if (!is_array($adjustment)) throw new RuntimeException('Ajuste inválido.');
                    foreach ($adjustment as $part) PurchaseInvoiceService::text($part,180);
                }
            } else if ($key==='identity_reviewed') $data[$key]=in_array($value,[true,1,'1'],true);
            else $data[$key]=PurchaseInvoiceService::text($value,$key==='notes'?2000:180);
        }
        return $data;
    }
    public function confirm(string $companyId,string $id,array $input,string $userId): array
    {
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$id,$input,$userId) {
            $row=$this->owned($companyId,$id);
            $key=PurchaseInvoiceService::text($input['request_key'] ?? '',80);
            if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$key)) throw new RuntimeException('Falta un identificador válido de confirmación.');
            if ($row['review_status']==='registered') {
                if (!hash_equals((string)$row['confirmation_key'],$key)) throw new RuntimeException('El borrador ya fue registrado en otra operación.');
                return $this->get($companyId,$id);
            }
            if ((string)($input['version'] ?? '')!==(string)$row['version']) throw new RuntimeException('El borrador cambió. Actualiza la revisión antes de registrar.');
            $draft=$this->get($companyId,$id);$data=$draft['data'];$data['request_key']=$key;
            $invoice=(new PurchaseInvoiceService())->register($companyId,$data,$userId,true,$id);
            foreach ($data['items'] as $item) {
                $code=trim((string)($item['supplier_code'] ?? ''));$product=$item['product_id'] ?? '';
                if (!$code || !$product) continue;
                if (mb_strlen($code)>120) throw new RuntimeException('El código de proveedor supera 120 caracteres.');
                $links=new SupplierProductLinkModel();
                $link=$links->where('company_id',$companyId)->where('supplier_id',$invoice['supplier_id'])->where('supplier_code',$code)->first();
                $value=['product_id'=>$product,'confirmed_by'=>$userId];
                if ($link) $ok=$links->update($link['id'],$value);
                else $ok=$links->insert($value+['company_id'=>$companyId,'supplier_id'=>$invoice['supplier_id'],'supplier_code'=>$code]);
                if (!$ok) throw new RuntimeException('No se pudo conservar la asociación de producto.');
            }
            if (!(new PurchaseImportJobModel())->where('company_id',$companyId)->where('import_id',$id)->whereIn('status',['queued','running'])
                ->set(['status'=>'cancelled','lease_token'=>null,'lease_until'=>null])->update()) throw new RuntimeException('No se pudo cerrar el procesamiento pendiente.');
            if (!(new PurchaseImportModel())->update($id,['processing_status'=>in_array($row['processing_status'],['queued','reading'],true)?'idle':$row['processing_status'],'review_status'=>'registered','invoice_id'=>$invoice['id'],'confirmation_key'=>$key,
                'confirmed_by'=>$userId,'confirmed_at'=>date('Y-m-d H:i:s'),'version'=>(int)$row['version']+1])) throw new RuntimeException('No se pudo confirmar la importación.');
            $this->audit($companyId,$id,$userId,'confirmed',['invoice_id'=>$invoice['id'],'total'=>$invoice['total']]);
            return $this->get($companyId,$id);
        });
    }
    public function queue(string $companyId,string $id,string $userId): array
    {
        $provider=config('PurchaseImport')->provider;
        if (!\App\Libraries\PurchaseImport\InvoiceReaderFactory::make($provider)->configured()) throw new RuntimeException('La lectura automática todavía no está configurada. Puedes completar y registrar el borrador manualmente.');
        return (new InventoryIntegrityService())->transaction($companyId,function() use($companyId,$id,$userId,$provider) {
            $row=$this->owned($companyId,$id);
            if ($row['review_status']==='registered') throw new RuntimeException('El borrador ya está registrado.');
            if (in_array($row['processing_status'],['queued','reading'],true)) return $this->get($companyId,$id);
            $generation=(int)$row['generation']+1;
            if (!(new PurchaseImportJobModel())->insert(['company_id'=>$companyId,'import_id'=>$id,'generation'=>$generation,'provider'=>$provider,'status'=>'queued','attempts'=>0,'available_at'=>date('Y-m-d H:i:s')])) throw new RuntimeException('No se pudo solicitar la lectura.');
            if (!(new PurchaseImportModel())->update($id,['generation'=>$generation,'processing_status'=>'queued'])) throw new RuntimeException('No se pudo encolar el documento.');
            $this->audit($companyId,$id,$userId,'analysis_requested',['generation'=>$generation]);
            return $this->get($companyId,$id);
        });
    }
    public function audit(string $companyId,string $id,?string $userId,string $action,array $details): void
    {
        if (!(new PurchaseImportAuditModel())->insert(['company_id'=>$companyId,'import_id'=>$id,'user_id'=>$userId,'action'=>$action,'details_json'=>json_encode($details,JSON_THROW_ON_ERROR)])) throw new RuntimeException('No se pudo registrar la trazabilidad.');
    }
}
