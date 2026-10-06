<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthService
{
    public function __construct(private PDO $db)
    {
    }

    public function attempt(string $email, string $password): ?array
    {
        $login = strtolower(trim($email));
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $policy = new SecurityPolicyService($this->db);
        $policy->assertNotBlocked($user);

        if (!password_verify($password, $user['password_hash'])) {
            $settings = (new SettingsService($this->db))->many();
            if (($settings['security_lockout_enabled'] ?? 'on') === 'on') {
                $policy->hitAndMaybeBlock(
                    (int) $user['id'],
                    'password',
                    'Too many wrong password attempts',
                    (int) ($settings['wrong_password_limit'] ?? 3),
                    (int) ($settings['wrong_password_block_minutes'] ?? 15)
                );
            }
            return null;
        }

        (new SecurityPolicyService($this->db))->clearAttempts((int) $user['id'], 'password');

        $this->db->prepare('UPDATE users SET last_active_at = NOW() WHERE id = ?')->execute([$user['id']]);
        unset($user['password_hash']);
        return $user;
    }

    public function register(array $input): int
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        $phones = new PhoneNumberService($this->db);
        $phone = $phones->normalize((string) ($input['phone'] ?? ''));

        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            throw new \InvalidArgumentException('Username must be 3 to 30 characters and use only letters, numbers, or underscore.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \InvalidArgumentException('Password must include letters, numbers, and symbols.');
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }
        $phones->assertAvailable($phone);

        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, username, email, phone, password_hash, transaction_pin_hash, role, status, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) ($input['first_name'] ?? '')),
            trim((string) ($input['last_name'] ?? '')),
            $username,
            $email,
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            password_hash((string) ($input['pin'] ?? '1234'), PASSWORD_DEFAULT),
            'customer',
            'active',
            strtoupper(bin2hex(random_bytes(4))),
            $this->referrerId((string) ($input['referral_code'] ?? '')),
        ]);

        $id = (int) $this->db->lastInsertId();
        $_SESSION['user'] = $this->publicUser($id);
        $_SESSION['_last_activity'] = time();
        (new AuditService($this->db))->record($id, 'user.registered', 'user', $id);
        $user = $_SESSION['user'];
        (new WelcomeService($this->db))->send($user);
        (new NotificationService($this->db))->admins(
            'New user registered',
            '<p>New user registered: <b>' . htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') . '</b></p>',
            'admin_notify_registrations'
        );
        (new NotificationService($this->db))->user(
            $user,
            'Your account has been created',
            '<p>Your Radiantlinks VTU account has been created successfully.</p>',
            'user_notify_registration'
        );
        return $id;
    }

    public function findForReset(string $login): ?array
    {
        $login = strtolower(trim($login));
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        return $stmt->fetch() ?: null;
    }

    public function resetPassword(int $userId, string $pin, string $password, string $confirmation): void
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new \RuntimeException('Reset session is invalid.');
        }
        (new SecurityPolicyService($this->db))->assertNotBlocked($user);
        if (!password_verify($pin, (string) $user['transaction_pin_hash'])) {
            throw new \InvalidArgumentException('Invalid transaction PIN.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \InvalidArgumentException('Password must include letters, numbers, and symbols.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }

        $this->db->prepare('UPDATE users SET password_hash = ?, status = IF(status = "blocked", "active", status), blocked_until = NULL, blocked_reason = NULL WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        (new SecurityPolicyService($this->db))->clearAttempts($userId, 'password');
    }

    public function createOwner(array $input): int
    {
        $exists = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
        if ($exists > 0) {
            throw new \RuntimeException('Owner already exists.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, email, phone, password_hash, transaction_pin_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) ($input['first_name'] ?? 'Owner')),
            trim((string) ($input['last_name'] ?? '')),
            strtolower(trim((string) ($input['email'] ?? ''))),
            trim((string) ($input['phone'] ?? '')),
            password_hash((string) ($input['password'] ?? ''), PASSWORD_DEFAULT),
            password_hash((string) ($input['pin'] ?? '1234'), PASSWORD_DEFAULT),
            'owner',
            'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function publicUser(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id, first_name, last_name, username, email, phone, role, package_id, status, balance, referral_code FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function referrerId(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}
