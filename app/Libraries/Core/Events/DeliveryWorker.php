<?php
namespace App\Libraries\Core\Events;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class DeliveryWorker
{
    public const MAX_ATTEMPTS = 5;
    private BaseConnection $db;
    private ConsumerRegistry $registry;

    public function __construct(?BaseConnection $db = null, ?ConsumerRegistry $registry = null)
    {
        $this->db=$db ?? db_connect(); $this->registry=$registry ?? new ConsumerRegistry();
        helper('app');
    }

    private function transaction(callable $fn)
    {
        if ($this->db->transDepth !== 0 || !$this->db->transStatus()) throw new RuntimeException('El worker requiere una conexión sin transacción pendiente.');
        $strict=$this->db->transStrict; $this->db->transStrict(false);
        if (!$this->db->transBegin()) {
            $this->db->transStrict($strict);
            throw new RuntimeException('No se pudo iniciar la entrega.');
        }
        try {
            $value=$fn();
            if (!$this->db->transStatus() || $this->db->transDepth!==1) throw new RuntimeException('Transacción de consumidor incompleta.');
            if (!$this->db->transCommit()) throw new RuntimeException('No se pudo confirmar la entrega.');
            return $value;
        } catch (\Throwable $e) {
            while ($this->db->transDepth>0 && $this->db->transRollback()) {}
            // CI 4.4 keeps failed SQL status after a manual rollback. Reset via a clean,
            // empty non-strict group before recording the failure on this connection.
            if (!$this->db->transStatus()) { $this->db->transStart(); $this->db->transComplete(); }
            throw $e;
        } finally { $this->db->transStrict($strict); }
    }

    private function locked(string $table, string $companyId, string $id): ?array
    {
        $sql='SELECT * FROM '.$this->db->protectIdentifiers($this->db->prefixTable($table)).' WHERE company_id = ? AND id = ?';
        if ($this->db->DBDriver==='MySQLi') $sql.=' FOR UPDATE';
        return $this->db->query($sql,[$companyId,$id])->getRowArray();
    }

    public function processBatch(string $companyId, int $limit=50): array
    {
        if ($companyId==='') throw new RuntimeException('Indica la empresa.');
        $limit=max(1,min(200,$limit)); $now=date('Y-m-d H:i:s');
        $rows=$this->db->table('integration_deliveries')->select('id')->where('company_id',$companyId)
            ->groupStart()->groupStart()->whereIn('status',['pending','retry'])->where('available_at <=',$now)->groupEnd()
            ->orGroupStart()->where('status','processing')->where('lease_until <=',$now)->groupEnd()->groupEnd()
            ->orderBy('available_at')->orderBy('id')->limit($limit)->get()->getResultArray();
        $counts=['claimed'=>0,'succeeded'=>0,'retry'=>0,'dead'=>0,'blocked'=>0,'stale'=>0];
        foreach ($rows as $row) {
            $claim=$this->claim($companyId,$row['id']);
            if (!$claim) continue;
            $counts['claimed']++;
            $status=$this->deliver($companyId,$claim);
            $counts[$status]++;
        }
        return $counts;
    }

    /** Public for scheduler adapters; token must always be revalidated by deliver(). */
    public function claim(string $companyId, string $id): ?array
    {
        return $this->transaction(function () use ($companyId,$id) {
            $row=$this->locked('integration_deliveries',$companyId,$id); $now=date('Y-m-d H:i:s');
            if (!$row) return null;
            $expired=$row['status']==='processing' && $row['lease_until']!==null && $row['lease_until']<=$now;
            if (!$expired && !(in_array($row['status'],['pending','retry'],true) && $row['available_at']<=$now)) return null;
            if ($expired) $this->db->table('integration_delivery_attempts')->where('company_id',$companyId)->where('delivery_id',$id)->where('result','processing')->update(['result'=>'abandoned','finished_at'=>$now]);
            if ((int)$row['attempts']>=self::MAX_ATTEMPTS) {
                $this->update($companyId,$id,['status'=>'dead','lease_token'=>null,'lease_until'=>null,'last_error'=>'Se agotaron los intentos de entrega.']);
                return null;
            }
            $token=bin2hex(random_bytes(32)); $attempt=app_uuid();
            $claimed=$this->db->table('integration_deliveries')->where('company_id',$companyId)->where('id',$id)
                ->where('status',$row['status'])->where('attempts',$row['attempts'])->update([
                    'status'=>'processing','attempts'=>(int)$row['attempts']+1,'lease_token'=>$token,'lease_until'=>date('Y-m-d H:i:s',time()+300),
                ]);
            if (!$claimed) throw new RuntimeException('No se pudo reservar la entrega.');
            if ($this->db->affectedRows()!==1) return null;
            if (!$this->db->table('integration_delivery_attempts')->insert(['id'=>$attempt,'company_id'=>$companyId,'delivery_id'=>$id,'attempt'=>(int)$row['attempts']+1,'started_at'=>$now,'result'=>'processing'])) throw new RuntimeException('No se pudo registrar el intento.');
            return ['id'=>$id,'token'=>$token,'attempt_id'=>$attempt];
        });
    }

    public function deliver(string $companyId, array $claim): string
    {
        $blocked=false;
        try {
            return $this->transaction(function () use ($companyId,$claim,&$blocked) {
                // Same lock order as business producers; also serializes certificate numbering.
                $sql='SELECT id FROM '.$this->db->protectIdentifiers($this->db->prefixTable('companies')).' WHERE id = ?';
                if ($this->db->DBDriver==='MySQLi') $sql.=' FOR UPDATE';
                if (!$this->db->query($sql,[$companyId])->getRowArray()) throw new RuntimeException('Empresa no disponible.');
                $row=$this->locked('integration_deliveries',$companyId,$claim['id']);
                if (!$this->owns($row,$claim)) return 'stale';
                $event=$this->db->table('integration_outbox')->where('company_id',$companyId)->where('id',$row['event_id'])->get()->getRowArray();
                if (!$event || (int)$event['schema_version']!==1) { $blocked=true; throw new RuntimeException('Contrato de evento no disponible.'); }
                $handler=$this->registry->handler($event['event'],$row['consumer'],(int)$row['consumer_version']);
                if (!$handler) { $blocked=true; throw new RuntimeException('Consumidor no disponible.'); }
                $hash=hash('sha256',$event['source_type'].'|'.$event['source_id'].'|1|'.$event['payload']);
                if (!hash_equals($event['payload_hash'],$hash)) { $blocked=true; throw new RuntimeException('Integridad del evento inválida.'); }
                $payload=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);
                $result=$handler($event,$payload);
                if ($result===false || (is_array($result) && isset($result['ok']) && !$result['ok'])) throw new RuntimeException('El consumidor no confirmó su operación.');
                $this->update($companyId,$row['id'],['status'=>'succeeded','completed_at'=>date('Y-m-d H:i:s'),'lease_token'=>null,'lease_until'=>null,'last_error'=>null]);
                $this->finishAttempt($companyId,$claim['attempt_id'],'succeeded');
                return 'succeeded';
            });
        } catch (\Throwable $e) {
            // Neither exception messages nor payload/credentials enter operational diagnostics.
            log_message('error','Integration delivery {id} failed ({type}).',['id'=>$claim['id'],'type'=>get_class($e)]);
            return $this->transaction(function () use ($companyId,$claim,$blocked) {
                $row=$this->locked('integration_deliveries',$companyId,$claim['id']);
                if (!$this->owns($row,$claim)) return 'stale';
                $status=$blocked ? 'blocked' : ((int)$row['attempts']>=self::MAX_ATTEMPTS ? 'dead' : 'retry');
                $delay=[60,300,900,3600][min(3,max(0,(int)$row['attempts']-1))];
                $error=$blocked ? 'Contrato o consumidor no disponible; requiere revisión.' : 'Falló el consumidor; revisar código y dependencias.';
                $this->update($companyId,$row['id'],['status'=>$status,'lease_token'=>null,'lease_until'=>null,'available_at'=>date('Y-m-d H:i:s',time()+$delay),'last_error'=>$error]);
                $this->finishAttempt($companyId,$claim['attempt_id'],$status,$error);
                return $status;
            });
        }
    }

    private function owns(?array $row,array $claim): bool
    {
        return $row && $row['status']==='processing' && is_string($row['lease_token']) && hash_equals($row['lease_token'],(string)$claim['token']);
    }

    private function update(string $companyId,string $id,array $data): void
    {
        if (!$this->db->table('integration_deliveries')->where('company_id',$companyId)->where('id',$id)->update($data)) throw new RuntimeException('No se pudo actualizar la entrega.');
    }

    private function finishAttempt(string $companyId,string $id,string $result,?string $error=null): void
    {
        if (!$this->db->table('integration_delivery_attempts')->where('company_id',$companyId)->where('id',$id)->where('result','processing')->update(['result'=>$result,'finished_at'=>date('Y-m-d H:i:s'),'error'=>$error]) || $this->db->affectedRows()!==1) throw new RuntimeException('No se pudo finalizar el intento.');
    }

    public function retryFailed(string $companyId,string $deliveryId): void
    {
        $this->transaction(function () use ($companyId,$deliveryId) {
            $row=$this->locked('integration_deliveries',$companyId,$deliveryId);
            if (!$row || !in_array($row['status'],['dead','blocked'],true)) throw new RuntimeException('Entrega no disponible para reintento manual en esta empresa.');
            $now=date('Y-m-d H:i:s');
            if (!$this->db->table('integration_delivery_attempts')->insert(['id'=>app_uuid(),'company_id'=>$companyId,'delivery_id'=>$deliveryId,'attempt'=>(int)$row['attempts'],'started_at'=>$now,'finished_at'=>$now,'result'=>'manual_retry'])) throw new RuntimeException('No se pudo registrar la solicitud.');
            $this->update($companyId,$deliveryId,['status'=>'retry','attempts'=>0,'available_at'=>$now,'last_error'=>null,'lease_token'=>null,'lease_until'=>null]);
        });
    }
}
