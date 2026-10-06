<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Your Referrals</h1>
    <p class="muted">Your referral code: <strong><?= Security::e($user['referral_code'] ?? '') ?></strong></p>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($referrals as $referral): ?>
            <tr><td><?= Security::e($referral['first_name'] . ' ' . $referral['last_name']) ?></td><td><?= Security::e($referral['email']) ?></td><td><?= Security::e($referral['created_at']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
