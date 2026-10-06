<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Funding Requests</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($requests as $row): ?>
            <tr>
                <td><?= Security::e($row['created_at']) ?></td>
                <td><?= Security::e(trim($row['first_name'] . ' ' . $row['last_name'])) ?><br><span class="muted"><?= Security::e($row['email']) ?></span></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td><?= Security::e($row['method']) ?></td>
                <td><?= Security::e($row['payment_reference']) ?><br><span class="muted"><?= Security::e($row['proof_note']) ?></span></td>
                <td><?= Security::e($row['status']) ?></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <form method="post" action="/admin/funding/<?= (int) $row['id'] ?>/approve" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <input name="admin_note" placeholder="Admin note">
                            <button data-confirm="Approve and credit this wallet?">Approve</button>
                        </form>
                        <form method="post" action="/admin/funding/<?= (int) $row['id'] ?>/reject" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <input name="admin_note" placeholder="Reason">
                            <button data-confirm="Reject this funding request?">Reject</button>
                        </form>
                    <?php else: ?>
                        <span class="muted"><?= Security::e($row['admin_note']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

