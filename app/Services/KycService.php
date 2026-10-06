<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class KycService
{
    public function __construct(private PDO $db)
    {
        $this->ensureSchema();
    }

    public function latestForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kyc_submissions WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function approvedForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kyc_submissions WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function submit(int $userId, array $data, array $files): void
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $dob = trim((string) ($data['dob'] ?? ''));
        $idType = strtolower(trim((string) ($data['id_type'] ?? '')));
        $idNumber = preg_replace('/\D+/', '', (string) ($data['id_number'] ?? '')) ?: '';
        $address = trim((string) ($data['address'] ?? ''));

        if ($fullName === '' || $dob === '' || !in_array($idType, ['nin', 'bvn'], true) || $idNumber === '') {
            throw new \InvalidArgumentException('Full name, date of birth, and NIN/BVN are required.');
        }
        if (($idType === 'bvn' && strlen($idNumber) !== 11) || ($idType === 'nin' && strlen($idNumber) !== 11)) {
            throw new \InvalidArgumentException(strtoupper($idType) . ' must be 11 digits.');
        }

        $selfie = $this->storeUpload($files['selfie'] ?? null, $userId, 'selfie');
        $idDocument = $this->storeUpload($files['id_document'] ?? null, $userId, 'id');

        $stmt = $this->db->prepare(
            'INSERT INTO kyc_submissions (user_id, full_name, dob, id_type, id_number, address, selfie_path, id_document_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $fullName, $dob, $idType, $idNumber, $address, $selfie, $idDocument, 'pending']);
        $this->db->prepare("UPDATE users SET kyc_status = 'pending' WHERE id = ?")->execute([$userId]);
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT k.*, u.email, u.phone FROM kyc_submissions k INNER JOIN users u ON u.id = k.user_id ORDER BY k.id DESC LIMIT 300'
        )->fetchAll();
    }

    public function review(int $id, int $adminId, string $status, string $note = ''): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Invalid KYC review status.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM kyc_submissions WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $kyc = $stmt->fetch();
            if (!$kyc) {
                throw new \RuntimeException('KYC submission not found.');
            }

            $this->db->prepare('UPDATE kyc_submissions SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, $note, $adminId, $id]);
            $this->db->prepare('UPDATE users SET kyc_status = ? WHERE id = ?')
                ->execute([$status === 'approved' ? 'verified' : 'rejected', (int) $kyc['user_id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function storeUpload(?array $file, int $userId, string $kind): string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(ucfirst($kind) . ' upload is required.');
        }
        if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
            throw new \InvalidArgumentException('KYC files must not exceed 4MB each.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmp) : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('Upload must be JPG, PNG, WEBP, or PDF.');
        }

        $dir = BASE_PATH . '/storage/kyc';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $userId . '-' . $kind . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Could not save uploaded KYC file.');
        }
        return 'storage/kyc/' . $name;
    }

    private function ensureSchema(): void
    {
        $this->addColumn('users', 'kyc_status', "ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none' AFTER address");
        $this->db->exec("CREATE TABLE IF NOT EXISTS kyc_submissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            full_name VARCHAR(191) NOT NULL,
            dob DATE NOT NULL,
            id_type ENUM('nin','bvn') NOT NULL,
            id_number VARCHAR(40) NOT NULL,
            address TEXT NULL,
            selfie_path VARCHAR(255) NOT NULL,
            id_document_path VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY kyc_user_status_idx (user_id, status),
            KEY kyc_status_idx (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function addColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
}
