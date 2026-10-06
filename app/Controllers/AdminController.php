<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\CommerceService;
use App\Services\SettingsService;
use App\Services\PhoneNumberService;

final class AdminController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $stats = [
            'users' => (int) $this->app->db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'active_users' => (int) $this->app->db()->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
            'blocked_users' => (int) $this->app->db()->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn(),
            'online_users' => (int) $this->app->db()->query('SELECT COUNT(*) FROM users WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)')->fetchColumn(),
            'balance' => (float) $this->app->db()->query('SELECT COALESCE(SUM(balance),0) FROM users')->fetchColumn(),
            'transactions' => (int) $this->app->db()->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
            'weekly_transactions' => (int) $this->app->db()->query('SELECT COUNT(*) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
            'success_volume' => (float) $this->app->db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'success'")->fetchColumn(),
            'weekly_success_volume' => (float) $this->app->db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'failed_transactions' => (int) $this->app->db()->query("SELECT COUNT(*) FROM transactions WHERE status = 'failed'")->fetchColumn(),
        ];
        $weeklyChart = [];
        for ($day = 6; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime('-' . $day . ' days'));
            $weeklyChart[$date] = ['label' => date('D', strtotime($date)), 'success' => 0.0, 'failed' => 0.0, 'pending' => 0.0];
        }
        $chartRows = $this->app->db()->query("SELECT DATE(created_at) AS day, status, COALESCE(SUM(amount), 0) AS total FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at), status")->fetchAll();
        foreach ($chartRows as $chartRow) {
            $day = (string) $chartRow['day'];
            $status = strtolower((string) $chartRow['status']);
            if (isset($weeklyChart[$day], $weeklyChart[$day][$status])) {
                $weeklyChart[$day][$status] = (float) $chartRow['total'];
            }
        }
        $serviceMix = ['airtime' => 0, 'data' => 0];
        $mixRows = $this->app->db()->query("SELECT service, COUNT(*) AS total FROM transactions WHERE service IN ('airtime', 'data') GROUP BY service")->fetchAll();
        foreach ($mixRows as $mixRow) {
            $serviceMix[(string) $mixRow['service']] = (int) $mixRow['total'];
        }
        $recent = $this->app->db()->query('SELECT t.*, u.email FROM transactions t INNER JOIN users u ON u.id = t.user_id ORDER BY t.id DESC LIMIT 10')->fetchAll();
        $this->app->view('admin/index', ['title' => 'Dashboard', 'stats' => $stats, 'recent' => $recent, 'weeklyChart' => $weeklyChart, 'serviceMix' => $serviceMix]);
    }

    public function users(): void
    {
        Security::requireAdmin();
        $packages = (new CommerceService($this->app->db()))->packages();
        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $statement = $this->app->db()->prepare('SELECT id, first_name, last_name, email, phone, role, package_id, status, blocked_until, blocked_reason, balance, created_at FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT 200');
            $statement->execute(array_fill(0, 5, '%' . $search . '%'));
            $users = $statement->fetchAll();
        } else {
            $users = $this->app->db()->query('SELECT id, first_name, last_name, email, phone, role, package_id, status, blocked_until, blocked_reason, balance, created_at FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
        }
        $this->app->view('admin/users', [
            'title' => 'Users',
            'users' => $users,
            'packages' => $packages,
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function createUser(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $_SESSION['notice'] = 'Password must be at least 8 characters.';
            redirect_to('/admin/users');
            return;
        }
        try {
            $phones = new PhoneNumberService($this->app->db());
            $phone = $phones->normalize((string) ($_POST['phone'] ?? ''));
            $phones->assertAvailable($phone);
            $stmt = $this->app->db()->prepare('INSERT INTO users (first_name, last_name, username, email, phone, password_hash, transaction_pin_hash, role, package_id, status, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
            trim((string) ($_POST['first_name'] ?? '')),
            trim((string) ($_POST['last_name'] ?? '')),
            strtolower(trim((string) ($_POST['username'] ?? ''))),
            strtolower(trim((string) ($_POST['email'] ?? ''))),
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            password_hash((string) ($_POST['pin'] ?? '1234'), PASSWORD_DEFAULT),
            $this->safeRole((string) ($_POST['role'] ?? 'customer')),
            !empty($_POST['package_id']) ? (int) $_POST['package_id'] : null,
            (string) ($_POST['status'] ?? 'active'),
            strtoupper(bin2hex(random_bytes(4))),
            ]);
            $_SESSION['notice'] = 'User created.';
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['notice'] = $exception->getMessage();
        } catch (\PDOException $exception) {
            $_SESSION['notice'] = str_contains($exception->getMessage(), 'users_phone_unique')
                ? 'An account already exists with this phone number.'
                : 'User could not be created. Check the email, username, and phone number.';
        }
        redirect_to('/admin/users');
    }

    public function updateUser(string $id): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();
        $targetId = (int) $id;
        $role = $this->safeRole((string) ($_POST['role'] ?? 'customer'));
        if ($targetId === (int) $admin['id'] && $role !== 'owner' && ($admin['role'] ?? '') === 'owner') {
            $_SESSION['notice'] = 'Owner cannot remove their own owner role.';
            redirect_to('/admin/users');
            return;
        }
        $blockedUntil = trim((string) ($_POST['blocked_until'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        try {
            $phones = new PhoneNumberService($this->app->db());
            $phone = $phones->normalize((string) ($_POST['phone'] ?? ''));
            $phones->assertAvailable($phone, $targetId);
            $stmt = $this->app->db()->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ?, role = ?, package_id = ?, status = ?, blocked_until = ?, blocked_reason = ? WHERE id = ?');
            $stmt->execute([
            trim((string) ($_POST['first_name'] ?? '')),
            trim((string) ($_POST['last_name'] ?? '')),
            $phone,
            $role,
            !empty($_POST['package_id']) ? (int) $_POST['package_id'] : null,
            $status,
            $status === 'blocked' && $blockedUntil !== '' ? str_replace('T', ' ', $blockedUntil) : null,
            trim((string) ($_POST['blocked_reason'] ?? '')),
            $targetId,
            ]);
            $_SESSION['notice'] = 'User updated.';
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['notice'] = $exception->getMessage();
        } catch (\PDOException $exception) {
            $_SESSION['notice'] = str_contains($exception->getMessage(), 'users_phone_unique')
                ? 'An account already exists with this phone number.'
                : 'User could not be updated.';
        }
        redirect_to('/admin/users');
    }

    public function deleteUser(string $id): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();
        $targetId = (int) $id;
        if ($targetId === (int) $admin['id']) {
            $_SESSION['notice'] = 'You cannot delete your own account.';
            redirect_to('/admin/users');
            return;
        }
        $stmt = $this->app->db()->prepare('DELETE FROM users WHERE id = ?');
        try {
            $stmt->execute([$targetId]);
            $_SESSION['notice'] = 'User deleted.';
        } catch (\Throwable) {
            $_SESSION['notice'] = 'User has records and cannot be deleted. Block or suspend them instead.';
        }
        redirect_to('/admin/users');
    }

    public function unblockUser(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $stmt = $this->app->db()->prepare("UPDATE users SET status = 'active', blocked_until = NULL, blocked_reason = NULL WHERE id = ?");
        $stmt->execute([(int) $id]);
        $_SESSION['notice'] = 'User unblocked.';
        redirect_to('/admin/users');
    }

    public function transactions(): void
    {
        Security::requireAdmin();
        $sql = 'SELECT t.*, u.email, u.first_name, u.last_name FROM transactions t INNER JOIN users u ON u.id = t.user_id ORDER BY t.id DESC LIMIT 500';
        $transactions = $this->app->db()->query($sql)->fetchAll();
        $this->app->view('admin/transactions', ['title' => 'Admin Transactions', 'transactions' => $transactions]);
    }

    public function exportTransactions(): void
    {
        Security::requireAdmin();
        $rows = $this->app->db()->query('SELECT t.created_at, u.email, t.reference, t.service, t.recipient, t.amount, t.status, t.response_message, t.details FROM transactions t INNER JOIN users u ON u.id = t.user_id ORDER BY t.id DESC')->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="radiantlinks-transactions-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'User', 'Reference', 'Service', 'Recipient', 'Amount', 'Status', 'Message', 'Payload / API Response']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }

    public function settings(string $area = 'general'): void
    {
        Security::requireAdmin();
        $area = in_array($area, ['general', 'payment'], true) ? $area : 'general';
        $settings = (new SettingsService($this->app->db()))->many();
        $this->app->view('admin/settings', [
            'title' => $area === 'payment' ? 'Payment Settings' : 'General Settings',
            'settingsArea' => $area,
            'settings' => $settings,
            'notice' => $_SESSION['notice'] ?? null,
            'noticeType' => $_SESSION['notice_type'] ?? 'success',
        ]);
        unset($_SESSION['notice'], $_SESSION['notice_type']);
    }

    public function generalSettings(): void
    {
        $this->settings('general');
    }

    public function paymentSettings(): void
    {
        Security::requireAdmin();
        $providers = [];
        foreach ($this->app->db()->query("SELECT * FROM provider_configs WHERE service = 'wallet_gateway' ORDER BY provider_key")->fetchAll() as $provider) {
            $providers[(string) $provider['provider_key']] = $provider;
        }
        $this->app->view('admin/payment-settings', [
            'title' => 'Payment Settings',
            'settings' => (new SettingsService($this->app->db()))->many(),
            'providers' => $providers,
            'presets' => array_filter(\App\Services\ProviderPresetService::all(), static fn (array $preset): bool => ($preset['service'] ?? '') === 'wallet_gateway'),
            'notice' => $_SESSION['notice'] ?? null,
            'noticeType' => $_SESSION['notice_type'] ?? 'success',
        ]);
        unset($_SESSION['notice'], $_SESSION['notice_type']);
    }

    public function saveSettings(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $settings = new SettingsService($this->app->db());
        $toggles = [
            'two_factor_admin_enabled',
            'two_factor_customer_enabled',
            'security_lockout_enabled',
            'admin_notify_registrations',
            'admin_notify_transactions',
            'admin_notify_blocks',
            'user_notify_registration',
            'user_notify_transactions',
            'funding_manual_enabled',
            'funding_gateway_enabled',
            'funding_dedicated_enabled',
        ];
        $sensitiveSettings = ['smtp_password', 'payment_webhook_secret'];
        $redirectTarget = (string) ($_POST['redirect_to'] ?? '/admin/settings/general');
        $paymentSave = $redirectTarget === '/admin/settings/payment';
        $activeToggles = $paymentSave
            ? ['funding_manual_enabled', 'funding_gateway_enabled', 'funding_dedicated_enabled']
            : ['two_factor_admin_enabled', 'two_factor_customer_enabled', 'security_lockout_enabled', 'admin_notify_registrations', 'admin_notify_transactions', 'admin_notify_blocks', 'user_notify_registration', 'user_notify_transactions'];
        try {
            foreach ($activeToggles as $toggle) {
                $settings->set($toggle, isset($_POST['settings'][$toggle]) ? 'on' : 'off');
            }
            foreach ($_POST['settings'] ?? [] as $key => $value) {
                if (in_array((string) $key, $toggles, true) || $key === 'site_logo_url') {
                    continue;
                }
                if (in_array((string) $key, $sensitiveSettings, true) && trim((string) $value) === '') {
                    if ((string) $settings->get((string) $key, '') !== '') {
                        continue;
                    }
                    if ($key === 'payment_webhook_secret') {
                        $value = bin2hex(random_bytes(32));
                    }
                }
                $settings->set((string) $key, $value);
            }
            if (!empty($_POST['remove_site_logo'])) {
                $this->deleteLocalLogo((string) $settings->get('site_logo_url', ''));
                $settings->set('site_logo_url', '');
            }
            if (!empty($_FILES['site_logo']['name'])) {
                $oldLogo = (string) $settings->get('site_logo_url', '');
                $settings->set('site_logo_url', $this->storeSiteLogo($_FILES['site_logo']));
                $this->deleteLocalLogo($oldLogo);
            }
            $_SESSION['notice'] = 'Settings saved successfully.';
            $_SESSION['notice_type'] = 'success';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
            $_SESSION['notice_type'] = 'error';
        }
        $redirect = (string) ($_POST['redirect_to'] ?? '/admin/settings/general');
        if (!in_array($redirect, ['/admin/settings/general', '/admin/settings/payment'], true)) {
            $redirect = '/admin/settings/general';
        }
        redirect_to($redirect);
    }

    private function storeSiteLogo(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('The logo upload could not be completed.');
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('The logo must not exceed 2MB.');
        }
        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($temporaryPath) : '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('The logo must be a PNG, JPG, or WEBP image.');
        }
        $directory = BASE_PATH . '/public/uploads/branding';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The branding upload directory could not be created.');
        }
        $name = 'logo-' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($temporaryPath, $directory . '/' . $name)) {
            throw new \RuntimeException('The logo could not be saved. Check upload directory permissions.');
        }
        return '/uploads/branding/' . $name;
    }

    private function deleteLocalLogo(string $logoUrl): void
    {
        if (!str_starts_with($logoUrl, '/uploads/branding/')) {
            return;
        }
        $name = basename($logoUrl);
        $path = BASE_PATH . '/public/uploads/branding/' . $name;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function safeRole(string $role): string
    {
        return in_array($role, ['owner', 'admin', 'staff', 'customer'], true) ? $role : 'customer';
    }
}
