<?php use App\Core\Security; ?>
<section class="panel">
    <h1>All Referrals</h1>
    <table>
        <thead><tr><th>User</th><th>Email</th><th>Referred By</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($referrals as $referral): ?>
            <tr>
                <td><?= Security::e($referral['first_name'] . ' ' . $referral['last_name']) ?></td>
                <td><?= Security::e($referral['email']) ?></td>
                <td><?= Security::e($referral['referrer_email'] ?? '') ?></td>
                <td><?= Security::e($referral['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
