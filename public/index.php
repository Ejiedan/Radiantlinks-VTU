<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Router;
use App\Core\Security;
use App\Services\SettingsService;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/app/bootstrap.php';

$app = App::get();
try {
    $timeout = (int) (new SettingsService($app->db()))->get('session_timeout_minutes', 30);
} catch (\Throwable) {
    $timeout = 30;
}
Security::enforceSessionTimeout($timeout);
$router = new Router($app);

require BASE_PATH . '/routes/web.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$base = app_base_url();
if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
    $path = substr($path, strlen($base)) ?: '/';
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
