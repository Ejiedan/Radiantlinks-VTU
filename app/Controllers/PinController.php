<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\InternalPinService;
use App\Services\ServiceCatalog;

final class PinController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $pins = new InternalPinService($this->app->db());
        $this->app->view('admin/pins', [
            'title' => 'PIN Manager',
            'services' => (new ServiceCatalog($this->app->db()))->allWithStatus(),
            'stock' => $pins->stockSummary(),
            'pins' => $pins->rows(),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function upload(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();

        try {
            $count = (new InternalPinService($this->app->db()))->upload($_POST);
            $_SESSION['notice'] = "Uploaded {$count} new PINs. Duplicate PINs were ignored.";
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/pins');
    }
}
