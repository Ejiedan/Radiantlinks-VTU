<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\ServiceCatalog;
use App\Services\SettingsService;

final class ServiceAdminController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $this->app->view('admin/services', [
            'title' => 'Services',
            'services' => (new ServiceCatalog($this->app->db()))->allWithStatus(),
            'typeControls' => (new ServiceCatalog($this->app->db()))->typeControls(),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function save(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $settings = new SettingsService($this->app->db());
        foreach (ServiceCatalog::SERVICES as $key => $label) {
            $settings->set('service_' . $key . '_status', ($_POST['services'][$key] ?? 'off') === 'on' ? 'on' : 'off');
        }
        $catalog = new ServiceCatalog($this->app->db());
        foreach ($catalog->allWithStatus() as $service) {
            if (array_key_exists($service['key'], ServiceCatalog::SERVICES)) {
                continue;
            }
            $catalog->saveCustom($service['key'], $service['label'], ($_POST['services'][$service['key']] ?? 'off') === 'on' ? 'on' : 'off');
        }
        if (!empty($_POST['new_service_key']) && !empty($_POST['new_service_label'])) {
            $catalog->saveCustom((string) $_POST['new_service_key'], (string) $_POST['new_service_label'], 'on');
        }
        foreach ($_POST['type_controls'] ?? [] as $id => $control) {
            $catalog->saveTypeControl((string) $control['service'], (string) $control['provider'], (string) $control['service_type'], (($control['status'] ?? 'off') === 'on') ? 'on' : 'off');
        }
        if (!empty($_POST['new_type_service']) && !empty($_POST['new_service_type'])) {
            $catalog->saveTypeControl((string) $_POST['new_type_service'], (string) ($_POST['new_type_provider'] ?? '*'), (string) $_POST['new_service_type'], 'on');
        }
        $_SESSION['notice'] = 'Service status updated.';
        redirect_to('/admin/services');
    }
}
