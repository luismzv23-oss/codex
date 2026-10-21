<?php
namespace App\Libraries\Core;

use CodeIgniter\HTTP\RequestInterface;

final class CompanyContext
{
    private array $company;
    private string $actorId;

    private function __construct(array $company, string $actorId)
    {
        $this->company = $company;
        $this->actorId = $actorId;
    }

    /** Read company_id consistently across browser forms, JSON and API headers. */
    public static function requested(RequestInterface $request): ?string
    {
        $values = [$request->getGet('company_id'), $request->getPost('company_id'), $request->getHeaderLine('X-Company-Id')];
        if (preg_match('~application/(?:[a-z0-9.+-]+\+)?json~i', $request->getHeaderLine('Content-Type'))) {
            try { $json = $request->getJSON(true); }
            catch (\Throwable $e) { throw new AccessException('El cuerpo JSON no es válido.', 422); }
            if (!is_array($json)) throw new AccessException('El cuerpo JSON debe ser un objeto.', 422);
            $values[] = $json['company_id'] ?? null;
        }
        $ids = [];
        foreach ($values as $value) {
            if ($value === null) continue;
            if (!is_string($value) && !is_int($value)) throw new AccessException('La empresa indicada no es válida.', 422);
            $id = trim((string) $value);
            if ($id !== '') $ids[$id] = true;
        }
        if (count($ids) > 1) throw new AccessException('La solicitud indica empresas diferentes.', 422);
        return $ids ? (string) array_key_first($ids) : null;
    }

    public static function resolve(ActorContext $actor, ?string $requestedCompanyId): self
    {
        $id = trim((string) $requestedCompanyId);
        if (!$actor->isSuperadmin()) {
            if ($id !== '' && $id !== $actor->companyId()) throw new AccessException('No tenés acceso a la empresa indicada.');
            $id = (string) $actor->companyId();
        } elseif ($id === '') {
            // Preserve Inventory's existing alphabetical fallback, selecting an active company.
            $first = db_connect()->table('companies')->where('active', 1)->orderBy('name')->get(1)->getRowArray();
            $id = $first['id'] ?? '';
        }
        if ($id === '') throw new AccessException('Debes seleccionar una empresa.', 422);
        $company = db_connect()->table('companies')->where('id', $id)->get()->getRowArray();
        if (!$company) throw new AccessException('La empresa seleccionada no existe.', 404);
        if ((int) $company['active'] !== 1) throw new AccessException('La empresa seleccionada está inactiva.');
        return new self($company, $actor->id());
    }

    public function id(): string { return $this->company['id']; }
    public function actorId(): string { return $this->actorId; }
    public function toArray(): array { return $this->company; }

    public function assertResource(array $resource): void
    {
        if (($resource['company_id'] ?? null) !== $this->id()) throw new AccessException('Recurso no disponible en esta empresa.', 404);
    }
}
