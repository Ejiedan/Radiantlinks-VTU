<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

App\Core\Env::load(BASE_PATH . '/.env');
$debug = filter_var(App\Core\Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOL);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
$logDirectory = BASE_PATH . '/storage/logs';
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}
ini_set('error_log', $logDirectory . '/app.log');
set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $message = $debug
        ? htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : 'An unexpected error occurred. Please try again or contact support.';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Server error</title></head><body><main style="max-width:560px;margin:10vh auto;padding:24px;font-family:Arial,sans-serif"><h1>Something went wrong</h1><p>' . $message . '</p></main></body></html>';
});
App\Core\Security::startSession();
App\Core\Security::securityHeaders();

function app_base_url(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('/public', '', str_replace('\\', '/', dirname($scriptName))), '/');

    return ($dir === '' || $dir === '.') ? '' : $dir;
}

function url(string $path = '/'): string
{
    $base = app_base_url();
    $path = '/' . ltrim($path, '/');

    return $base . ($path === '/' ? '/' : $path);
}

function stored_media_url(string $path): string
{
    if (preg_match('#^(?:https?:)?//#i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    // App::view applies the current subdirectory prefix to root-relative HTML URLs.
    return '/' . ltrim($path, '/');
}

function absolute_url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        $scheme = $forwardedProto !== ''
            ? $forwardedProto
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . url($path);
    }

    $base = rtrim((string) App\Core\Env::get('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect_to(string $path): void
{
    if (preg_match('#^https?://#i', $path) === 1) {
        header('Location: ' . $path);
        return;
    }

    header('Location: ' . url($path));
}
