<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\HomepageService;
use App\Services\CommerceService;
use App\Services\WalletService;

final class DashboardController
{
    public function __construct(private App $app)
    {
    }

    public function home(): void
    {
        if (!empty($_SESSION['user'])) {
            redirect_to('/dashboard');
            return;
        }
        $sections = (new HomepageService($this->app->db()))->publicSections();
        $this->app->view('public/home', ['title' => 'Radiantlinks VTU', 'sections' => $sections]);
    }

    public function user(): void
    {
        $user = Security::requireLogin();
        $fresh = $this->freshUser((int) $user['id']);
        $_SESSION['user'] = $fresh;

        $tx = $this->app->db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 6');
        $tx->execute([$user['id']]);

        $stats = [
            'transactions' => (int) $this->scalar('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$user['id']]),
            'success' => (int) $this->scalar("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'success'", [$user['id']]),
            'spent' => (float) $this->scalar("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND status = 'success'", [$user['id']]),
        ];

        $this->app->view('dashboard/user', [
            'title' => 'Dashboard',
            'user' => $fresh,
            'currentPackage' => (new CommerceService($this->app->db()))->currentPackage((int) ($fresh['package_id'] ?? 0)),
            'recentTransactions' => $tx->fetchAll(),
            'stats' => $stats,
            'fundingSettings' => (new WalletService($this->app->db()))->fundingSettings(),
            'dedicatedAccount' => (new WalletService($this->app->db()))->dedicatedAccountFor((int) $fresh['id']),
            'adminView' => false,
        ]);
    }

    public function adminUser(string $id): void
    {
        Security::requireAdmin();
        $fresh = $this->freshUser((int) $id);
        if (!$fresh) {
            http_response_code(404);
            $this->app->view('errors/404', ['title' => 'Customer not found']);
            return;
        }

        $tx = $this->app->db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 6');
        $tx->execute([$fresh['id']]);
        $stats = [
            'transactions' => (int) $this->scalar('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$fresh['id']]),
            'success' => (int) $this->scalar("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'success'", [$fresh['id']]),
            'spent' => (float) $this->scalar("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND status = 'success'", [$fresh['id']]),
        ];

        $wallet = new WalletService($this->app->db());
        $this->app->view('dashboard/user', [
            'title' => 'Customer Dashboard',
            'user' => $fresh,
            'currentPackage' => (new CommerceService($this->app->db()))->currentPackage((int) ($fresh['package_id'] ?? 0)),
            'recentTransactions' => $tx->fetchAll(),
            'stats' => $stats,
            'fundingSettings' => $wallet->fundingSettings(),
            'dedicatedAccount' => $wallet->dedicatedAccountFor((int) $fresh['id']),
            'adminView' => true,
        ]);
    }

    private function freshUser(int $id): array
    {
        $stmt = $this->app->db()->prepare('SELECT id, first_name, last_name, username, email, phone, role, package_id, status, balance, referral_code FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->app->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
