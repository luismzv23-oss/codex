<?php
namespace App\Libraries\PurchaseImport;
use RuntimeException;

/** No shell, uploaded filenames or document contents are used to construct commands. */
class LocalOcrProcess
{
    private \Config\PurchaseImport $config;
    public function __construct(?\Config\PurchaseImport $config=null) {$this->config=$config ?? config('PurchaseImport');}
    public function configured(): bool
    {
        if (!function_exists('proc_open') || !is_file($this->config->tesseractPath) || !is_file($this->config->pdftoppmPath) || !is_file($this->config->pdftotextPath)
            || !preg_match('/^[a-z]{3}(?:\+[a-z]{3})*$/D',$this->config->ocrLanguage)) return false;
        foreach (explode('+',$this->config->ocrLanguage) as $language) if (!is_file($this->config->tessdataPath.'/'.$language.'.traineddata')) return false;
        return true;
    }
    public function run(string $tool,array $arguments,string $directory,float $deadline): string
    {
        $binary=['tesseract'=>$this->config->tesseractPath,'pdftoppm'=>$this->config->pdftoppmPath,'pdftotext'=>$this->config->pdftotextPath][$tool] ?? '';
        if (!$binary || !is_file($binary) || !function_exists('proc_open')) throw new RuntimeException('Faltan las herramientas locales de lectura. Revisa Tesseract y Poppler.');
        $binary=realpath($binary);
        $out=$directory.'/stdout-'.bin2hex(random_bytes(8));$err=$out.'.err';
        $process=null;
        try {
            $process=@proc_open(array_merge([$binary],$arguments),[0=>['pipe','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,$directory,null,
                ['bypass_shell'=>true,'suppress_errors'=>true,'create_new_console'=>false]);
            if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar la herramienta de lectura local.');
            fclose($pipes[0]);
            $until=min($deadline,microtime(true)+$this->config->processTimeout);
            while (true) {
                $status=proc_get_status($process);
                clearstatcache(true,$out);clearstatcache(true,$err);
                if ((is_file($out) && filesize($out)>4000000) || (is_file($err) && filesize($err)>1000000)) {
                    proc_terminate($process);throw new RuntimeException('La salida de lectura superó el tamaño permitido.');
                }
                if (!$status['running']) break;
                if (microtime(true)>$until) {proc_terminate($process);throw new RuntimeException('La lectura local superó el tiempo permitido. Puedes completar el borrador manualmente.');}
                usleep(50000);
            }
            if ($status['exitcode']!==0) throw new RuntimeException('No se pudo leer esta página con las herramientas locales. Verifica el archivo y los idiomas instalados.');
            return is_file($out)?(string)file_get_contents($out):'';
        } finally {
            if (is_resource($process)) { $status=proc_get_status($process);if ($status['running']) proc_terminate($process);proc_close($process); }
            if (is_file($out)) unlink($out);
            if (is_file($err)) unlink($err);
        }
    }
}
