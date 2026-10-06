<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionPath = BASE_PATH . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0775, true);
        }
        session_save_path($sessionPath);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'secure' => self::isHttps(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function securityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid session token.');
        }
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function requireLogin(): array
    {
        if (empty($_SESSION['user'])) {
            \redirect_to('/login');
            exit;
        }
        return $_SESSION['user'];
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (($user['role'] ?? '') !== 'owner' && ($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            App::get()->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        return $user;
    }

    public static function enforceSessionTimeout(int $minutes): void
    {
        if (empty($_SESSION['user'])) {
            return;
        }

        $minutes = max(1, $minutes);
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? time());
        if ((time() - $lastActivity) > ($minutes * 60)) {
            $_SESSION = [];
            session_destroy();
            self::startSession();
            $_SESSION['notice'] = 'Your session expired. Please login again.';
            \redirect_to('/login');
            exit;
        }

        $_SESSION['_last_activity'] = time();
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
