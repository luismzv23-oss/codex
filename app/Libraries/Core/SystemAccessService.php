<?php
namespace App\Libraries\Core;

/** Shared access boundary. Other modules adopt it explicitly; no global permission rewrite. */
final class SystemAccessService
{
    public function authorize(ActorContext $actor, CompanyContext $company, string $systemSlug, string $action): void
    {
        $this->context($actor, $company, $systemSlug, $action);
    }

    public function context(ActorContext $actor, CompanyContext $company, string $systemSlug, string $action): array
    {
        if (!in_array($action, ['view', 'manage', 'configure'], true)) throw new AccessException('Acción no reconocida.', 422);
        if ($company->actorId() !== $actor->id()) throw new AccessException('Contexto de empresa no autorizado.');
        if (!$actor->isSuperadmin() && $actor->companyId() !== $company->id()) throw new AccessException('Contexto de empresa no autorizado.');
        if (!$actor->hasPermission('systems.view')) throw new AccessException('No tenés permiso para acceder a Sistemas.');
        $db = db_connect();
        // Recheck company and assignments at the operation boundary; identity is request scoped.
        if (!$db->table('companies')->where(['id'=>$company->id(), 'active'=>1])->countAllResults()) throw new AccessException('La empresa seleccionada está inactiva.');
        $system = $db->table('systems')->where('slug', $systemSlug)->where('active', 1)->get()->getRowArray();
        if (!$system) throw new AccessException('El sistema solicitado no está disponible.', 404);
        $level = 'view';
        if (!$actor->isSuperadmin()) {
            if (!$db->table('company_systems')->where(['company_id'=>$company->id(), 'system_id'=>$system['id'], 'active'=>1])->countAllResults()) throw new AccessException('El sistema no está habilitado para la empresa.');
            $assignment = $db->table('user_systems')->where(['company_id'=>$company->id(), 'user_id'=>$actor->id(), 'system_id'=>$system['id'], 'active'=>1])->get()->getRowArray();
            if (!$assignment) throw new AccessException('Tu usuario no tiene acceso activo al sistema.');
            $level = $assignment['access_level'];
            if (!in_array($level, ['view', 'manage', 'vendedor'], true)) throw new AccessException('Nivel de acceso no reconocido.');
        }
        // Explicit pilot policy: preserve the web restriction for vendedores in both transports.
        if ($systemSlug === 'inventario' && $actor->role() === 'vendedor') throw new AccessException('El rol vendedor no tiene acceso a Inventario.');
        $canManage = $actor->isSuperadmin() || $level === 'manage';
        $canConfigure = $actor->isSuperadmin() || ($actor->role() === 'admin' && $level === 'manage');
        if ($action === 'manage' && !$canManage) throw new AccessException('Tu usuario solo tiene acceso de consulta al sistema.');
        if ($action === 'configure' && !$canConfigure) throw new AccessException('Solo superadmin o admin con gestión pueden configurar el sistema.');
        return ['company'=>$company->toArray(), 'system'=>$system, 'access_level'=>$level, 'canManage'=>$canManage, 'canConfigure'=>$canConfigure];
    }
}
