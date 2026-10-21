<?php

namespace App\Libraries\Core;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ModuleInstallationService
{
    private ModuleRegistry $registry;
    private BaseConnection $db;

    public function __construct(?ModuleRegistry $registry = null, ?BaseConnection $db = null)
    {
        $this->registry = $registry ?? new ModuleRegistry();
        $this->db = $db ?? db_connect();
    }

    public function report(): array
    {
        $rows = $this->db->table('systems')->orderBy('name')->get()->getResultArray();
        $known = array_column($rows,'slug');
        foreach ($this->registry->all() as $slug => $m) {
            if (!in_array($slug,$known,true)) $rows[]=['id'=>null,'slug'=>$slug,'name'=>$m['name'],'active'=>0];
        }
        foreach ($rows as &$row) {
            $row['technical'] = $this->registry->check($row['slug'],$this->db);
            $row['installation'] = $this->db->tableExists('system_installations') && !empty($row['id'])
                ? $this->db->table('system_installations')->where('system_id',$row['id'])->get()->getRowArray() : null;
            $row['current'] = $row['technical']['ok'] && $row['installation'] && $row['installation']['status'] === 'verified' && $row['installation']['manifest_hash'] === $row['technical']['hash'] && $row['installation']['installed_version'] === $row['technical']['version'];
        }
        unset($row);
        return ['systems'=>$rows,'errors'=>$this->registry->errors(),'tracking_available'=>$this->db->tableExists('system_installations')];
    }

    /** Explicit deployment operation. Never changes existing catalog metadata or assignments. */
    public function register(string $slug, bool $apply = false): array
    {
        $check = $this->registry->check($slug,$this->db);
        if (!$check['ok']) throw new RuntimeException(implode(' ', $check['issues']));
        $m = $this->registry->get($slug);
        $existing = $this->db->table('systems')->where('slug',$slug)->get()->getRowArray();
        $plan = ['slug'=>$slug,'operation'=>$existing ? 'record_verification' : 'create_inactive_catalog_entry','applied'=>false,'check'=>$check];
        if (!$apply) return $plan;
        if (!$this->db->tableExists('system_installations')) throw new RuntimeException('Falta aplicar la migración aislada CodexCore.');
        $this->db->transBegin();
        try {
            helper('app');
            $id = $existing['id'] ?? app_uuid();
            $now = date('Y-m-d H:i:s');
            if (!$existing && !$this->db->table('systems')->insert([
                'id'=>$id,'slug'=>$slug,'name'=>$m['name'],'description'=>$m['description'] ?? '',
                'entry_url'=>$m['entry'],'icon'=>$m['icon'],'active'=>0,'created_at'=>$now,'updated_at'=>$now,
            ])) throw new RuntimeException('No se pudo registrar el sistema.');
            $data = ['installed_version'=>$m['version'],'manifest_hash'=>$m['hash'],'status'=>'verified','checked_at'=>$now];
            $table = $this->db->table('system_installations');
            $success = $table->where('system_id',$id)->countAllResults() > 0
                ? $table->where('system_id',$id)->update($data)
                : $table->insert(['system_id'=>$id]+$data);
            if (!$success || !$this->db->transStatus()) throw new RuntimeException('No se pudo guardar la verificación.');
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        $plan['applied'] = true;
        return $plan;
    }
}
