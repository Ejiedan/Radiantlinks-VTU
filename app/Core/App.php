<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\SettingsService;
use PDO;
use Throwable;

final class App
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public function db(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::get('DB_PORT', '3306'),
            Env::get('DB_DATABASE', 'radiantlinks_vtu')
        );

        return $this->pdo = new PDO($dsn, (string) Env::get('DB_USERNAME', 'root'), (string) Env::get('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function view(string $view, array $data = []): void
    {
        try {
            $data['appSettings'] = (new SettingsService($this->db()))->many();
        } catch (Throwable) {
            $data['appSettings'] = [];
        }
        extract($data, EXTR_SKIP);
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        ob_start();
        require BASE_PATH . '/app/Views/layout.php';
        $html = ob_get_clean();
        $base = \app_base_url();
        if ($base !== '') {
            $html = str_replace(
                ['href="/', 'src="/', 'action="/'],
                ['href="' . $base . '/', 'src="' . $base . '/', 'action="' . $base . '/'],
                $html
            );
        }
        echo $html;
    }
}
