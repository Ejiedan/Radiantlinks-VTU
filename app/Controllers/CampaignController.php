<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\CampaignService;

final class CampaignController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $campaigns = $this->app->db()->query('SELECT * FROM campaigns ORDER BY id DESC LIMIT 100')->fetchAll();
        $this->app->view('admin/campaigns', ['title' => 'Campaigns', 'campaigns' => $campaigns, 'notice' => $_SESSION['notice'] ?? null]);
        unset($_SESSION['notice']);
    }

    public function store(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $stmt = $this->app->db()->prepare(
            'INSERT INTO campaigns (name, subject, body, target_rule, date_from, date_to, mail_driver, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) $_POST['name']),
            trim((string) $_POST['subject']),
            (string) $_POST['body'],
            (string) $_POST['target_rule'],
            $_POST['date_from'] ?: null,
            $_POST['date_to'] ?: null,
            (string) $_POST['mail_driver'],
            'draft',
        ]);
        $campaignId = (int) $this->app->db()->lastInsertId();
        $count = (new CampaignService($this->app->db()))->queueRecipients($campaignId);
        $_SESSION['notice'] = "Campaign saved with {$count} queued recipient(s).";
        redirect_to('/admin/campaigns');
    }

    public function activate(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->app->db()->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute(['active', (int) $id]);
        $_SESSION['notice'] = 'Campaign activated.';
        redirect_to('/admin/campaigns');
    }

    public function send(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $sent = (new CampaignService($this->app->db()))->sendQueued((int) $id, 100);
        $_SESSION['notice'] = "Campaign batch sent: {$sent} email(s).";
        redirect_to('/admin/campaigns');
    }
}
