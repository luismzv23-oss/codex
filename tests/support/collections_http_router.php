<?php
declare(strict_types=1);

// Invoked exclusively by the disposable MySQL check, never by the public entry point.
$database = getenv('COLLECTIONS_QA_DATABASE') ?: '';
if (PHP_SAPI !== 'cli-server' || !preg_match('/\Acodex_collections_check_[a-f0-9]{10}\z/', $database)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
define('FCPATH', $root . '/public/');
chdir(FCPATH);
require $root . '/app/Config/Paths.php';
$paths = new Config\Paths();
$paths->writableDirectory = $root . '/tmp/' . $database;
require rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
require_once SYSTEMPATH . 'Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();
define('ENVIRONMENT', 'development');
// Override configuration before initializing any services/connections.
$config = config('Database');
$config->default['database'] = $database;
$config->default['DSN'] = '';
$config->default['DBPrefix'] = '';
$config->default['strictOn'] = true;
$config->defaultGroup = 'default';
config('App')->baseURL = 'http://127.0.0.1:' . $_SERVER['SERVER_PORT'] . '/';
config('Session')->savePath = $paths->writableDirectory . '/session';
config('Session')->driver = CodeIgniter\Session\Handlers\FileHandler::class;
$_ENV['jwt.secret'] = $_SERVER['jwt.secret'] = getenv('COLLECTIONS_QA_JWT');
$app = Config\Services::codeigniter();
$app->initialize();
if (db_connect()->query('SELECT DATABASE() AS name')->getRowArray()['name'] !== $database) throw new RuntimeException('Isolation guard failed');
$app->setContext('web');
$app->run();
