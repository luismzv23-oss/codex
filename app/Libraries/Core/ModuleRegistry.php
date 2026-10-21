<?php

namespace App\Libraries\Core;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Trusted deployment metadata only. A catalog row never executes or installs PHP. */
final class ModuleRegistry
{
    public const CORE_VERSION = '1.0.0';
    private array $modules = [];
    private array $errors = [];
    private array $routeFiles = [];
    private ?array $resolvedRoutes = null;

    public function __construct(?string $root = null)
    {
        foreach (glob(($root ?? APPPATH . 'Modules') . '/*/module.php') ?: [] as $file) {
            $key = basename(dirname($file));
            try {
                $manifest = (static fn(string $path) => require $path)($file);
                $this->validate($manifest);
                $slug = $manifest['slug'];
                $key = $slug;
                if (isset($this->errors[$slug]) || isset($this->modules[$slug])) {
                    unset($this->modules[$slug]);
                    unset($this->routeFiles[$slug]);
                    throw new RuntimeException('Slug duplicado: ' . $slug);
                }
                $routeFile = dirname($file).'/Config/Routes.php';
                $manifest['hash'] = hash('sha256',file_get_contents($file).(is_file($routeFile) ? file_get_contents($routeFile) : ''));
                $this->modules[$slug] = $manifest;
                if (is_file($routeFile)) $this->routeFiles[$slug] = $routeFile;
            } catch (Throwable $e) {
                $this->errors[$key] = 'Manifiesto inválido: ' . $key;
                // Do not expose paths, PHP errors or arbitrary manifest contents to the UI.
            }
        }
        ksort($this->modules);
    }

    public function all(): array { return $this->modules; }
    public function errors(): array { return $this->errors; }
    public function get(string $slug): ?array { return $this->modules[$slug] ?? null; }

    public function registerRoutes(\CodeIgniter\Router\RouteCollection $routes): void
    {
        foreach ($this->routeFiles as $file) {
            (static function ($routes, string $file): void { require $file; })($routes,$file);
        }
    }

    private function validate($m): void
    {
        if (!is_array($m)) throw new RuntimeException('Expected array');
        foreach (['slug','name','version','owner','entry','icon','core_min','core_max'] as $key) {
            if (!isset($m[$key]) || !is_string($m[$key]) || trim($m[$key]) === '') throw new RuntimeException($key);
        }
        if (!preg_match('/^[a-z][a-z0-9-]*$/D', $m['slug'])) throw new RuntimeException('slug');
        foreach (['version','core_min','core_max'] as $key) {
            if (!preg_match('/^\d+\.\d+\.\d+$/D', $m[$key])) throw new RuntimeException($key);
        }
        if (version_compare($m['core_min'], $m['core_max'], '>')) throw new RuntimeException('Core range');
        if (!isset($m['company_scoped']) || !is_bool($m['company_scoped'])) throw new RuntimeException('company_scoped');
        $this->validatePath($m['entry']);
        foreach (['dependencies','schema','settings'] as $key) {
            if (!isset($m[$key]) || !is_array($m[$key])) throw new RuntimeException($key);
        }
        foreach ($m['dependencies'] as $slug => $version) {
            if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]*$/D', $slug) || !is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/D', $version)) throw new RuntimeException('dependency');
        }
        foreach ($m['schema'] as $table => $columns) {
            if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]*$/D', $table) || !is_array($columns)) throw new RuntimeException('schema');
            foreach ($columns as $column) {
                if (!is_string($column) || !preg_match('/^[a-z][a-z0-9_]*$/D', $column)) throw new RuntimeException('column');
            }
        }
        foreach ($m['settings'] as $setting) {
            foreach (['name','path','icon','description'] as $key) {
                if (!isset($setting[$key]) || !is_string($setting[$key])) throw new RuntimeException('setting');
            }
            $this->validatePath($setting['path']);
        }
    }

    private function validatePath(string $path): void
    {
        if (!preg_match('~^[a-z0-9][a-z0-9/_-]*$~D', $path) || str_contains($path, '..')) throw new RuntimeException('Local route required');
    }

    /** Checks the declared integration contract, not production or third-party readiness. */
    public function check(string $slug, ?BaseConnection $db = null, ?array $routes = null, array $stack = []): array
    {
        $m = $this->get($slug);
        $issues = [];
        if (!$m) return ['slug'=>$slug,'ok'=>false,'issues'=>['Sin manifiesto válido desplegado.'],'version'=>null,'hash'=>null];
        if (in_array($slug, $stack, true)) return ['slug'=>$slug,'ok'=>false,'issues'=>['Dependencia circular.'],'version'=>$m['version'],'hash'=>$m['hash']];
        $stack[] = $slug;
        $db ??= db_connect();
        if ($routes === null) {
            $routes = $this->resolvedRoutes ??= (static function (): array {
                $routes = \Config\Services::routes(false);
                require APPPATH . 'Config/Routes.php';
                return $routes->getRoutes('get');
            })();
        }
        if (version_compare(self::CORE_VERSION,$m['core_min'],'<') || version_compare(self::CORE_VERSION,$m['core_max'],'>')) $issues[]='Versión de Core incompatible.';
        foreach ($m['dependencies'] as $dependency => $minimum) {
            $other = $this->get($dependency);
            if (!$other || version_compare($other['version'],$minimum,'<')) {
                $issues[]='Dependencia ausente o incompatible: '.$dependency;
            } elseif (!$this->check($dependency,$db,$routes,$stack)['ok']) {
                $issues[]='Dependencia no disponible: '.$dependency;
            }
        }
        $paths = array_merge([$m['entry']], array_column($m['settings'],'path'));
        foreach (array_unique($paths) as $path) {
            $handler = $routes[$path] ?? null;
            if (!is_string($handler) || !str_contains($handler,'::')) { $issues[]='Ruta GET ausente: '.$path; continue; }
            [$class,$method] = explode('::',$handler,2);
            $method = explode('/',$method)[0];
            if (!class_exists($class) || !method_exists($class,$method) || !(new \ReflectionMethod($class,$method))->isPublic()) $issues[]='Controlador no disponible: '.$path;
        }
        foreach ($m['schema'] as $table => $columns) {
            if (!$db->tableExists($table)) { $issues[]='Tabla ausente: '.$table; continue; }
            foreach (array_diff($columns,$db->getFieldNames($table)) as $column) $issues[]='Columna ausente: '.$table.'.'.$column;
        }
        return ['slug'=>$slug,'ok'=>$issues===[],'issues'=>$issues,'version'=>$m['version'],'hash'=>$m['hash']];
    }
}
