<?php use App\Core\Security; ?>
<?php
$firstName = (string) ($user['first_name'] ?? 'Customer');
$refLink = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080') . '/register?ref=' . ($user['referral_code'] ?? '');
$fundingSettings = $fundingSettings ?? ['charges' => []];
$adminView = !empty($adminView);
$serviceTiles = [
    ['href' => '/service/airtime', 'icon' => 'TEL', 'label' => 'Airtime', 'hint' => 'Recharge any network'],
    ['href' => '/service/data', 'icon' => 'NET', 'label' => 'Data', 'hint' => 'SME, corporate, gifting'],
    ['href' => '/service/electricity', 'icon' => 'POW', 'label' => 'Electricity', 'hint' => 'Token on receipt'],
    ['href' => '/service/cable', 'icon' => 'TV', 'label' => 'Cable TV', 'hint' => 'DSTV, GOTV, Startimes'],
    ['href' => '/service/airtime_pin', 'icon' => 'PIN', 'label' => 'Airtime PIN', 'hint' => 'Print recharge cards'],
    ['href' => '/service/data_pin', 'icon' => 'D-P', 'label' => 'Data PIN', 'hint' => 'Sell data cards'],
    ['href' => '/service/exam_epin', 'icon' => 'EX', 'label' => 'Exam ePIN', 'hint' => 'WAEC, NECO, JAMB'],
];
?>
<section class="app-home">
    <?php if ($adminView): ?>
        <div class="alert">Admin preview of <?= Security::e($user['email'] ?? 'customer') ?> dashboard. Purchases and funding actions still use the logged-in admin account.</div>
    <?php endif; ?>
    <div class="app-topline">
        <div>
            <span>Welcome back</span>
            <strong><?= Security::e($firstName) ?></strong>
        </div>
        <a href="/wallet">Fund</a>
    </div>

    <div class="app-balance-card">
        <div>
            <span>Wallet Balance</span>
            <strong>₦<?= number_format((float) ($user['balance'] ?? 0), 2) ?></strong>
        </div>
        <div class="app-balance-meta">
            <span><?= number_format((int) $stats['transactions']) ?> transactions</span>
            <span><?= Security::e($currentPackage['name'] ?? 'Default') ?> package</span>
        </div>
    </div>

    <div class="app-action-row">
        <a href="/wallet">Fund Wallet</a>
        <a href="/packages">Packages</a>
        <a href="/history">History</a>
    </div>

    <section class="app-panel wallet-dashboard-panel">
        <div class="app-section-title compact">
            <h2>Funding Account</h2>
            <a href="/wallet">Manage</a>
        </div>
        <?php if (!empty($dedicatedAccount)): ?>
            <div class="dashboard-account-number">
                <span><?= Security::e($dedicatedAccount['bank_name']) ?></span>
                <strong><?= Security::e($dedicatedAccount['account_number']) ?></strong>
                <small><?= Security::e($dedicatedAccount['account_name']) ?></small>
            </div>
        <?php elseif (!empty($fundingSettings['dedicated_enabled']) && !$adminView): ?>
            <form method="post" action="/wallet/generate-account" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <button>Generate Dedicated Account</button>
            </form>
        <?php else: ?>
            <p class="muted">Dedicated account funding is not available right now.</p>
        <?php endif; ?>
        <div class="funding-charge-list">
            <span>Manual: <?= Security::e($fundingSettings['charges']['manual_bank'] ?? 'No charge') ?></span>
            <span>Gateway: <?= Security::e($fundingSettings['charges']['gateway'] ?? 'No charge') ?></span>
            <span>Dedicated: <?= Security::e($fundingSettings['charges']['dedicated_account'] ?? 'No charge') ?></span>
        </div>
    </section>

    <div class="app-section-title">
        <h2>Services</h2>
    </div>
    <div class="app-service-grid">
        <?php foreach ($serviceTiles as $tile): ?>
            <a class="app-service-tile" href="<?= Security::e($tile['href']) ?>">
                <span><?= Security::e($tile['icon']) ?></span>
                <strong><?= Security::e($tile['label']) ?></strong>
                <small><?= Security::e($tile['hint']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="app-split">
        <section class="app-panel">
            <div class="app-section-title compact">
                <h2>Referral Link</h2>
                <a href="/referrals">View</a>
            </div>
            <input readonly value="<?= Security::e($refLink) ?>">
            <p class="muted">Share this with your customers. Rewards follow the referral rules set by admin.</p>
        </section>

        <section class="app-panel">
            <div class="app-section-title compact">
                <h2>Performance</h2>
            </div>
            <div class="mini-metrics">
                <div><span>Successful</span><strong><?= number_format((int) $stats['success']) ?></strong></div>
                <div><span>Total spent</span><strong>₦<?= number_format((float) $stats['spent'], 2) ?></strong></div>
            </div>
        </section>
    </div>

    <section class="app-panel">
        <div class="app-section-title compact">
            <h2>Recent Transactions</h2>
            <a href="/history">All</a>
        </div>
        <div class="app-history-list">
            <?php foreach ($recentTransactions as $row): ?>
                <a href="/receipt/<?= urlencode($row['reference']) ?>" class="app-history-item">
                    <span>
                        <strong><?= Security::e(strtoupper(str_replace('_', ' ', $row['service']))) ?></strong>
                        <small><?= Security::e($row['reference']) ?></small>
                    </span>
                    <em class="<?= $row['status'] === 'success' ? 'ok' : 'bad' ?>">₦<?= number_format((float) $row['amount'], 2) ?></em>
                </a>
            <?php endforeach; ?>
            <?php if (empty($recentTransactions)): ?>
                <p class="muted">No transactions yet.</p>
            <?php endif; ?>
        </div>
    </section>
</section>
