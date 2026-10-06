<?php use App\Core\Security; ?>
<section class="panel">
    <div class="section-head">
        <h1>All Transactions</h1>
        <a class="button-link" href="/admin/transactions/export">Export CSV</a>
    </div>
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Reference</th><th>Service</th><th>Recipient</th><th>Amount</th><th>Status</th><th>Message</th><th>Payload / API Response</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $row): ?>
            <?php
            $details = json_decode((string) ($row['details'] ?? '{}'), true) ?: [];
            $debug = $details['admin_debug'] ?? [];
            $prettyDetails = json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $payloadId = 'payload-json-' . (int) $row['id'];
            ?>
            <tr>
                <td><?= Security::e($row['created_at']) ?></td>
                <td><?= Security::e(trim($row['first_name'] . ' ' . $row['last_name'])) ?><br><span class="muted"><?= Security::e($row['email']) ?></span></td>
                <td><?= Security::e($row['reference']) ?></td>
                <td><?= Security::e($row['service']) ?></td>
                <td><?= Security::e($row['recipient']) ?></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td><?= Security::e($row['status']) ?></td>
                <td>
                    <?= Security::e($row['response_message']) ?>
                    <?php if (!empty($debug['failure_reason'])): ?>
                        <br><span class="status-pill failed"><?= Security::e($debug['failure_reason']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <button
                        type="button"
                        class="secondary-button"
                        data-payload-modal
                        data-reference="<?= Security::e($row['reference']) ?>"
                        data-payload-target="<?= Security::e($payloadId) ?>"
                    >View Payload</button>
                    <template id="<?= Security::e($payloadId) ?>"><?= Security::e((string) ($prettyDetails ?: '{}')) ?></template>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<div class="modal-backdrop" data-payload-backdrop hidden>
    <section class="payload-modal" role="dialog" aria-modal="true" aria-labelledby="payload-modal-title">
        <div class="payload-modal-head">
            <div>
                <p class="eyebrow">Transaction debug</p>
                <h2 id="payload-modal-title">Payload / API Response</h2>
                <span data-payload-reference></span>
            </div>
            <button type="button" class="secondary-button" data-payload-close>Close</button>
        </div>
        <pre data-payload-content></pre>
    </section>
</div>
