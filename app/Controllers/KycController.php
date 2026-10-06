<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\KycService;

final class KycController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        $user = Security::requireLogin();
        $kyc = new KycService($this->app->db());
        $this->app->view('kyc/index', [
            'title' => 'Verification',
            'submission' => $kyc->latestForUser((int) $user['id']),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function submit(): void
    {
        $user = Security::requireLogin();
        Security::verifyCsrf();
        try {
            (new KycService($this->app->db()))->submit((int) $user['id'], $_POST, $_FILES);
            $_SESSION['notice'] = 'Verification submitted. Admin will review it.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }
        redirect_to('/verification');
    }

    public function adminIndex(): void
    {
        Security::requireAdmin();
        $this->app->view('admin/kyc', [
            'title' => 'KYC Verification',
            'submissions' => (new KycService($this->app->db()))->all(),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function review(string $id): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();
        try {
            (new KycService($this->app->db()))->review(
                (int) $id,
                (int) $admin['id'],
                (string) ($_POST['status'] ?? 'rejected'),
                trim((string) ($_POST['admin_note'] ?? ''))
            );
            $_SESSION['notice'] = 'KYC review saved.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }
        redirect_to('/admin/kyc');
    }

    public function file(string $name): void
    {
        Security::requireAdmin();
        $safe = basename($name);
        $path = BASE_PATH . '/storage/kyc/' . $safe;
        if (!is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($path) : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
