<?php

namespace App\Libraries\Core;

/** Presentation only. Controllers remain responsible for authorization. */
final class ModuleNavigation
{
    private ModuleRegistry $registry;

    public function __construct(?ModuleRegistry $registry = null)
    {
        $this->registry = $registry ?? new ModuleRegistry();
    }

    public function entry(array $system, ?string $companyId = null): string
    {
        $m = $this->registry->get((string)($system['slug'] ?? ''));
        // The catalog remains authoritative, including systems without a manifest.
        return $this->url((string)($system['entry_url'] ?? ''), !empty($m['company_scoped']) ? $companyId : null);
    }

    /** Mirrors existing switcher visibility, always scoped to the user's company. */
    public function accessible(array $user, ?string $companyId): array
    {
        if (empty($user['id'])) return [];
        $super = ($user['role_slug'] ?? '') === 'superadmin';
        $companyId = $super ? $companyId : ($user['company_id'] ?? null);
        $q = db_connect()->table('systems')->select('systems.*')->where('systems.active',1);
        if (!$super || $companyId) {
            if (!$companyId) return [];
            $q->join('company_systems','company_systems.system_id = systems.id')
                ->where('company_systems.company_id',$companyId)->where('company_systems.active',1);
        }
        if (!$super) {
            $q->join('user_systems','user_systems.system_id = systems.id AND user_systems.company_id = company_systems.company_id')
                ->where('user_systems.company_id',$companyId)->where('user_systems.user_id',$user['id'])->where('user_systems.active',1);
        }
        $rows = $q->orderBy('systems.name')->get()->getResultArray();
        foreach ($rows as &$row) $row['entry_url'] = $this->entry($row,$companyId);
        unset($row);
        return array_values(array_filter($rows,static fn(array $row): bool => $row['entry_url'] !== '#'));
    }

    public function url(string $path, ?string $companyId = null): string
    {
        $path = trim($path);
        if ($path === '' || $path === '#' || preg_match('/[\x00-\x20\\\\]/', $path) || str_starts_with($path,'//')) return '#';
        $absolute = preg_match('~^https?://~i', $path) === 1;
        if (!$absolute && preg_match('~^[a-z][a-z0-9+.-]*:~i', $path)) return '#';
        $tailOffset = strcspn($path,'?#');
        $url = $absolute ? $path : site_url(ltrim(substr($path,0,$tailOffset),'/')).substr($path,$tailOffset);
        // Never forward the active company to an external service.
        $base = parse_url(site_url());
        $parts = parse_url($url);
        if ($parts === false) return '#';
        $local = strtolower($parts['host'] ?? '') === strtolower($base['host'] ?? '')
            && ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) === ($base['port'] ?? (($base['scheme'] ?? '') === 'https' ? 443 : 80))
            && ($parts['scheme'] ?? '') === ($base['scheme'] ?? '');
        if ($companyId === null || $companyId === '' || !$local) return $url;
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $url = explode('#',$url,2)[0];
        $query = array_values(array_filter(explode('&',$parts['query'] ?? ''),static function (string $pair): bool {
            $key = urldecode(explode('=',$pair,2)[0]);
            return $pair !== '' && $key !== 'company_id' && !str_starts_with($key,'company_id[');
        }));
        $query[] = 'company_id='.rawurlencode($companyId);
        return explode('?',$url,2)[0].'?'.implode('&',$query).$fragment;
    }

    public function settings(?string $companyId, ?array $user): array
    {
        $entries = [];
        foreach ($this->registry->all() as $slug => $m) {
            if ($m['settings'] === []) continue;
            // Preserve the three existing settings entries during gradual adoption.
            $legacy = in_array($slug,['inventario','ventas'],true);
            if (!$legacy && !$this->canConfigure($slug,$companyId,$user)) continue;
            foreach ($m['settings'] as $setting) {
                $setting['url'] = $this->url($setting['path'],$companyId);
                $entries[] = $setting;
            }
        }
        return $entries;
    }

    private function canConfigure(string $slug, ?string $companyId, ?array $user): bool
    {
        if (!$companyId || !$user || !\App\Libraries\AuthService::allows($user,'settings.manage')) return false;
        $db = db_connect();
        $q = $db->table('systems')->join('company_systems','company_systems.system_id = systems.id')
            ->where('systems.slug',$slug)->where('systems.active',1)
            ->where('company_systems.company_id',$companyId)->where('company_systems.active',1);
        if (($user['role_slug'] ?? '') !== 'superadmin') {
            if (($user['company_id'] ?? '') !== $companyId || ($user['role_slug'] ?? '') !== 'admin') return false;
            $q->join('user_systems','user_systems.system_id = systems.id AND user_systems.company_id = company_systems.company_id')
                ->where('user_systems.user_id',$user['id'])->where('user_systems.active',1)->where('user_systems.access_level','manage');
        }
        return $q->countAllResults() > 0;
    }
}
