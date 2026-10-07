<?php
namespace App\Libraries;
use RuntimeException;

final class PurchaseDocumentVault
{
    private string $keyFile;
    private string $directory;
    public function __construct(?string $keyFile = null, ?string $directory = null)
    {
        $this->keyFile = $keyFile ?? (string) env('purchaseDocuments.keyFile', WRITEPATH.'keys/purchase-documents.key');
        $this->directory = $directory ?? WRITEPATH.'private/purchase-documents/';
    }
    public function initialize(): void
    {
        if (is_file($this->keyFile)) { $this->key(); return; }
        if (!is_dir(dirname($this->keyFile)) && !mkdir(dirname($this->keyFile),0700,true)) throw new RuntimeException('No se pudo preparar la clave documental.');
        $handle = fopen($this->keyFile, 'x');
        if (!$handle) throw new RuntimeException('No se pudo crear la clave documental.');
        try { if (fwrite($handle, base64_encode(random_bytes(32))) !== 44) throw new RuntimeException('No se pudo guardar la clave.'); }
        finally { fclose($handle); }
        chmod($this->keyFile,0600);
    }
    private function key(): string
    {
        if (!is_file($this->keyFile)) throw new RuntimeException('El archivo seguro necesita configuracion. Contacta al administrador.');
        $key=base64_decode(trim((string)file_get_contents($this->keyFile)),true);
        if ($key === false || strlen($key)!==32) throw new RuntimeException('La clave documental no es valida.');
        return $key;
    }
    public function seal(string $plain, string $context): string
    {
        $master=$this->key(); $dataKey=random_bytes(32); $iv=random_bytes(12); $wrapIv=random_bytes(12);
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$dataKey,OPENSSL_RAW_DATA,$iv,$tag,$context,16);
        $wrapped=openssl_encrypt($dataKey,'aes-256-gcm',$master,OPENSSL_RAW_DATA,$wrapIv,$wrapTag,$context,16);
        if ($cipher===false || $wrapped===false) throw new RuntimeException('No se pudo cifrar el documento.');
        return json_encode(['v'=>1,'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'cipher'=>base64_encode($cipher),'key'=>base64_encode($wrapped),'wiv'=>base64_encode($wrapIv),'wtag'=>base64_encode($wrapTag)],JSON_THROW_ON_ERROR);
    }
    public function unseal(string $sealed, string $context): string
    {
        $data=json_decode($sealed,true,16,JSON_THROW_ON_ERROR);
        if (($data['v']??0)!==1) throw new RuntimeException('Formato de archivo no valido.');
        foreach(['iv','tag','cipher','key','wiv','wtag'] as $field) {
            $data[$field]=base64_decode($data[$field]??'',true);
            if ($data[$field]===false) throw new RuntimeException('Documento alterado.');
        }
        if (strlen($data['iv'])!==12 || strlen($data['wiv'])!==12 || strlen($data['tag'])!==16 || strlen($data['wtag'])!==16) throw new RuntimeException('Documento alterado.');
        $key=openssl_decrypt($data['key'],'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$data['wiv'],$data['wtag'],$context);
        if ($key===false || strlen($key)!==32) throw new RuntimeException('No se pudo verificar la clave del documento.');
        $plain=openssl_decrypt($data['cipher'],'aes-256-gcm',$key,OPENSSL_RAW_DATA,$data['iv'],$data['tag'],$context);
        if ($plain===false) throw new RuntimeException('La integridad del documento no es valida.');
        return $plain;
    }
    private function path(string $company, string $id): string
    {
        foreach([$company,$id] as $part) if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/D',$part)) throw new RuntimeException('Identificador invalido.');
        return rtrim($this->directory,'/\\').'/'.$company.'/'.$id.'.enc';
    }
    public function put(string $company, string $id, string $pdf): void
    {
        $sealed=$this->seal($pdf,$company.':'.$id.':pdf'); $path=$this->path($company,$id);
        if (!is_dir(dirname($path)) && !mkdir(dirname($path),0700,true)) throw new RuntimeException('No se pudo preparar el archivo.');
        $handle=fopen($path,'x'); if (!$handle) throw new RuntimeException('No se pudo archivar el PDF.');
        try { if (fwrite($handle,$sealed)!==strlen($sealed)) throw new RuntimeException('No se pudo guardar el PDF completo.'); }
        finally { fclose($handle); }
        chmod($path,0600);
    }
    public function get(string $company, string $id, string $hash): string
    {
        $path=$this->path($company,$id);
        if (!is_file($path)) throw new RuntimeException('El archivo no esta disponible.');
        $pdf=$this->unseal((string)file_get_contents($path),$company.':'.$id.':pdf');
        if (!hash_equals($hash,hash('sha256',$pdf))) throw new RuntimeException('La integridad del PDF no es valida.');
        return $pdf;
    }
    public function removeUncommitted(string $company,string $id): void
    {
        $path=$this->path($company,$id); if(is_file($path)) unlink($path);
    }
}
