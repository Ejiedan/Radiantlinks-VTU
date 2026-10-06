<?php use App\Core\Security; ?>
<section class="panel">
    <h1>KYC Verification</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <table>
        <thead><tr><th>User</th><th>Details</th><th>Files</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $row): ?>
            <tr>
                <td><?= Security::e($row['email']) ?><br><small><?= Security::e($row['phone']) ?></small></td>
                <td>
                    <strong><?= Security::e($row['full_name']) ?></strong><br>
                    DOB: <?= Security::e($row['dob']) ?><br>
                    <?= strtoupper(Security::e($row['id_type'])) ?>: <?= Security::e($row['id_number']) ?><br>
                    <small><?= Security::e($row['address']) ?></small>
                </td>
                <td>
                    <a href="/admin/kyc/file/<?= Security::e(basename($row['selfie_path'])) ?>" target="_blank">Selfie</a><br>
                    <a href="/admin/kyc/file/<?= Security::e(basename($row['id_document_path'])) ?>" target="_blank">Valid ID</a>
                </td>
                <td>
                    <span class="status-badge <?= Security::e($row['status']) ?>"><?= Security::e($row['status']) ?></span><br>
                    <small><?= Security::e($row['created_at']) ?></small>
                </td>
                <td>
                    <form method="post" action="/admin/kyc/<?= (int) $row['id'] ?>/review" class="stacked-form compact-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <select name="status">
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                        <textarea name="admin_note" rows="2" placeholder="Admin note"><?= Security::e($row['admin_note']) ?></textarea>
                        <button>Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
