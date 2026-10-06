<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Mailer;
use App\Core\Security;
use App\Services\AuthService;
use App\Services\LoginThrottleService;
use App\Services\NotificationService;
use App\Services\SecurityPolicyService;
use App\Services\SettingsService;
use InvalidArgumentException;
use PDOException;

final class AuthController
{
    public function __construct(private App $app)
    {
    }

    public function showLogin(): void
    {
        $this->app->view('auth/login', ['title' => 'Login', 'notice' => $_SESSION['notice'] ?? null]);
        unset($_SESSION['notice']);
    }

    public function login(): void
    {
        Security::verifyCsrf();
        $login = (string) ($_POST['email'] ?? '');
        $bucket = $this->loginBucket($login);
        $throttle = new LoginThrottleService($this->app->db());
        if ($throttle->tooMany($bucket)) {
            $this->app->view('auth/login', ['title' => 'Login', 'error' => 'Too many login attempts. Please try again in 15 minutes.']);
            return;
        }

        $user = (new AuthService($this->app->db()))->attempt($login, $_POST['password'] ?? '');
        if (!$user) {
            $throttle->hit($bucket);
            $this->app->view('auth/login', ['title' => 'Login', 'error' => 'Invalid login details.']);
            return;
        }
        $throttle->clear($bucket);

        if ($this->twoFactorEnabledFor($user)) {
            if (!$this->beginTwoFactor($user)) {
                unset($_SESSION['pending_2fa']);
                $this->app->view('auth/login', [
                    'title' => 'Login',
                    'error' => 'Verification code could not be sent. Please configure SMTP/PHP mail or turn off 2FA temporarily.',
                ]);
                return;
            }
            redirect_to('/verify-2fa');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['_last_activity'] = time();
        redirect_to('/dashboard');
    }

    public function showTwoFactor(): void
    {
        if (empty($_SESSION['pending_2fa'])) {
            redirect_to('/login');
            return;
        }
        $this->app->view('auth/two_factor', [
            'title' => 'Verify login',
            'notice' => $_SESSION['notice'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
        unset($_SESSION['notice'], $_SESSION['error']);
    }

    public function verifyTwoFactor(): void
    {
        Security::verifyCsrf();
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || (int) ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa']);
            $_SESSION['error'] = 'Verification code expired. Please login again.';
            redirect_to('/login');
            return;
        }

        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!password_verify($code, (string) $pending['code_hash'])) {
            $_SESSION['pending_2fa']['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
            if ($_SESSION['pending_2fa']['attempts'] >= 5) {
                unset($_SESSION['pending_2fa']);
                $_SESSION['error'] = 'Too many wrong verification attempts. Please login again.';
                redirect_to('/login');
                return;
            }
            $_SESSION['error'] = 'Invalid verification code.';
            redirect_to('/verify-2fa');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $pending['user'];
        $_SESSION['_last_activity'] = time();
        unset($_SESSION['pending_2fa']);
        redirect_to('/dashboard');
    }

    public function resendTwoFactor(): void
    {
        Security::verifyCsrf();
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending) {
            redirect_to('/login');
            return;
        }
        if ($this->beginTwoFactor($pending['user'])) {
            $_SESSION['notice'] = 'A new verification code has been sent.';
        } else {
            $_SESSION['error'] = 'Verification code could not be sent. Please contact support.';
        }
        redirect_to('/verify-2fa');
    }

    public function showRegister(): void
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $_SESSION['register_bot_answer'] = $a + $b;
        $this->app->view('auth/register', ['title' => 'Create account']);
    }

    public function register(): void
    {
        Security::verifyCsrf();
        if (!$this->passesBotCheck()) {
            $this->app->view('auth/register', [
                'title' => 'Create account',
                'error' => 'Registration security check failed. Please try again.',
                'old' => $_POST,
            ]);
            return;
        }
        try {
            (new AuthService($this->app->db()))->register($_POST);
            redirect_to('/dashboard');
        } catch (InvalidArgumentException $exception) {
            $this->app->view('auth/register', [
                'title' => 'Create account',
                'error' => $exception->getMessage(),
                'old' => $_POST,
            ]);
        } catch (PDOException $exception) {
            $message = 'Registration could not be completed. Please check your details and try again.';
            if (str_contains($exception->getMessage(), 'users_email_unique')) {
                $message = 'An account already exists with this email address.';
            }
            if (str_contains($exception->getMessage(), 'users_username_unique')) {
                $message = 'This username is already taken.';
            }
            if (str_contains($exception->getMessage(), 'users_phone_unique')) {
                $message = 'An account already exists with this phone number.';
            }
            $this->app->view('auth/register', [
                'title' => 'Create account',
                'error' => $message,
                'old' => $_POST,
            ]);
        }
    }

    public function showForgotPassword(): void
    {
        $this->app->view('auth/forgot_password', ['title' => 'Forgot password', 'notice' => $_SESSION['notice'] ?? null, 'error' => $_SESSION['error'] ?? null]);
        unset($_SESSION['notice'], $_SESSION['error']);
    }

    public function sendPasswordReset(): void
    {
        Security::verifyCsrf();
        $auth = new AuthService($this->app->db());
        $user = $auth->findForReset((string) ($_POST['login'] ?? ''));
        $_SESSION['notice'] = 'If the account exists, a verification code has been sent.';

        if ($user) {
            try {
                (new SecurityPolicyService($this->app->db()))->assertNotBlocked($user);
                $code = (string) random_int(100000, 999999);
                $_SESSION['password_reset'] = [
                    'user_id' => (int) $user['id'],
                    'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                    'expires_at' => time() + 600,
                    'attempts' => 0,
                ];
                $settings = (new SettingsService($this->app->db()))->many();
                $site = (string) ($settings['site_title'] ?? 'Radiantlinks VTU');
                $html = '<p>Your password reset verification code is:</p><h2>' . $code . '</h2><p>This code expires in 10 minutes. You will also need your transaction PIN.</p>';
                (new Mailer())->send((string) $user['email'], $site . ' password reset code', $html, $settings);
            } catch (\Throwable) {
                // Keep response generic so account existence and block state are not exposed.
            }
        }

        redirect_to('/reset-password');
    }

    public function showResetPassword(): void
    {
        if (empty($_SESSION['password_reset'])) {
            redirect_to('/forgot-password');
            return;
        }
        $this->app->view('auth/reset_password', ['title' => 'Reset password', 'error' => $_SESSION['error'] ?? null]);
        unset($_SESSION['error']);
    }

    public function resetPassword(): void
    {
        Security::verifyCsrf();
        $pending = $_SESSION['password_reset'] ?? null;
        if (!$pending || (int) ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset']);
            $_SESSION['error'] = 'Password reset code expired. Please request a new one.';
            redirect_to('/forgot-password');
            return;
        }

        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!password_verify($code, (string) $pending['code_hash'])) {
            $_SESSION['password_reset']['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
            if ($_SESSION['password_reset']['attempts'] >= 5) {
                unset($_SESSION['password_reset']);
                $_SESSION['error'] = 'Too many wrong reset code attempts. Please request a new code.';
                redirect_to('/forgot-password');
                return;
            }
            $_SESSION['error'] = 'Invalid verification code.';
            redirect_to('/reset-password');
            return;
        }

        try {
            (new AuthService($this->app->db()))->resetPassword(
                (int) $pending['user_id'],
                (string) ($_POST['pin'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirmation'] ?? '')
            );
            unset($_SESSION['password_reset']);
            $_SESSION['notice'] = 'Password reset successful. Please login.';
            redirect_to('/login');
        } catch (\Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
            redirect_to('/reset-password');
        }
    }

    public function logout(): void
    {
        Security::verifyCsrf();
        $_SESSION = [];
        session_destroy();
        redirect_to('/login');
    }

    private function twoFactorEnabledFor(array $user): bool
    {
        $settings = (new SettingsService($this->app->db()))->many();
        $role = (string) ($user['role'] ?? 'customer');
        if (in_array($role, ['owner', 'admin'], true)) {
            return ($settings['two_factor_admin_enabled'] ?? 'off') === 'on';
        }
        return ($settings['two_factor_customer_enabled'] ?? 'off') === 'on';
    }

    private function beginTwoFactor(array $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $_SESSION['pending_2fa'] = [
            'user' => $user,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => time() + 600,
            'attempts' => 0,
        ];

        $settings = (new SettingsService($this->app->db()))->many();
        $site = (string) ($settings['site_title'] ?? 'Radiantlinks VTU');
        $html = '<p>Your ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' verification code is:</p><h2>' . $code . '</h2><p>This code expires in 10 minutes.</p>';
        return (new Mailer())->send((string) $user['email'], $site . ' login verification code', $html, $settings);
    }

    private function loginBucket(string $login): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
        return 'login:' . hash('sha256', strtolower(trim($login)) . '|' . $ip);
    }

    private function passesBotCheck(): bool
    {
        if (!empty($_POST['website'])) {
            return false;
        }
        $started = (int) ($_POST['form_started_at'] ?? 0);
        if ($started <= 0 || time() - $started < 3) {
            return false;
        }
        $answer = (int) ($_POST['bot_answer'] ?? -1);
        return $answer === (int) ($_SESSION['register_bot_answer'] ?? -2);
    }
}
