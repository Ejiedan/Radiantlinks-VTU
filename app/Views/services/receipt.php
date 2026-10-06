<?php
use App\Core\Security;
$details = json_decode($transaction['details'] ?? '{}', true) ?: [];
$options = json_decode($transaction['receipt_options'] ?? '{}', true) ?: [];
$displayAmount = ($options['display_amount'] ?? '') !== '' ? $options['display_amount'] : $transaction['amount'];
$businessName = $appSettings['site_title'] ?? 'Radiantlinks VTU';
$logoUrl = trim((string) ($appSettings['site_logo_url'] ?? ''));
$logoUrl = $logoUrl !== '' ? stored_media_url($logoUrl) : '';
?>
<section class="receipt">
    <div class="receipt-head">
        <div class="receipt-brand">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= Security::e($logoUrl) ?>" alt="<?= Security::e($businessName) ?> logo">
            <?php endif; ?>
            <div>
                <strong><?= Security::e($businessName) ?></strong>
                <span>Official Transaction Receipt</span>
            </div>
        </div>
        <div class="receipt-title">
            <h1>Transaction Receipt</h1>
        </div>
        <button type="button" data-print-receipt>Print</button>
    </div>
    <dl>
        <dt>Reference</dt><dd><?= Security::e($transaction['reference']) ?></dd>
        <dt>Service</dt><dd><?= Security::e(strtoupper($transaction['service'])) ?></dd>
        <dt>Recipient</dt><dd><?= Security::e($transaction['recipient']) ?></dd>
        <?php if (($options['show_amount'] ?? true)): ?>
            <dt>Amount</dt><dd>₦<?= number_format((float) $displayAmount, 2) ?></dd>
        <?php endif; ?>
        <?php if (!empty($options['discount'])): ?>
            <dt>Original amount</dt><dd>₦<?= number_format((float) ($options['original_amount'] ?? 0), 2) ?></dd>
            <dt>Discount</dt><dd>₦<?= number_format((float) $options['discount'], 2) ?><?= !empty($options['discount_rules']) ? ' (' . Security::e(implode(', ', $options['discount_rules'])) . ')' : '' ?></dd>
        <?php endif; ?>
        <?php if (!empty($options['customer_address'])): ?>
            <dt>Customer address</dt><dd><?= Security::e($options['customer_address']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($details['pins'])): ?>
            <dt>PINs</dt>
            <dd>
                <?php foreach ($details['pins'] as $pin): ?>
                    <div><strong><?= Security::e($pin['pin']) ?></strong> <?= Security::e($pin['serial'] ?? '') ?></div>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
        <?php if (!empty($details['token'])): ?>
            <dt>Electricity token</dt><dd><strong class="receipt-token" data-electricity-token="<?= Security::e($details['token']) ?>"><?= Security::e($details['token']) ?></strong></dd>
            <?php if (!empty($details['units'])): ?><dt>Units</dt><dd><?= Security::e($details['units']) ?></dd><?php endif; ?>
        <?php endif; ?>
        <dt>Status</dt><dd><?= Security::e($transaction['status']) ?></dd>
        <dt>Date</dt><dd><?= Security::e($transaction['created_at']) ?></dd>
    </dl>
    <?php if (!empty($options['extra_details'])): ?>
        <div class="receipt-note"><?= nl2br(Security::e($options['extra_details'])) ?></div>
    <?php endif; ?>
</section>
