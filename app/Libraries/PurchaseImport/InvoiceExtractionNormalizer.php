<?php
namespace App\Libraries\PurchaseImport;
use App\Libraries\PurchaseInvoiceService;
use RuntimeException;

class InvoiceExtractionNormalizer
{
    private array $locations=[];
    private function value(array $field,string $path)
    {
        $value=$field['valueString'] ?? $field['valueDate'] ?? $field['valueNumber'] ?? $field['valueCurrency']['amount'] ?? $field['content'] ?? '';
        $this->locations[$path]=['confidence'=>(float)($field['confidence'] ?? 0),'content'=>mb_substr((string)($field['content'] ?? ''),0,2000),
            'regions'=>array_values(array_map(static fn($region)=>['page'=>(int)($region['pageNumber'] ?? 1),'polygon'=>$region['polygon'] ?? []],$field['boundingRegions'] ?? []))];
        return $value;
    }
    private function decimal($value,int $scale=2): string
    {
        if ($value==='' || $value===null) return '';
        if (is_numeric($value)) return number_format((float)$value,$scale,'.','');
        $value=preg_replace('/[^0-9,.-]/','',(string)$value);
        if (str_contains($value,',') && str_contains($value,'.')) {
            $value=strrpos($value,',')>strrpos($value,'.')?str_replace(',','.',str_replace('.','',$value)):str_replace(',','',$value);
        } else $value=str_replace(',','.',$value);
        return is_numeric($value) && (float)$value>=0?number_format((float)$value,$scale,'.',''):'';
    }
    public function normalize(string $companyId,array $result,array $document): array
    {
        $documents=$result['documents'] ?? [];
        if (count($documents)!==1) throw new RuntimeException('El archivo debe contener una única factura. Separa los comprobantes y vuelve a subirlos.');
        if (count($result['pages'] ?? [])!==(int)$document['page_count']) throw new RuntimeException('La lectura no cubrió todas las páginas. Revisa el nivel del servicio o completa el documento manualmente.');
        $this->locations=[];$fields=$documents[0]['fields'] ?? [];$data=[];
        foreach (['VendorName'=>'supplier_name','VendorTaxId'=>'supplier_tax_id','CustomerTaxId'=>'receiver_tax_id','InvoiceId'=>'invoice_number','InvoiceDate'=>'issue_date','DueDate'=>'due_date','InvoiceTotal'=>'reviewed_total'] as $key=>$target) {
            if (isset($fields[$key])) $data[$target]=$this->value($fields[$key],$target);
        }
        if (isset($data['reviewed_total'])) $data['reviewed_total']=$this->decimal($data['reviewed_total']);
        $currency=$fields['InvoiceTotal']['valueCurrency']['currencyCode'] ?? '';
        if (preg_match('/^[A-Z]{3}$/D',$currency)) {
            $data['currency_code']=$currency;
            $this->locations['currency_code']=$this->locations['reviewed_total'] ?? ['confidence'=>0,'regions'=>[]];
            $this->locations['currency_code']['content']=$currency;
        }
        if (preg_match('/(\d{1,5})\s*[-\/]\s*(\d{1,8})/D',(string)($data['invoice_number'] ?? ''),$matches)) {
            $data['point_of_sale']=$matches[1];$data['invoice_sequence']=$matches[2];
            $this->locations['point_of_sale']=$this->locations['invoice_number'];
            $this->locations['invoice_sequence']=$this->locations['invoice_number'];
        }
        if (preg_match('/FACTURA\s+([ABCME])\b/ui',(string)($result['content'] ?? ''),$type)) $data['document_type']='FACTURA_'.strtoupper($type[1]);
        $taxId=PurchaseInvoiceService::taxId($data['supplier_tax_id'] ?? '');
        if ($taxId) {
            $matches=array_values(array_filter(db_connect()->table('suppliers')->where('company_id',$companyId)->where('active',1)->get()->getResultArray(),
                static fn($supplier)=>PurchaseInvoiceService::taxId($supplier['tax_id'] ?? '')===$taxId));
            if (count($matches)===1) $data['supplier_id']=$matches[0]['id'];
        }
        $items=[];$lineTax=0;
        foreach (($fields['Items']['valueArray'] ?? []) as $index=>$item) {
            if ($index>=300) throw new RuntimeException('El documento supera 300 conceptos.');
            $values=$item['valueObject'] ?? [];$line=[];
            foreach (['Description'=>'description','ProductCode'=>'supplier_code','Quantity'=>'quantity','UnitPrice'=>'unit_cost','Tax'=>'tax_amount','TaxRate'=>'tax_rate'] as $key=>$target) {
                if (isset($values[$key])) $line[$target]=$this->value($values[$key],'items.'.$index.'.'.$target);
            }
            foreach (['quantity'=>6,'unit_cost'=>6,'tax_amount'=>2,'tax_rate'=>4] as $key=>$scale) if (isset($line[$key])) $line[$key]=$this->decimal($line[$key],$scale);
            $lineTax+=(float)($line['tax_amount'] ?? 0);
            $code=(string)($line['supplier_code'] ?? '');
            if ($code && !empty($data['supplier_id'])) {
                $link=db_connect()->table('supplier_product_links')->where('company_id',$companyId)->where('supplier_id',$data['supplier_id'])->where('supplier_code',$code)->get()->getRowArray();
                if ($link && db_connect()->table('inventory_products')->where('company_id',$companyId)->where('id',$link['product_id'])->where('active',1)->countAllResults()) $line['product_id']=$link['product_id'];
            }
            $items[]=$line;
        }
        $data['items']=$items;
        if (isset($fields['TotalTax'])) {
            $tax=$this->decimal($this->value($fields['TotalTax'],'tax_total'));
            $difference=round((float)$tax-$lineTax,2);
            if ($difference>0) $data['adjustments']=[['kind'=>'tax','label'=>'Impuestos del documento: revisar clasificación','amount'=>number_format($difference,2,'.','')]];
        }
        $pages=array_map(static fn($page)=>['number'=>(int)$page['pageNumber'],'width'=>(float)$page['width'],'height'=>(float)$page['height'],'unit'=>$page['unit'] ?? 'inch'],$result['pages']);
        return ['suggestions'=>$data,'locations'=>$this->locations,'pages'=>$pages,
            'notice'=>'Revisa importes, clasificación de impuestos y coincidencias antes de registrar. La lectura no valida fiscalmente el documento.'];
    }
}
