<?php
namespace App\Libraries;
use RuntimeException;
use Smalot\PdfParser\{Parser,Config};

/** Store only parsed documents; no original filename is used as a filesystem path. */
class PurchaseDocumentStorage
{
    private $config;
    public function __construct(?\Config\PurchaseImport $config=null) {$this->config=$config ?? config('PurchaseImport');}
    public function inspect(string $path,string $original): array
    {
        $bytes=filesize($path);
        if (!$bytes || $bytes>$this->config->maxBytes) throw new RuntimeException('El archivo debe tener contenido y pesar como máximo 10 MB.');
        $extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        if (!isset($allowed[$extension]) || $allowed[$extension]!==$mime) throw new RuntimeException('Sube un PDF, JPG o PNG cuyo contenido coincida con el formato.');
        $pages=1;
        if ($mime==='application/pdf') {
            try {
                $config=new Config();$config->setDecodeMemoryLimit(24*1024*1024);$config->setRetainImageContent(false);
                $document=(new Parser([],$config))->parseFile($path);
                $pages=count($document->getPages());
                foreach ($document->getObjects() as $object) {
                    $header=$object->getHeader();
                    foreach (['JavaScript','JS','OpenAction','AA','AcroForm','EmbeddedFiles','Launch'] as $key) {
                        if ($header->has($key)) throw new RuntimeException('El PDF contiene acciones, adjuntos o formularios. Exporta una copia estática.');
                    }
                }
                unset($document);
            } catch (RuntimeException $e) {throw $e;}
            catch (\Throwable $e) {throw new RuntimeException('No se pudo validar el PDF. Utiliza un archivo sin contraseña o una imagen legible.');}
            if ($pages<1 || $pages>$this->config->maxPages) throw new RuntimeException('El PDF debe tener entre 1 y 20 páginas.');
        } else {
            $size=@getimagesize($path);
            if (!$size || $size[0]<50 || $size[1]<50 || $size[0]>10000 || $size[1]>10000 || $size[0]*$size[1]>25000000) throw new RuntimeException('La imagen debe ser legible y tener entre 50 y 10000 píxeles por lado, hasta 25 megapíxeles.');
        }
        return ['sha256'=>hash_file('sha256',$path),'mime_type'=>$mime,'byte_size'=>$bytes,'page_count'=>$pages,
            'original_name'=>mb_substr(preg_replace('/[^\p{L}\p{N} ._-]/u','_',basename(str_replace('\\','/',$original))),0,180),'extension'=>$extension==='jpeg'?'jpg':$extension];
    }
    public function store(string $companyId,string $source,array $info): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D',$companyId)) throw new RuntimeException('Empresa inválida.');
        $directory=rtrim($this->config->storageRoot,'/\\').DIRECTORY_SEPARATOR.$companyId;
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        $relative=$companyId.'/'.app_uuid().'.'.$info['extension'];
        $destination=rtrim($this->config->storageRoot,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (!copy($source,$destination)) throw new RuntimeException('No se pudo guardar el documento.');
        @chmod($destination,0600);
        return $relative;
    }
    public function path(array $document): string
    {
        $root=realpath($this->config->storageRoot);
        $path=realpath($this->config->storageRoot.DIRECTORY_SEPARATOR.$document['storage_path']);
        if (!$root || !$path || !str_starts_with(strtolower(str_replace('\\','/',$path)),strtolower(str_replace('\\','/',$root)).'/')
            || !is_file($path)) throw new RuntimeException('Documento no disponible.');
        return $path;
    }
    public function discard(string $relative): void
    {
        $path=$this->path(['storage_path'=>$relative]);
        if (!unlink($path)) throw new RuntimeException('No se pudo retirar un documento temporal.');
    }
}
