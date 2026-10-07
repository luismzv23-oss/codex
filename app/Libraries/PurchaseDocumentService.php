<?php
namespace App\Libraries;
use RuntimeException;

class PurchaseDocumentService
{
    private PurchaseDocumentVault $vault;
    public function __construct(?PurchaseDocumentVault $vault=null) { $this->vault=$vault??new PurchaseDocumentVault(); }
    public function owned(string $company,string $id): array
    {
        $row=db_connect()->table('purchase_documents')->where('company_id',$company)->where('id',$id)->get()->getRowArray();
        if(!$row) throw new RuntimeException('Documento no disponible para esta empresa.');
        return $row;
    }
    public function data(array $row): array
    {
        $draft=$row['draft_cipher'] ? json_decode($this->vault->unseal($row['draft_cipher'],$row['company_id'].':'.$row['id'].':draft'),true,64,JSON_THROW_ON_ERROR) : [];
        return ['id'=>$row['id'],'name'=>$row['original_name'],'status'=>$row['status'],'invoice_id'=>$row['purchase_invoice_id'],'revision'=>(int)$row['revision'],'draft'=>$draft];
    }
    public function event(string $company,string $id,string $user,string $action): void
    {
        if(!db_connect()->table('purchase_document_events')->insert(['id'=>app_uuid(),'company_id'=>$company,'document_id'=>$id,'user_id'=>$user,'action'=>$action,'created_at'=>date('Y-m-d H:i:s')])) throw new RuntimeException('No se pudo registrar la auditoria documental.');
    }
    public function upload(string $company,string $user,string $name,string $pdf): array
    {
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($pdf);
        if(strlen($pdf)>10*1024*1024 || !in_array($mime,['application/pdf','image/jpeg','image/png'],true)) throw new RuntimeException('Selecciona PDF, JPG, JPEG o PNG de hasta 10 MB.');
        if($mime==='application/pdf' && !str_starts_with($pdf,'%PDF-')) throw new RuntimeException('PDF invalido.');
        if($mime!=='application/pdf') { $size=@getimagesizefromstring($pdf); if(!$size || $size[0]*$size[1]>20000000) throw new RuntimeException('La imagen debe tener hasta 20 megapixeles.'); }

        return (new InventoryIntegrityService())->transaction($company,function() use($company,$user,$name,$pdf) {
            $db=db_connect();$hash=hash('sha256',$pdf);
            $existing=$db->table('purchase_documents')->where('company_id',$company)->where('sha256',$hash)->get()->getRowArray();
            if($existing) return $this->data($existing)+['duplicate'=>true];
            $id=app_uuid();$now=date('Y-m-d H:i:s');
            try {
                $this->vault->put($company,$id,$pdf);
                $name=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u','',basename(str_replace('\\','/',$name))),0,180);
                if(!$db->table('purchase_documents')->insert(['id'=>$id,'company_id'=>$company,'sha256'=>$hash,'original_name'=>$name?:'factura.pdf','size_bytes'=>strlen($pdf),'status'=>'draft','created_by'=>$user,'updated_by'=>$user,'created_at'=>$now,'updated_at'=>$now,'revision'=>0])) throw new RuntimeException('No se pudo archivar el documento.');
                $this->event($company,$id,$user,'upload');
                return $this->data($this->owned($company,$id));
            } catch(\Throwable $e) { $this->vault->removeUncommitted($company,$id); throw $e; }
        });
    }
    public function pdf(string $company,string $id): string
    {
        $row=$this->owned($company,$id); return $this->vault->get($company,$id,$row['sha256']);
    }
    public function save(string $company,string $id,string $user,array $draft,int $revision): array
    {
        if(strlen(json_encode($draft,JSON_THROW_ON_ERROR))>600000 || count($draft['items']??[])>200) throw new RuntimeException('El borrador supera el limite de 200 renglones.');
        return (new InventoryIntegrityService())->transaction($company,function() use($company,$id,$user,$draft,$revision) {
            $row=$this->owned($company,$id);
            if($row['status']!=='draft') throw new RuntimeException('Esta factura ya fue registrada.');
            if((int)$row['revision']!==$revision) throw new RuntimeException('El borrador cambio en otra ventana. Recarga antes de guardar.');
            $cipher=$this->vault->seal(json_encode($draft,JSON_THROW_ON_ERROR),$company.':'.$id.':draft');
            db_connect()->table('purchase_documents')->where('id',$id)->where('company_id',$company)->update(['draft_cipher'=>$cipher,'revision'=>$revision+1,'updated_by'=>$user,'updated_at'=>date('Y-m-d H:i:s')]);
            if(!db_connect()->transStatus()) throw new RuntimeException('No se pudo guardar el borrador.');
            $this->event($company,$id,$user,'save_draft');
            return $this->data($this->owned($company,$id));
        });
    }
    public function rows(string $company,array $items): array
    {
        if(!$items || count($items)>200) throw new RuntimeException('Agrega entre 1 y 200 renglones.');
        $rows=[];
        foreach($items as $i=>$item) {
            $description=trim((string)($item['description']??'')); $product=trim((string)($item['product_id']??''))?:null;
            $qty=$this->number($item['quantity']??null);$cost=$this->number($item['unit_cost']??null);$rate=$this->number($item['tax_rate']??null);
            $pack=$this->number($item['pack']??1);$discount=$this->number($item['discount']??0);
            if($description==='' || mb_strlen($description)>255 || $qty<=0 || $cost<0 || $rate<0 || $rate>100 || $pack<=0 || $discount<0 || $discount>100) throw new RuntimeException('Revisa descripcion, cantidad, costo e IVA del renglon '.($i+1).'.');
            if($product && !db_connect()->table('inventory_products')->where('company_id',$company)->where('id',$product)->countAllResults()) throw new RuntimeException('El producto no pertenece a la empresa.');
            $qty=round($qty*$pack,2);$cost=round($cost*(1-$discount/100)/$pack,4);$rate=round($rate,2);
            if($qty<=0 || $qty>999999999 || $cost>999999999) throw new RuntimeException('Cantidad o costo fuera de rango.');
            $net=round($qty*$cost,2);$tax=round($net*$rate/100,2);
            if($net+$tax>999999999999) throw new RuntimeException('Importe fuera de rango.');
            $rows[]=['product_id'=>$product,'description'=>$description,'quantity'=>$qty,'unit_cost'=>$cost,'tax_rate'=>$rate,'tax_amount'=>$tax,'line_total'=>round($net+$tax,2)];
        }
        return $rows;
    }
    private function number($value): float
    {
        if(!is_numeric($value) || !is_finite((float)$value)) throw new RuntimeException('Completa los importes con numeros validos.');
        return (float)$value;
    }
    public function confirm(string $company,string $id,string $user,int $revision,array $currencies): array
    {
        return (new InventoryIntegrityService())->transaction($company,function() use($company,$id,$user,$revision,$currencies) {
            $row=$this->owned($company,$id);
            if($row['status']==='registered') return $this->data($row); // retries never create another invoice
            if((int)$row['revision']!==$revision) throw new RuntimeException('El borrador cambio. Recarga y revisa la ultima version.');
            $data=$this->data($row)['draft'];
            if(empty($data['reviewed'])) throw new RuntimeException('Confirma que revisaste el PDF y todos los renglones.');
            $supplier=(string)($data['supplier_id']??'');
            if(!db_connect()->table('suppliers')->where('company_id',$company)->where('id',$supplier)->where('active',1)->countAllResults()) throw new RuntimeException('Selecciona un proveedor activo de esta empresa.');
            $number=trim((string)($data['invoice_number']??''));
            if($number==='' || mb_strlen($number)>60) throw new RuntimeException('Completa el numero de factura (hasta 60 caracteres).');
            if(db_connect()->table('purchase_invoices')->where('company_id',$company)->where('invoice_number',$number)->countAllResults()) throw new RuntimeException('Ya existe una factura con ese numero en esta empresa.');
            if(!in_array($data['currency_code']??'', $currencies,true) || $this->number($data['exchange_rate']??null)<=0) throw new RuntimeException('Selecciona una moneda habilitada y una cotizacion valida.');
            foreach(['issue_date','due_date'] as $field) {
                $value=$data[$field]??''; if($field==='due_date' && $value==='') continue;
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$date || $date->format('Y-m-d')!==$value) throw new RuntimeException('Revisa las fechas del comprobante.');
            }
            $rows=$this->rows($company,$data['items']??[]);$registration=new PurchaseInvoiceRegistration();$totals=$registration->totals($rows);
            foreach(['expected_subtotal'=>'subtotal','expected_tax'=>'tax_total','expected_total'=>'total'] as $field=>$total) {
                if(abs($this->number($data[$field]??null)-$totals[$total])>.011) throw new RuntimeException('Los renglones no coinciden con el neto, IVA y total del PDF. Revisa los importes antes de registrar.');
            }
            // Verify the encrypted original is recoverable before creating financial records.
            $this->pdf($company,$id);
            $invoice=$registration->register($company,$user,$data,$rows);
            db_connect()->table('purchase_documents')->where('id',$id)->where('company_id',$company)->update(['status'=>'registered','purchase_invoice_id'=>$invoice,'updated_by'=>$user,'updated_at'=>date('Y-m-d H:i:s')]);
            $this->event($company,$id,$user,'register_invoice');
            return $this->data($this->owned($company,$id));
        });
    }
}
