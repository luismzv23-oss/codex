<?php
namespace App\Libraries;
use App\Libraries\PurchaseImport\{InvoiceExtractor,AzureInvoiceExtractor,InvoiceExtractionNormalizer};
use App\Models\{PurchaseImportModel,PurchaseImportJobModel,PurchaseDocumentModel};

class PurchaseImportWorker
{
    private InvoiceExtractor $extractor;
    private PurchaseDocumentStorage $storage;
    public function __construct(?InvoiceExtractor $extractor=null,?PurchaseDocumentStorage $storage=null)
    {
        $this->extractor=$extractor ?? new AzureInvoiceExtractor();$this->storage=$storage ?? new PurchaseDocumentStorage();
    }
    public function run(int $limit=10,?string $company=null): int
    {
        $count=0;
        while ($count<max(1,min(100,$limit))) {
            $query=(new PurchaseImportJobModel())->groupStart()->groupStart()->where('status','queued')->where('available_at <=',date('Y-m-d H:i:s'))->groupEnd()
                ->orGroupStart()->where('status','running')->where('lease_until <=',date('Y-m-d H:i:s'))->groupEnd()->groupEnd();
            if ($company) $query->where('company_id',$company);
            $candidate=$query->orderBy('created_at')->first();
            if (!$candidate) break;
            $job=(new InventoryIntegrityService())->transaction($candidate['company_id'],function() use($candidate) {
                $current=(new PurchaseImportJobModel())->find($candidate['id']);$now=date('Y-m-d H:i:s');
                if (!$current || !(($current['status']==='queued' && $current['available_at']<=$now) || ($current['status']==='running' && $current['lease_until']<=$now))) return null;
                $draft=(new PurchaseImportService())->owned($current['company_id'],$current['import_id']);
                if ($draft['review_status']==='registered' || (int)$draft['generation']!==(int)$current['generation']) {
                    (new PurchaseImportJobModel())->update($current['id'],['status'=>'cancelled']);return null;
                }
                $token=app_uuid();
                if (!(new PurchaseImportJobModel())->update($current['id'],['status'=>'running','lease_token'=>$token,'lease_until'=>date('Y-m-d H:i:s',time()+config('PurchaseImport')->leaseSeconds)])) throw new \RuntimeException('No se pudo reservar el trabajo.');
                if (!(new PurchaseImportModel())->update($draft['id'],['processing_status'=>'reading'])) throw new \RuntimeException('No se pudo actualizar el trabajo.');
                return array_merge($current,['lease_token'=>$token]);
            });
            $count++;
            if (!$job) continue;
            try {
                $draft=(new PurchaseImportService())->owned($job['company_id'],$job['import_id']);
                $document=(new PurchaseDocumentModel())->where('company_id',$job['company_id'])->find($draft['document_id']);
                if (!$document) throw new \RuntimeException('Documento no disponible para lectura.');
                $path=$this->storage->path($document);
                if (!hash_equals($document['sha256'],hash_file('sha256',$path))) throw new \RuntimeException('El documento almacenado no coincide con su huella original.');
                if (!$job['operation_url']) {
                    $url=$this->extractor->start($path,$document);
                    $this->finish($job,['operation_url'=>$url,'status'=>'queued','available_at'=>date('Y-m-d H:i:s',time()+5)],null);
                    continue;
                }
                $result=$this->extractor->poll($job['operation_url']);
                if ($result===null) {
                    $this->finish($job,['status'=>'queued','available_at'=>date('Y-m-d H:i:s',time()+10)],null);continue;
                }
                $normalized=(new InvoiceExtractionNormalizer())->normalize($job['company_id'],$result,$document);
                if (strlen(json_encode($normalized,JSON_THROW_ON_ERROR))>4000000) throw new \RuntimeException('La lectura excedió el tamaño de resultado admitido.');
                $this->finish($job,['status'=>'done','error_message'=>null],$normalized);
            } catch (\Throwable $e) {
                $attempts=(int)$job['attempts']+1;$retry=$attempts<config('PurchaseImport')->maxAttempts;
                $message=$e instanceof \RuntimeException?mb_substr($e->getMessage(),0,500):'No se pudo interpretar la lectura. Puedes continuar manualmente.';
                $this->finish($job,['status'=>$retry?'queued':'failed','attempts'=>$attempts,'error_message'=>$message,'available_at'=>date('Y-m-d H:i:s',time()+60*(2**$attempts))],null);
            }
        }
        return $count;
    }
    private function finish(array $job,array $changes,?array $extraction): void
    {
        (new InventoryIntegrityService())->transaction($job['company_id'],function() use($job,$changes,$extraction) {
            $current=(new PurchaseImportJobModel())->where('company_id',$job['company_id'])->find($job['id']);
            if (!$current || $current['status']!=='running' || $current['lease_token']!==$job['lease_token']) return;
            $draft=(new PurchaseImportService())->owned($job['company_id'],$job['import_id']);
            if ($draft['review_status']==='registered' || (int)$draft['generation']!==(int)$job['generation']) {
                (new PurchaseImportJobModel())->update($job['id'],['status'=>'cancelled','lease_token'=>null,'lease_until'=>null]);return;
            }
            if ($extraction!==null) $changes['result_json']=json_encode($extraction,JSON_THROW_ON_ERROR);
            if (!(new PurchaseImportJobModel())->update($job['id'],$changes+['lease_token'=>null,'lease_until'=>null])) throw new \RuntimeException('No se pudo actualizar el trabajo.');
            $status=$changes['status']==='done'?'done':($changes['status']==='failed'?'error':'reading');
            $update=['processing_status'=>$status];
            if ($extraction!==null) $update['extraction_json']=json_encode($extraction+['generation'=>(int)$job['generation']],JSON_THROW_ON_ERROR);
            if (!(new PurchaseImportModel())->update($draft['id'],$update)) throw new \RuntimeException('No se pudo conservar la lectura.');
            if (in_array($changes['status'],['done','failed'],true)) (new PurchaseImportService())->audit($job['company_id'],$draft['id'],null,'analysis_'.$changes['status'],['generation'=>(int)$job['generation']]);
        });
    }
}
