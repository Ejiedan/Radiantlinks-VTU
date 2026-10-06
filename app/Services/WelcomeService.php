<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Mailer;
use PDO;

final class WelcomeService
{
    public function __construct(private PDO $db)
    {
    }

    public function send(array $user): bool
    {
        $settings = new SettingsService($this->db);
        $subject = (string) $settings->get('welcome_subject', 'Welcome to Radiantlinks VTU');
        $template = (string) $settings->get('welcome_template', 'Hello {first_name}, welcome to {site_name}.');
        $html = $this->render($template, $user);

        return (new Mailer())->send((string) $user['email'], $subject, $html, $settings->many());
    }

    private function render(string $template, array $user): string
    {
        $values = [
            '{first_name}' => (string) ($user['first_name'] ?? ''),
            '{last_name}' => (string) ($user['last_name'] ?? ''),
            '{email}' => (string) ($user['email'] ?? ''),
            '{phone}' => (string) ($user['phone'] ?? ''),
            '{site_name}' => (string) Env::get('APP_NAME', 'Radiantlinks VTU'),
            '{site_url}' => (string) Env::get('APP_URL', ''),
            '{current_date}' => date('F j, Y'),
        ];

        return nl2br(str_replace(array_keys($values), array_values($values), $template));
    }
}

