<?php
namespace App\Libraries\Core\Events;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class OutboxPublisher
{
    private BaseConnection $db;
    private ConsumerRegistry $registry;

    public function __construct(?BaseConnection $db = null, ?ConsumerRegistry $registry = null)
    {
        $this->db = $db ?? db_connect();
        $this->registry = $registry ?? new ConsumerRegistry();
        helper('app');
    }

    public function publish(string $companyId, string $event, string $operationKey, string $sourceType, string $sourceId, array $payload): string
    {
        if ($this->db->transDepth < 1) throw new RuntimeException('El evento requiere la transacción de su operación.');
        foreach ([$companyId,$sourceId] as $id) if ($id === '' || strlen($id)>36) throw new RuntimeException('Origen del evento inválido.');
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,99}$/D',$event) || strlen($operationKey)>120 || $operationKey==='' || !preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$sourceType)) throw new RuntimeException('Contrato del evento inválido.');
        $consumers = $this->registry->forEvent($event);
        if (!$consumers) throw new RuntimeException('Evento sin consumidores desplegados.');
        $canonical = static function (array $data) use (&$canonical): array {
            if ($data !== [] && array_keys($data) !== range(0,count($data)-1)) ksort($data);
            foreach ($data as &$value) if (is_array($value)) $value=$canonical($value);
            return $data;
        };
        $json = json_encode($canonical($payload),JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($json)>262144) throw new RuntimeException('Payload demasiado grande.');
        $hash = hash('sha256',$sourceType.'|'.$sourceId.'|1|'.$json);
        // Serialize publishers for this company; consistent with existing business locks.
        $sql='SELECT id FROM '.$this->db->protectIdentifiers($this->db->prefixTable('companies')).' WHERE id = ?';
        if ($this->db->DBDriver === 'MySQLi') $sql.=' FOR UPDATE';
        if (!$this->db->query($sql,[$companyId])->getRowArray()) throw new RuntimeException('Empresa del evento no disponible.');
        $existing=$this->db->table('integration_outbox')->where('company_id',$companyId)->where('event',$event)->where('operation_key',$operationKey)->get()->getRowArray();
        if ($existing) {
            if (!hash_equals($existing['payload_hash'],$hash)) throw new RuntimeException('La clave del evento ya representa otros datos.');
            return $existing['id'];
        }
        $id=app_uuid(); $now=date('Y-m-d H:i:s');
        if (!$this->db->table('integration_outbox')->insert([
            'id'=>$id,'company_id'=>$companyId,'event'=>$event,'schema_version'=>1,'source_type'=>$sourceType,
            'source_id'=>$sourceId,'operation_key'=>$operationKey,'payload'=>$json,'payload_hash'=>$hash,'occurred_at'=>$now,
        ])) throw new RuntimeException('No se pudo guardar el evento.');
        foreach ($consumers as $consumer=>$entry) {
            if (!$this->db->table('integration_deliveries')->insert([
                'id'=>app_uuid(),'company_id'=>$companyId,'event_id'=>$id,'consumer'=>$consumer,
                'consumer_version'=>$entry['version'],'status'=>'pending','attempts'=>0,'available_at'=>$now,
            ])) throw new RuntimeException('No se pudo guardar la entrega.');
        }
        return $id;
    }
}
