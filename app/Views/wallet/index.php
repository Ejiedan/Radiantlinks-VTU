<?php use App\Core\Security; ?>
<?php
$fundingSettings = $fundingSettings ?? ['charges' => []];
$dedicatedAccount = $dedicatedAccount ?? null;
$methods = [
    'manual_bank' => ['enabled' => $fundingSettings['manual_enabled'] ?? true, 'label' => 'Manual Bank Transfer'],
    'gateway' => ['enabled' => $fundingSettings['gateway_enabled'] ?? false, 'label' => 'Payment Gateway'],
    'dedicated_account' => ['enabled' => $fundingSettings['dedicated_enabled'] ?? false, 'label' => 'Dedicated Account Payment'],
];
?>
<section class="hero-panel">
    <p class="eyebrow">Wallet</p>
    <h1>₦<?= number_format((float) ($user['balance'] ?? 0), 2) ?></h1>
    <p>Track your wallet movement and transaction funding.</p>
</section>
<?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
<section class="wallet-funding-grid">
    <div class="wallet-account-card">
        <span>Dedicated Account</span>
        <?php if ($dedicatedAccount): ?>
            <strong><?= Security::e($dedicatedAccount['account_number']) ?></strong>
            <p><?= Security::e($dedicatedAccount['bank_name']) ?></p>
            <small><?= Security::e($dedicatedAccount['account_name']) ?></small>
        <?php elseif (!empty($fundingSettings['dedicated_enabled'])): ?>
            <strong>Not generated</strong>
            <p>Create your account number for automatic wallet funding.</p>
            <form method="post" action="/wallet/generate-account">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <button>Generate Account Number</button>
            </form>
        <?php else: ?>
            <strong>Disabled</strong>
            <p>Dedicated account funding is currently disabled by admin.</p>
        <?php endif; ?>
    </div>
    <div class="funding-charge-card">
        <h2>Funding Charges</h2>
        <p><b>Manual:</b> <?= Security::e($fundingSettings['charges']['manual_bank'] ?? 'No charge') ?></p>
        <p><b>Gateway:</b> <?= Security::e($fundingSettings['charges']['gateway'] ?? 'No charge') ?></p>
        <p><b>Dedicated account:</b> <?= Security::e($fundingSettings['charges']['dedicated_account'] ?? 'No charge') ?></p>
    </div>
</section>
<section class="panel">
    <h2>Fund Wallet</h2>
    <?php if (!empty($fundingSettings['manual_enabled'])): ?>
        <div class="funding-note"><?= nl2br(Security::e($fundingSettings['manual_bank_details'] ?? '')) ?></div>
    <?php endif; ?>
    <form method="post" action="/wallet/fund" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Amount <input type="number" step="0.01" name="amount" required></label>
        <label>Method
            <select name="method">
                <?php foreach ($methods as $value => $method): ?>
                    <?php if (!empty($method['enabled'])): ?><option value="<?= Security::e($value) ?>"><?= Security::e($method['label']) ?> - <?= Security::e($fundingSettings['charges'][$value] ?? 'No charge') ?></option><?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Payment Reference <input name="payment_reference" placeholder="Bank reference, teller number, narration"></label>
        <label>Proof / Note <textarea name="proof_note" rows="3" placeholder="Describe the transfer or payment proof"></textarea></label>
        <button>Submit Funding Request</button>
    </form>
</section>
<section class="panel">
    <h2>Funding Requests</h2>
    <table>
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($fundingRequests as $row): ?>
            <tr>
                <td><?= Security::e($row['created_at']) ?></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td><?= Security::e($row['method']) ?></td>
                <td><?= Security::e($row['payment_reference']) ?></td>
                <td><?= Security::e($row['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<section class="panel">
    <h2>Wallet Ledger</h2>
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($ledger as $row): ?>
            <tr>
                <td><?= Security::e($row['created_at']) ?></td>
                <td><?= Security::e($row['direction']) ?></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td>₦<?= number_format((float) $row['balance_after'], 2) ?></td>
                <td><?= Security::e($row['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
