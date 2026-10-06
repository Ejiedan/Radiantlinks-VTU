<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\CommerceService;
use App\Services\ServiceCatalog;

final class CommerceController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $commerce = new CommerceService($this->app->db());
        $this->app->view('admin/commerce', [
            'title' => 'Packages & Rewards',
            'packages' => $commerce->packages(),
            'rules' => $commerce->rules(),
            'services' => (new ServiceCatalog($this->app->db()))->allWithStatus(),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function packageStore(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        (new CommerceService($this->app->db()))->savePackage($_POST);
        $_SESSION['notice'] = 'Package saved.';
        redirect_to('/admin/commerce');
    }

    public function ruleStore(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        (new CommerceService($this->app->db()))->saveRule($_POST);
        $_SESSION['notice'] = 'Commerce rule saved.';
        redirect_to('/admin/commerce');
    }

    public function userPackages(): void
    {
        $user = Security::requireLogin();
        $commerce = new CommerceService($this->app->db());
        $this->app->view('commerce/packages', [
            'title' => 'Packages',
            'user' => $user,
            'packages' => $commerce->packages(),
            'currentPackage' => $commerce->currentPackage((int) ($user['package_id'] ?? 0)),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function changePackage(): void
    {
        $user = Security::requireLogin();
        Security::verifyCsrf();
        try {
            (new CommerceService($this->app->db()))->changePackage((int) $user['id'], (int) ($_POST['package_id'] ?? 0));
            $stmt = $this->app->db()->prepare('SELECT id, first_name, last_name, username, email, phone, role, package_id, status, balance, referral_code FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $_SESSION['user'] = $stmt->fetch() ?: $user;
            $_SESSION['notice'] = 'Package changed successfully.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }
        redirect_to('/packages');
    }

    public function userReferrals(): void
    {
        $user = Security::requireLogin();
        $commerce = new CommerceService($this->app->db());
        $this->app->view('commerce/referrals', [
            'title' => 'Referrals',
            'user' => $user,
            'referrals' => $commerce->referralsFor((int) $user['id']),
        ]);
    }

    public function adminReferrals(): void
    {
        Security::requireAdmin();
        $this->app->view('admin/referrals', [
            'title' => 'All Referrals',
            'referrals' => (new CommerceService($this->app->db()))->allReferrals(),
        ]);
    }
}
