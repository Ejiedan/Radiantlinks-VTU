<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use PDO;

final class CampaignService
{
    public function __construct(private PDO $db)
    {
    }

    public function targetUsers(string $rule, ?string $dateFrom, ?string $dateTo): array
    {
        return match ($rule) {
            'funded_wallet' => $this->fundedUsers(),
            'no_funds' => $this->noFundUsers(),
            'active' => $this->activityUsers(true, $dateFrom, $dateTo),
            'inactive' => $this->activityUsers(false, $dateFrom, $dateTo),
            default => [],
        };
    }

    public function queueRecipients(int $campaignId): int
    {
        $campaign = $this->campaign($campaignId);
        if (!$campaign) {
            return 0;
        }

        $users = $this->targetUsers($campaign['target_rule'], $campaign['date_from'], $campaign['date_to']);
        $stmt = $this->db->prepare('INSERT IGNORE INTO campaign_sends (campaign_id, user_id, status) VALUES (?, ?, ?)');

        foreach ($users as $user) {
            $stmt->execute([$campaignId, $user['id'], 'queued']);
        }

        $count = count($users);
        $this->db->prepare('UPDATE campaigns SET total_recipients = ? WHERE id = ?')->execute([$count, $campaignId]);
        return $count;
    }

    public function sendQueued(int $campaignId, int $limit = 100): int
    {
        $campaign = $this->campaign($campaignId);
        if (!$campaign || $campaign['status'] !== 'active') {
            return 0;
        }

        $settings = (new SettingsService($this->db))->many();
        $mailer = new Mailer();
        $stmt = $this->db->prepare(
            'SELECT cs.id AS send_id, u.* FROM campaign_sends cs INNER JOIN users u ON u.id = cs.user_id WHERE cs.campaign_id = ? AND cs.status = ? LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$campaignId, 'queued']);
        $rows = $stmt->fetchAll();

        $sent = 0;
        foreach ($rows as $row) {
            $body = $this->renderTemplate((string) $campaign['body'], $row);
            $ok = $mailer->send((string) $row['email'], (string) $campaign['subject'], $body, array_merge($settings, [
                'mail_driver' => $campaign['mail_driver'],
            ]));

            $this->db->prepare('UPDATE campaign_sends SET status = ?, error_message = ?, sent_at = ? WHERE id = ?')
                ->execute([$ok ? 'sent' : 'failed', $ok ? null : 'Mail delivery failed', $ok ? date('Y-m-d H:i:s') : null, $row['send_id']]);

            if ($ok) {
                $sent++;
            }
        }

        $this->db->prepare('UPDATE campaigns SET sent_count = sent_count + ? WHERE id = ?')->execute([$sent, $campaignId]);
        return $sent;
    }

    private function campaign(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM campaigns WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function fundedUsers(): array
    {
        return $this->db->query("SELECT * FROM users WHERE status = 'active' AND balance > 0")->fetchAll();
    }

    private function noFundUsers(): array
    {
        return $this->db->query("SELECT * FROM users WHERE status = 'active' AND balance <= 0")->fetchAll();
    }

    private function activityUsers(bool $active, ?string $dateFrom, ?string $dateTo): array
    {
        $from = $dateFrom ?: '1970-01-01';
        $to = $dateTo ?: date('Y-m-d');

        if ($active) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT u.* FROM users u INNER JOIN transactions t ON t.user_id = u.id WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"
            );
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            "SELECT u.* FROM users u WHERE NOT EXISTS (SELECT 1 FROM transactions t WHERE t.user_id = u.id AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    private function renderTemplate(string $template, array $user): string
    {
        return nl2br(str_replace(
            ['{first_name}', '{last_name}', '{email}', '{phone}', '{balance}', '{current_date}'],
            [
                (string) $user['first_name'],
                (string) $user['last_name'],
                (string) $user['email'],
                (string) $user['phone'],
                number_format((float) $user['balance'], 2),
                date('F j, Y'),
            ],
            $template
        ));
    }
}

