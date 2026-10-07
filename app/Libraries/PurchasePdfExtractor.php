<?php
namespace App\Libraries;
use RuntimeException;
final class PurchasePdfExtractor
{
    public function preview(string $pdf,int $page): string
    {
        $image=$this->run($pdf,['--preview',(string)$page]);
        if(!str_starts_with($image,"\x89PNG")) throw new RuntimeException('No se pudo generar la vista previa. Descarga el original para revisarlo.');
        return $image;
    }
    public function extract(string $pdf): array
    {
        $result=json_decode($this->run($pdf),true);
        if (!is_array($result) || isset($result['error'])) throw new RuntimeException('No se pudo leer este archivo automaticamente. Puedes continuar manualmente.');
        return $result;
    }
    private function run(string $pdf,array $arguments=[]): string
    {
        $python=(string)env('purchaseDocuments.python',ROOTPATH.'.venv-pdf/'.(PHP_OS_FAMILY==='Windows'?'Scripts/python.exe':'bin/python'));
        if (!is_file($python) || !function_exists('proc_open')) throw new RuntimeException('La lectura automatica no esta disponible. Puedes completar el documento manualmente.');
        $process=proc_open(array_merge([$python,ROOTPATH.'scripts/pdf/extract.py'],$arguments),[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar la lectura del PDF.');
        $out=''; $err=''; $start=microtime(true);
        try {
            $offset=0;
            while($offset<strlen($pdf)) { $written=fwrite($pipes[0],substr($pdf,$offset,65536)); if(!$written) break; $offset+=$written; }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
            do {
                $out.=stream_get_contents($pipes[1]); $err.=stream_get_contents($pipes[2]);
                if (strlen($out)>15000000 || strlen($err)>1000000 || microtime(true)-$start>90) {
                    proc_terminate($process); throw new RuntimeException('La lectura excedio el tiempo disponible. Puedes completar los datos manualmente.');
                }
                $state=proc_get_status($process);
                if (!$state['running']) break;
                usleep(50000);
            } while(true);
            $out.=stream_get_contents($pipes[1]);
        } finally {
            foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
        return $out;
    }
    public function suggest(string $text, array $suppliers, array $products): array
    {
        $draft=['supplier_id'=>'','invoice_number'=>'','issue_date'=>'','currency_code'=>'ARS','exchange_rate'=>1,'items'=>[]];
        preg_match_all('/\b\d{2}[- ]?\d{8}[- ]?\d\b/',$text,$taxes);
        $taxes=array_map(static fn($v)=>preg_replace('/\D/','',$v),$taxes[0]);
        $matches=array_values(array_filter($suppliers,static fn($s)=>in_array(preg_replace('/\D/','',$s['tax_id']??''),$taxes,true)));
        if(count($matches)===1) $draft['supplier_id']=$matches[0]['id'];
        if(preg_match('/(?<!\d)(\d{4,5})\s*-\s*(\d{8})\b/',$text,$m)) $draft['invoice_number']=$m[1].'-'.$m[2];
        if(preg_match('/Fecha\s*facturac[iIl1][o\x{00f3}]n\s*:?\s*(\d{2})[\/.-](\d{2})[\/.-](\d{4})/iu',$text,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])) $draft['issue_date']=$m[3].'-'.$m[2].'-'.$m[1];
        if(preg_match('/(?:Fecha(?: de emisi[o\x{00f3}]n)?|Emisi[o\x{00f3}]n)\s*:?\s*(\d{2})[\/.-](\d{2})[\/.-](\d{4})/iu',$text,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])) $draft['issue_date']=$m[3].'-'.$m[2].'-'.$m[1];
        if(preg_match('/\b(?:TOTAL A PAGAR|IMPORTE TOTAL|TOTAL)\s*:?\s*\$?\s*([\d.,]+)/iu',$text,$m)) $draft['expected_total']=$this->number($m[1]);
        $supplierTable=(bool)preg_match('/Cantidad\s+Unitario\s+Descuento\s+Neto\s+Internos\s+IVA\s+SubTotal/iu',$text);
        foreach(preg_split('/\R/',$text) as $line) {
            if($supplierTable && preg_match('/^Totales\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s*$/iu',trim($line),$m)) {
                $draft['expected_subtotal']=$this->number($m[3]);
                $draft['source_internal_tax']=$this->number($m[4]);
                $draft['expected_tax']=$this->number($m[5]);
                $draft['expected_total']=$this->number($m[6]);
            }
            if($supplierTable && preg_match('/^(\d+)\s+(.+?)\s{2,}([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s*$/u',trim($line),$m)) {
                $qty=$this->number($m[3]);$cost=$this->number($m[4]);$discount=$this->number($m[5]);$net=$this->number($m[6]);$internal=$this->number($m[7]);$vat=$this->number($m[8]);$total=$this->number($m[9]);
                $rates=array_values(array_filter([0,2.5,5,10.5,21,27],static fn($rate)=>abs(round($net*$rate/100,2)-$vat)<=.01));
                if($qty<=0 || $net<=0 || $discount!=0 || abs(round($qty*$cost,2)-$net)>.02 || abs(round($net+$internal+$vat,2)-$total)>.02 || count($rates)!==1) continue;
                $id='';foreach($products as $product) if((string)($product['sku']??'')===$m[1]) {$id=$product['id'];break;}
                $draft['items'][]=['product_id'=>$id,'description'=>mb_substr($m[1].' '.trim($m[2]),0,255),'quantity'=>$qty,'unit_cost'=>$cost,'tax_rate'=>$rates[0],'pack'=>1,'discount'=>0,'source_internal_tax'=>$internal,'source_total'=>$total];
                if(count($draft['items'])>=200) break;
                continue;
            }
            // Only suggest unambiguous rows with quantity, net cost, tax rate and total.
            if(!preg_match('/^(.+?)\s{2,}([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s*%?\s+([\d.,]+)\s*$/u',trim($line),$m)) continue;
            $qty=$this->number($m[2]);$cost=$this->number($m[3]);$rate=$this->number($m[4]);$total=$this->number($m[5]);
            if($qty<=0 || $cost<0 || !in_array($rate,[0,2.5,5,10.5,21,27]) || abs(round($qty*$cost,2)+round(round($qty*$cost,2)*$rate/100,2)-$total)>.02) continue;
            $id='';
            foreach($products as $product) if(!empty($product['sku']) && preg_match('/(?<![\w-])'.preg_quote($product['sku'],'/').'(?![\w-])/iu',$m[1])) { $id=$product['id'];break; }
            $draft['items'][]=['product_id'=>$id,'description'=>mb_substr(trim($m[1]),0,255),'quantity'=>$qty,'unit_cost'=>$cost,'tax_rate'=>$rate,'pack'=>1,'discount'=>0];
            if(count($draft['items'])>=200) break;
        }
        if(($draft['source_internal_tax']??0)>0) $draft['extraction_warning']='El original incluye impuestos internos por '.number_format($draft['source_internal_tax'],2,',','.').'. Se leyeron por separado del IVA. Este asistente aun no los incorpora al registro: conserva el borrador; no cambies el IVA ni el total para forzar una coincidencia.';
        return $draft;
    }
    private function number(string $value): float
    {
        if(str_contains($value,',')) return (float)str_replace(',','.',str_replace('.','',$value));
        return (float)$value;
    }
}
