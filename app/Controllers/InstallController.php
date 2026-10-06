<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Env;
use App\Core\Security;
use App\Services\AuthService;

final class InstallController
{
    public function __construct(private App $app)
    {
    }

    public function show(): void
    {
        if ($this->isInstalled()) {
            redirect_to('/login');
            return;
        }
        $this->app->view('install/show', ['title' => 'Install']);
    }

    public function store(): void
    {
        if ($this->isInstalled()) {
            http_response_code(403);
            exit('Application is already installed.');
        }
        Security::verifyCsrf();
        $license = trim((string) ($_POST['owner_license_key'] ?? ''));
        if (!hash_equals((string) Env::get('OWNER_LICENSE_KEY'), $license)) {
            $this->app->view('install/show', ['title' => 'Install', 'error' => 'Invalid owner activation key.']);
            return;
        }

        $this->runSchema();
        $auth = new AuthService($this->app->db());
        $ownerId = $auth->createOwner($_POST);
        $this->app->db()->prepare('INSERT INTO installations (owner_user_id, activation_key_hash, installed_at) VALUES (?, ?, NOW())')
            ->execute([$ownerId, password_hash($license, PASSWORD_DEFAULT)]);
        redirect_to('/login');
    }

    private function isInstalled(): bool
    {
        try {
            return (int) $this->app->db()->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function runSchema(): void
    {
        $schema = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $schema) ?: []));

        foreach ($statements as $statement) {
            if ($statement !== '') {
                $this->app->db()->exec($statement);
            }
        }
    }
}
