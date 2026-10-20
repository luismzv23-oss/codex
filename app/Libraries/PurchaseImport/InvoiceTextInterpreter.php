<?php
namespace App\Libraries\PurchaseImport;
use RuntimeException;

/** Conservative suggestions: ambiguous amounts and unrecognized tables remain manual. */
class InvoiceTextInterpreter
{
    public function money(string $value,int $scale=2): ?string
    {
        $value=trim(preg_replace('/(?:ARS|USD|US\$|\$|\s)/ui','',$value));
        if (!preg_match('/^\d+(?:[.,]\d+)*$/D',$value)) return null;
        if (str_contains($value,',') && str_contains($value,'.')) {
            $decimal=strrpos($value,',')>strrpos($value,'.')?',':'.';
            $value=str_replace($decimal===','?'.':',','',$value);
            $value=str_replace($decimal,'.',$value);
        } elseif ($scale===2 && preg_match('/^\d{1,3}(?:[.,]\d{3})+$/D',$value)) $value=str_replace([',','.'],'',$value);
        else $value=str_replace(',','.',$value);
        if (!preg_match('/^\d+(?:\.\d{1,6})?$/D',$value) || (float)$value>999999999999) return null;
        return number_format((float)$value,$scale,'.','');
    }
    private function field(string $value,array $line): array
    {
        return ['valueString'=>$value,'content'=>$line['text'],'detection_status'=>'detected',
            'boundingRegions'=>isset($line['region'])?[$line['region']]:[]];
    }
    public function interpret(array $pages): array
    {
        $lines=[];$content='';$fields=[];$warnings=[];$identities=[];$totals=[];$taxes=[];$cuits=[];$items=[];$headers=null;$dates=[];$names=[];
        $customerSection=false;$explicitAdjustments=[];$headerRate=null;
        foreach ($pages as $page) foreach ($page['lines'] ?? [] as $line) {
            $line['text']=trim((string)($line['text'] ?? ''));
            if ($line['text']==='') continue;
            if (!mb_check_encoding($line['text'],'UTF-8')) throw new RuntimeException('El texto leído no tiene una codificación válida.');
            $content.=$line['text']."\n";
            if (strlen($content)>1000000) throw new RuntimeException('El texto del documento supera el límite permitido.');
            $lines[]=$line+['page'=>$page['pageNumber']];
        }
        if (mb_strlen(trim($content))<12) throw new RuntimeException('No se encontró texto legible. Sube una imagen más clara o completa el borrador manualmente.');
        foreach ($lines as $index=>$line) {
            $text=$line['text'];
            if (preg_match('/^(?:SR\.\s*\(?S\)?|SE[ÑN]OR(?:ES)?|CLIENTE|DESTINATARIO|RECEPTOR)\b/ui',$text)) $customerSection=true;
            if (preg_match('/\b(?:SR\.\(S\)|CLIENTE|DESTINATARIO|RECEPTOR)/ui',$text)) $customerSection=true;
            if (preg_match('/\bFACTURA\s+([ABCME])\b/ui',$text,$m)) $fields['DocumentType']=$this->field('FACTURA_'.strtoupper($m[1]),$line);
            if (preg_match('/^(?:(?:FACTURA|COMPROBANTE|COMP\.?)(?:\s+[ABCME])?\s*(?:N[º°]|NRO\.?|N[ÚU]MERO)?|N[º°]|NRO\.?|N[ÚU]MERO)\s*:?\s*(\d{1,5})\s*[-\/]\s*(\d{1,8})(?![\d\/-])/ui',$text,$m)
                || (preg_match('/^\s*(\d{1,5})\s*[-\/]\s*(\d{1,8})\s*$/u',$text,$m) && preg_match('/FACTURA|COMPROBANTE/ui',implode(' ',array_column(array_slice($lines,max(0,$index-3),3),'text'))))) {
                $identities[str_pad($m[1],5,'0',STR_PAD_LEFT).'-'.str_pad($m[2],8,'0',STR_PAD_LEFT)]=$line;
            } elseif (preg_match('/PUNTO\s+DE\s+VENTA\s*:?\s*(\d{1,5}).{0,40}(?:COMP\.?|COMPROBANTE)\s*(?:NRO\.?|N[º°])?\s*:?\s*(\d{1,8})\b/ui',$text,$m)) {
                $identities[str_pad($m[1],5,'0',STR_PAD_LEFT).'-'.str_pad($m[2],8,'0',STR_PAD_LEFT)]=$line;
            }
            if (preg_match('/\bCUIT\s*:?\s*(\d{2}[\s-]?\d{8}[\s-]?\d)\b/ui',$text,$m)) {
                $taxId=preg_replace('/\D/','',$m[1]);
                $kind=$customerSection || preg_match('/CLIENTE|RECEPTOR|DESTINATARIO/ui',$text)?'CustomerTaxId':'VendorTaxId';
                $cuits[$kind][$taxId]=$line;
            }
            if (preg_match('/^(?:RAZ[ÓO]N\s+SOCIAL|PROVEEDOR|EMISOR)\s*:\s*(.{3,160})$/ui',$text,$m)) $names[trim($m[1])]=$line;
            $dateText=$text;
            if (preg_match('/^FECHA\s*:?\s*$/ui',$text)) foreach (array_slice($lines,$index+1,3) as $following) {
                if (preg_match('/^\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4}$/D',$following['text'])) {$dateText.=' '.$following['text'];break;}
            }
            $certificateDate=preg_match('/CAEA?\b/ui',implode(' ',array_column(array_slice($lines,max(0,$index-2),2),'text')));
            if (!$certificateDate && preg_match('/\b(?:FECHA(?:\s+DE\s+EMISI[ÓO]N)?|EMISI[ÓO]N|VENCIMIENTO|VTO\.?)\s*:?\s*(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\b/ui',$dateText,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])) {
                $key=preg_match('/VENCIMIENTO|VTO/ui',$m[0])?'DueDate':'InvoiceDate';
                $date=sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);$dates[$key][$date]=$line;
            }
            if (preg_match('/^\s*(?:IMPORTE\s+TOTAL|TOTAL(?:\s+(?:GENERAL|A\s+PAGAR|FACTURA|DEL\s+COMPROBANTE))?)\s*:?\s*((?:(?:ARS|USD|US\$|\$)\s*)?\d[\d.,\s]*)\s*$/ui',$text,$m)) {
                $amount=$this->money($m[1]);if ($amount!==null) $totals[$amount]=$line;
            }
            if (preg_match('/^\s*(?:TOTAL\s+IVA|IVA(?:\s+\d+(?:[.,]\d+)?\s*%)?)\s*:?\s*((?:\$\s*)?\d[\d.,\s]*)\s*$/ui',$text,$m)) {
                $amount=$this->money($m[1]);if ($amount!==null) $taxes[$amount]=$line;
                if (preg_match('/^IVA\s+(\d+(?:[.,]\d+)?)\s*%/ui',$text,$rate)) $headerRate=str_replace(',','.',$rate[1]);
            }
            if (preg_match('/^(PERC\.?\s*IVA|PERC\.?\s*I\.?B\.?(?:\s+[^0-9]+)?|IMP\.?\s*INT\.?)\s*:?\s+(\d[\d.,]*)\s*$/ui',$text,$adjustment)) {
                $amount=$this->money($adjustment[2]);
                if ($amount!==null) $explicitAdjustments[mb_strtoupper(trim($adjustment[1]))]=['kind'=>'tax','label'=>trim($adjustment[1]).' (revisar)','amount'=>$amount];
            }
            // Only tables with explicit headers and matching columns produce item suggestions.
            $columns=preg_split('/\s{2,}|\t+|\s*\|\s*/u',$text);
            $candidate=[];
            foreach ($columns as $position=>$label) {
                $label=mb_strtoupper(trim($label));
                foreach (['Description'=>'/^(?:DESCRIPCI[ÓO]N|DETALLE|PRODUCTO)$/u','ProductCode'=>'/^(?:C[ÓO]DIGO|SKU|COD\.?)$/u',
                    'Quantity'=>'/^(?:CANTIDAD|CANT\.?)$/u','UnitPrice'=>'/^(?:PRECIO\s+UNITARIO|PRECIO\s+UNIT\.?|P\.?\s*UNIT\.?|COSTO\s+UNITARIO)$/u',
                    'TaxRate'=>'/^IVA\s*%$/u','Tax'=>'/^(?:IMPORTE\s+IVA|IVA\s+IMPORTE)$/u','LineAmount'=>'/^(?:IMPORTE|SUBTOTAL)$/u',
                    'Discount'=>'/^%?\s*(?:DTO\.?|DESC\.?|DESCUENTO)\s*%?$/u'] as $key=>$pattern) if (preg_match($pattern,$label)) $candidate[$key]=$position;
            }
            if (isset($candidate['Description'],$candidate['Quantity'],$candidate['UnitPrice'])) {$headers=$candidate+['_count'=>count($columns)];continue;}
            if ($headers && $items && count($columns)===1 && !preg_match('/\d|IVA|TOTAL|NETO|PERC\.|IMP\./ui',$text)) {
                $last=count($items)-1;$items[$last]['valueObject']['Description']['valueString'].=' '.$text;
                continue;
            }
            if ($headers && count($columns)===$headers['_count']) {
                $quantity=$this->money($columns[$headers['Quantity']],6);$cost=$this->money($columns[$headers['UnitPrice']],6);
                $description=trim($columns[$headers['Description']]);
                $discount=isset($headers['Discount'])?$this->money($columns[$headers['Discount']]):'0.00';
                if ($discount!==null && (float)$discount!==0.0) {$warnings[]='Hay descuentos por renglón: revisa el costo neto y los importes antes de registrar.';continue;}
                $lineAmount=isset($headers['LineAmount'])?$this->money($columns[$headers['LineAmount']]):null;
                $net=round((float)$quantity*(float)$cost,2);
                $rowTax=isset($headers['Tax'])?$this->money($columns[$headers['Tax']]):null;
                $rowRate=isset($headers['TaxRate'])?$this->money(str_replace('%','',$columns[$headers['TaxRate']])):null;
                $gross=$net+($rowTax!==null?(float)$rowTax:round($net*(float)$rowRate/100,2));
                if ($quantity!==null && (float)$quantity>0 && $cost!==null && $lineAmount!==null && min(abs($net-(float)$lineAmount),abs($gross-(float)$lineAmount))>.02) {
                    $warnings[]='Un renglón no coincide con cantidad por precio: completa ese concepto manualmente.';continue;
                }
                if ($quantity!==null && (float)$quantity>0 && $cost!==null && $description!=='' && !preg_match('/^(?:TOTAL|SUBTOTAL|IVA)\b/ui',$description)) {
                    $object=['Description'=>$this->field($description,$line),'Quantity'=>$this->field($quantity,$line),'UnitPrice'=>$this->field($cost,$line)];
                    foreach (['ProductCode','TaxRate','Tax'] as $key) if (isset($headers[$key])) {
                        $value=trim($columns[$headers[$key]]);
                        if ($key!=='ProductCode') $value=$this->money(str_replace('%','',$value));
                        if ($value!==null && $value!=='') $object[$key]=$this->field($value,$line);
                    }
                    $items[]=['valueObject'=>$object];if (count($items)>300) throw new RuntimeException('El documento supera 300 conceptos.');
                }
            }
        }
        if (count($identities)>1) throw new RuntimeException('Se detectaron números de factura diferentes. Sube una única factura por archivo.');
        if (!isset($fields['DocumentType']) && preg_match('/\b([ABCME])\s+FACTURA\b/ui',$content,$type)) $fields['DocumentType']=$this->field('FACTURA_'.$type[1],['text'=>$type[0]]);
        if ($identities) {$number=(string)array_key_first($identities);$fields['InvoiceId']=$this->field($number,$identities[$number]);}
        if (count($names)===1) {$name=(string)array_key_first($names);$fields['VendorName']=$this->field($name,$names[$name]);}
        foreach ($dates as $key=>$values) {
            if (count($values)===1) {$date=(string)array_key_first($values);$fields[$key]=$this->field($date,$values[$date]);}
            else $warnings[]='Se detectaron fechas diferentes para el mismo dato. Completa la fecha desde el original.';
        }
        foreach ($cuits as $key=>$values) {
            if (count($values)===1) {$taxId=(string)array_key_first($values);$fields[$key]=$this->field($taxId,$values[$taxId]);}
            else $warnings[]='Se detectaron varios CUIT sin una identificación inequívoca de su titular. Revísalos en el original.';
        }
        if (count($totals)===1) {
            $amount=(string)array_key_first($totals);$fields['InvoiceTotal']=$this->field($amount,$totals[$amount]);
            $usd=preg_match('/(?:MONEDA\s*:?\s*(?:USD|D[ÓO]LARES)|(?:TOTAL.*?)\bUSD\b|US\$)/ui',$content);
            $ars=preg_match('/(?:MONEDA\s*:?\s*(?:ARS|PESOS)|(?:TOTAL.*?)\bARS\b)/ui',$content);
            if ($usd && !$ars) $fields['InvoiceTotal']['valueCurrency']=['amount'=>(float)$amount,'currencyCode'=>'USD'];
            elseif ($ars && !$usd) $fields['InvoiceTotal']['valueCurrency']=['amount'=>(float)$amount,'currencyCode'=>'ARS'];
            elseif ($ars && $usd) $warnings[]='Se detectaron referencias a monedas diferentes. Verifica la moneda del comprobante.';
        } elseif (count($totals)>1) $warnings[]='Se detectaron totales diferentes. El total debe completarse desde el original.';
        if (count($taxes)===1) {$amount=(string)array_key_first($taxes);$fields['TotalTax']=$this->field($amount,$taxes[$amount]);}
        if ($items && $headerRate!==null && count($taxes)===1) {
            $calculated=0;
            foreach ($items as $item) {
                $value=$item['valueObject'];$calculated+=round(round((float)$value['Quantity']['valueString']*(float)$value['UnitPrice']['valueString'],2)*(float)$headerRate/100,2);
            }
            // A single rate is applied only if the complete recognized detail reconciles its header VAT.
            if (abs($calculated-(float)array_key_first($taxes))<=.02) foreach ($items as &$item) {
                if (!isset($item['valueObject']['TaxRate']) && !isset($item['valueObject']['Tax'])) $item['valueObject']['TaxRate']=$this->field($headerRate,['text'=>'IVA '.$headerRate.'%']);
            }
            unset($item);
        }
        if ($items) $fields['Items']=['valueArray'=>$items];
        else $warnings[]='No se identificó una tabla de conceptos con columnas inequívocas. Completa los renglones manualmente.';
        return ['content'=>$content,'pages'=>array_map(static fn($page)=>array_diff_key($page,['lines'=>true]),$pages),
            'documents'=>[['fields'=>$fields]],'header_tax_kind'=>'vat','adjustments'=>array_values($explicitAdjustments),'warnings'=>array_values(array_unique($warnings))];
    }
}
