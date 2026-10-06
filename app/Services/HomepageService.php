<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class HomepageService
{
    public function __construct(private PDO $db)
    {
    }

    public function ensureTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS homepage_sections (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(40) NOT NULL,
                title VARCHAR(191) NOT NULL,
                subtitle TEXT NULL,
                content JSON NULL,
                button_label VARCHAR(80) NULL,
                button_url VARCHAR(255) NULL,
                image_url VARCHAR(255) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY homepage_status_order_idx (status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function publicSections(): array
    {
        $this->ensureTable();
        $rows = $this->db->query("SELECT * FROM homepage_sections WHERE status = 'active' ORDER BY sort_order, id")->fetchAll();
        return $rows ? $this->decodeRows($rows) : $this->defaults();
    }

    public function adminSections(): array
    {
        $this->ensureTable();
        $rows = $this->db->query('SELECT * FROM homepage_sections ORDER BY sort_order, id')->fetchAll();
        if (!$rows) {
            $this->seedDefaults();
            $rows = $this->db->query('SELECT * FROM homepage_sections ORDER BY sort_order, id')->fetchAll();
        }
        return $this->decodeRows($rows);
    }

    public function store(array $data): void
    {
        $this->ensureTable();
        $id = (int) ($data['id'] ?? 0);
        $content = $this->normalizeContent(
            (string) ($data['type'] ?? 'text'),
            (string) ($data['items'] ?? ''),
            (string) ($data['content_text'] ?? '')
        );
        $payload = [
            trim((string) ($data['type'] ?? 'text')),
            trim((string) ($data['title'] ?? '')),
            trim((string) ($data['subtitle'] ?? '')),
            json_encode($content, JSON_UNESCAPED_SLASHES),
            trim((string) ($data['button_label'] ?? '')),
            trim((string) ($data['button_url'] ?? '')),
            trim((string) ($data['image_url'] ?? '')),
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];

        if ($id > 0) {
            $payload[] = $id;
            $stmt = $this->db->prepare(
                'UPDATE homepage_sections SET type = ?, title = ?, subtitle = ?, content = ?, button_label = ?, button_url = ?, image_url = ?, sort_order = ?, status = ? WHERE id = ?'
            );
            $stmt->execute($payload);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO homepage_sections (type, title, subtitle, content, button_label, button_url, image_url, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($payload);
    }

    public function reorder(array $ids): void
    {
        $this->ensureTable();
        $stmt = $this->db->prepare('UPDATE homepage_sections SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([($index + 1) * 10, (int) $id]);
        }
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $stmt = $this->db->prepare('DELETE FROM homepage_sections WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function typeOptions(): array
    {
        return [
            'hero' => 'Hero',
            'stats' => 'Stats',
            'services' => 'Service grid',
            'feature_cards' => 'Feature cards',
            'split' => 'Split band',
            'cta' => 'Call to action',
        ];
    }

    public function defaults(): array
    {
        return [
            [
                'id' => 0,
                'type' => 'hero',
                'title' => 'Buy airtime, data, bills, and ePINs from one clean wallet.',
                'subtitle' => 'Fast. Secure. Always available.',
                'button_label' => 'Start Buying',
                'button_url' => '/register',
                'content' => [
                    'text' => 'Radiantlinks VTU gives customers a smooth way to top up, pay bills, buy exam PINs, and keep clean receipts from one secure wallet.',
                    'proof' => ['Instant wallet ledger', 'Printable receipts', 'Bulk purchases'],
                ],
            ],
            [
                'id' => 0,
                'type' => 'stats',
                'title' => '',
                'subtitle' => '',
                'content' => ['items' => [
                    ['title' => '6+', 'text' => 'Core services'],
                    ['title' => '24/7', 'text' => 'Automated vending'],
                    ['title' => 'Easy', 'text' => 'Clear receipts'],
                    ['title' => 'Secure', 'text' => 'Wallet protection'],
                ]],
            ],
            [
                'id' => 0,
                'type' => 'services',
                'title' => 'Everything customers expect from a VTU app.',
                'subtitle' => 'Services',
                'content' => ['items' => [
                    ['title' => 'Airtime', 'text' => 'Top up any major network in a few taps.'],
                    ['title' => 'Data', 'text' => 'Choose affordable plans and send data instantly.'],
                    ['title' => 'Exam ePIN', 'text' => 'Buy WAEC, NECO, JAMB, and other exam PINs with receipts.'],
                    ['title' => 'Data PIN', 'text' => 'Purchase data cards for yourself or customers.'],
                    ['title' => 'Electricity', 'text' => 'Pay prepaid and postpaid bills with clear customer details.'],
                    ['title' => 'Bulk Orders', 'text' => 'Send many purchases at once and track each one.'],
                ]],
            ],
            [
                'id' => 0,
                'type' => 'split',
                'title' => 'A reliable platform for everyday digital payments.',
                'subtitle' => 'Built For Growth',
                'content' => ['items' => [
                    ['title' => 'Wallet funding', 'text' => 'Tracked'],
                    ['title' => 'Data purchase', 'text' => 'Instant'],
                    ['title' => 'Electricity bill', 'text' => 'Receipted'],
                    ['title' => 'Bulk orders', 'text' => 'Organized'],
                ]],
            ],
        ];
    }

    private function seedDefaults(): void
    {
        foreach ($this->defaults() as $index => $section) {
            $stmt = $this->db->prepare(
                'INSERT INTO homepage_sections (type, title, subtitle, content, button_label, button_url, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $section['type'],
                $section['title'],
                $section['subtitle'],
                json_encode($section['content'], JSON_UNESCAPED_SLASHES),
                $section['button_label'] ?? '',
                $section['button_url'] ?? '',
                ($index + 1) * 10,
                'active',
            ]);
        }
    }

    private function decodeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['content'] ?? ''), true);
            $row['content'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }

    private function normalizeContent(string $type, string $items, string $text): array
    {
        $lines = preg_split('/\R/', $items) ?: [];
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$title, $text] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $parsed[] = ['title' => $title, 'text' => $text];
        }

        if ($type === 'hero') {
            return [
                'text' => trim($text),
                'proof' => array_values(array_filter(array_map('trim', explode(',', $items)))),
            ];
        }

        return ['text' => trim($text), 'items' => $parsed];
    }
}
