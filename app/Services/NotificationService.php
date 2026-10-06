<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use PDO;

final class NotificationService
{
    public function __construct(private PDO $db)
    {
    }

    public function admins(string $subject, string $html, string $settingKey): void
    {
        $settings = (new SettingsService($this->db))->many();
        if (($settings[$settingKey] ?? 'on') !== 'on') {
            return;
        }

        $stmt = $this->db->query("SELECT email FROM users WHERE role IN ('owner','admin') AND status = 'active'");
        $mailer = new Mailer();
        foreach ($stmt->fetchAll() as $row) {
            $mailer->send((string) $row['email'], $subject, $html, $settings);
        }
    }

    public function user(array $user, string $subject, string $html, string $settingKey): void
    {
        $settings = (new SettingsService($this->db))->many();
        if (($settings[$settingKey] ?? 'on') !== 'on') {
            return;
        }

        (new Mailer())->send((string) $user['email'], $subject, $html, $settings);
    }
}
