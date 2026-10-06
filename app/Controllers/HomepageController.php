<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\HomepageService;

final class HomepageController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $service = new HomepageService($this->app->db());
        $this->app->view('admin/homepage', [
            'title' => 'Homepage Builder',
            'sections' => $service->adminSections(),
            'types' => $service->typeOptions(),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function store(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        (new HomepageService($this->app->db()))->store($_POST);
        $_SESSION['notice'] = 'Homepage section saved.';
        redirect_to('/admin/homepage');
    }

    public function order(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $ids = array_filter(explode(',', (string) ($_POST['section_order'] ?? '')));
        (new HomepageService($this->app->db()))->reorder($ids);
        $_SESSION['notice'] = 'Homepage order updated.';
        redirect_to('/admin/homepage');
    }

    public function delete(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        (new HomepageService($this->app->db()))->delete((int) $id);
        $_SESSION['notice'] = 'Homepage section deleted.';
        redirect_to('/admin/homepage');
    }
}
