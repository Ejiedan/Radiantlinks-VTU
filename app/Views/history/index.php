<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Transactions</h1>
    <?php if (!empty($_SESSION['notice'])): ?><p class="alert"><?= Security::e($_SESSION['notice']) ?></p><?php unset($_SESSION['notice']); endif; ?>
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Service</th><th>Recipient</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $row): ?>
            <tr>
                <td><?= Security::e($row['created_at']) ?></td>
                <td><?= Security::e($row['reference']) ?></td>
                <td><?= Security::e($row['service']) ?></td>
                <td><?= Security::e($row['recipient']) ?></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td><?= Security::e($row['status']) ?></td>
                <td><a href="/receipt/<?= urlencode($row['reference']) ?>">Receipt</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
