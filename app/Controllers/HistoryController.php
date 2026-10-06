<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;

final class HistoryController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        $user = Security::requireLogin();
        $stmt = $this->app->db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 200');
        $stmt->execute([$user['id']]);
        $this->app->view('history/index', ['title' => 'Transactions', 'transactions' => $stmt->fetchAll()]);
    }
}

