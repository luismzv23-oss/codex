<?php
namespace App\Libraries\PurchaseImport;
use RuntimeException;
use Smalot\PdfParser\{Parser,Config};

/** Local results share the normalized field contract, without HTTP requests. */
class LocalInvoiceExtractor
{
    private \Config\PurchaseImport $config;
    private LocalOcrProcess $process;
    public function __construct(?\Config\PurchaseImport $config=null,?LocalOcrProcess $process=null)
    {
        $this->config=$config ?? config('PurchaseImport');$this->process=$process ?? new LocalOcrProcess($this->config);
    }
    public function configured(): bool {return $this->process->configured();}
    public function extract(string $path,array $document): array
    {
        if (!is_file($path) || filesize($path)>$this->config->maxBytes) throw new RuntimeException('Documento local inválido.');
        $count=(int)($document['page_count'] ?? 0);
        if ($count<1 || $count>$this->config->maxPages) throw new RuntimeException('Cantidad de páginas no admitida.');
        $deadline=microtime(true)+$this->config->documentTimeout;
        $base=rtrim($this->config->storageRoot,'/\\').'/work';
        if (!is_dir($base) && !mkdir($base,0700,true) && !is_dir($base)) throw new RuntimeException('No se pudo preparar la lectura privada.');
        $directory=$base.'/'.bin2hex(random_bytes(16));
        if (!mkdir($directory,0700)) throw new RuntimeException('No se pudo preparar la lectura privada.');
        $pages=[];$methods=[];$pdfPages=[];
        try {
            if (($document['mime_type'] ?? '')==='application/pdf') {
                $parserConfig=new Config();$parserConfig->setDecodeMemoryLimit(24*1024*1024);$parserConfig->setRetainImageContent(false);
                $pdfPages=(new Parser([],$parserConfig))->parseFile($path)->getPages();
                if (count($pdfPages)!==$count) throw new RuntimeException('El archivo no coincide con las páginas registradas.');
            } elseif (!in_array($document['mime_type'] ?? '',['image/png','image/jpeg'],true) || $count!==1) throw new RuntimeException('Formato local no admitido.');
            for ($number=1;$number<=$count;$number++) {
                if (microtime(true)>$deadline) throw new RuntimeException('La lectura local superó el tiempo permitido.');
                $text=$pdfPages?$pdfPages[$number-1]->getText():'';
                if (mb_check_encoding($text,'UTF-8') && preg_match_all('/[\pL\pN]/u',$text)>=25 && mb_strlen($text)<200000) {
                    $text=$this->process->run('pdftotext',['-f',(string)$number,'-l',(string)$number,'-layout','-enc','UTF-8',$path,'-'],$directory,$deadline);
                    $lines=array_map(static fn($line)=>['text'=>trim($line)],preg_split('/\R/u',$text));
                    $pages[]=['pageNumber'=>$number,'width'=>1,'height'=>1,'unit'=>'relative','lines'=>$lines];
                    $methods[]='pdf-text';
                    continue;
                }
                $image=$path;
                if ($pdfPages) {
                    $prefix=$directory.'/page';
                    $this->process->run('pdftoppm',['-f',(string)$number,'-l',(string)$number,'-singlefile','-scale-to','2500','-r','200','-png',$path,$prefix],$directory,$deadline);
                    $image=$prefix.'.png';
                    if (!is_file($image) || filesize($image)>60000000) throw new RuntimeException('No se pudo convertir la página a una imagen admitida.');
                }
                $size=@getimagesize($image);
                if (!$size || $size[0]*$size[1]>25000000) throw new RuntimeException('La imagen supera el tamaño permitido.');
                if (!preg_match('/^[a-z]{3}(?:\+[a-z]{3})*$/D',$this->config->ocrLanguage)) throw new RuntimeException('Configuración de idiomas inválida.');
                $tsv=$this->process->run('tesseract',[$image,'stdout','--tessdata-dir',$this->config->tessdataPath,'-l',$this->config->ocrLanguage,'--psm','3','tsv'],$directory,$deadline);
                $pages[]=['pageNumber'=>$number,'width'=>$size[0],'height'=>$size[1],'unit'=>'pixel','lines'=>$this->tsvLines($tsv,$number)];
                $methods[]='tesseract';
                if ($pdfPages && is_file($image)) unlink($image);
            }
            $result=(new InvoiceTextInterpreter())->interpret($pages);
            $result['provider']='local';$result['methods']=$methods;
            return $result;
        } finally {
            // Only direct files in this random, private directory are removed.
            foreach (new \DirectoryIterator($directory) as $file) if ($file->isFile() && !$file->isLink()) unlink($file->getPathname());
            rmdir($directory);
        }
    }
    public function tsvLines(string $tsv,int $page): array
    {
        $groups=[];
        foreach (explode("\n",$tsv) as $row) {
            $cells=explode("\t",rtrim($row,"\r"),12);
            if (count($cells)!==12 || $cells[0]!=='5' || trim($cells[11])==='' || (float)$cells[10]<0) continue;
            $key=implode('-',array_slice($cells,1,4));$x=max(0,(int)$cells[6]);$y=max(0,(int)$cells[7]);$right=$x+max(0,(int)$cells[8]);$bottom=$y+max(0,(int)$cells[9]);
            if (!isset($groups[$key])) $groups[$key]=['text'=>'','x'=>$x,'y'=>$y,'right'=>$right,'bottom'=>$bottom];
            $line=&$groups[$key];$line['text'].=($line['text']?($x-$line['right']>20?'  ':' '):'').$cells[11];
            $line['x']=min($line['x'],$x);$line['y']=min($line['y'],$y);$line['right']=max($line['right'],$right);$line['bottom']=max($line['bottom'],$bottom);unset($line);
        }
        return array_values(array_map(static fn($line)=>['text'=>$line['text'],'region'=>['pageNumber'=>$page,'polygon'=>[$line['x'],$line['y'],$line['right'],$line['y'],$line['right'],$line['bottom'],$line['x'],$line['bottom']]]],$groups));
    }
}
