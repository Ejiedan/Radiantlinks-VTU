<?php use App\Core\Security; ?>
<div class="admin-balance-strip"><i class="mdi mdi-wallet"></i> Total users balance <strong>₦<?= number_format((float) $stats['balance'], 2) ?></strong></div>

<section class="admin-kpi-grid">
    <div class="admin-kpi-card rose"><i class="mdi mdi-cube-outline"></i><span>Total Revenue</span><strong>₦<?= number_format((float) $stats['success_volume'], 2) ?></strong><small>Successful transactions</small></div>
    <div class="admin-kpi-card amber"><i class="mdi mdi-receipt-text-outline"></i><span>Orders</span><strong><?= number_format((int) $stats['transactions']) ?></strong><small><?= number_format((int) $stats['weekly_transactions']) ?> this week</small></div>
    <div class="admin-kpi-card cyan"><i class="mdi mdi-chart-bar"></i><span>Sales</span><strong>₦<?= number_format((float) $stats['weekly_success_volume'], 2) ?></strong><small>This week</small></div>
    <div class="admin-kpi-card blue"><i class="mdi mdi-account-group-outline"></i><span>Users</span><strong><?= number_format((int) $stats['users']) ?></strong><small><?= number_format((int) $stats['active_users']) ?> active customers</small></div>
</section>

<section class="admin-gradient-grid">
    <a class="admin-gradient-card sales" href="/admin/transactions"><span>Weekly Sales</span><strong>₦<?= number_format((float) $stats['weekly_success_volume'], 2) ?></strong><small>Review transaction activity</small></a>
    <a class="admin-gradient-card orders" href="/admin/funding"><span>Weekly Orders</span><strong><?= number_format((int) $stats['weekly_transactions']) ?></strong><small>Process pending wallet funding</small></a>
    <a class="admin-gradient-card online" href="/admin/users"><span>Users Online</span><strong><?= number_format((int) $stats['online_users']) ?></strong><small>Active in the last 15 minutes</small></a>
</section>

<section class="admin-wide-metrics">
    <div><span>Banned Users</span><strong><?= number_format((int) $stats['blocked_users']) ?></strong></div>
    <div><span>Failed Transactions</span><strong><?= number_format((int) $stats['failed_transactions']) ?></strong></div>
    <div><span>Total Users Balance</span><strong>₦<?= number_format((float) $stats['balance'], 2) ?></strong></div>
    <div><span>Top Sales</span><strong>₦<?= number_format((float) $stats['success_volume'], 0) ?></strong></div>
</section>

<?php
$chartMax = max(1, ...array_map(static fn (array $day): float => max($day['success'], $day['failed'], $day['pending']), array_values($weeklyChart)));
$mixTotal = $serviceMix['airtime'] + $serviceMix['data'];
$airtimeShare = $mixTotal > 0 ? round($serviceMix['airtime'] / $mixTotal * 100) : 0;
?>
<section class="admin-analytics">
    <div class="panel admin-chart-panel">
        <div class="section-head"><h2>Weekly Sales Statistics</h2><div class="admin-chart-legend"><span class="failed">Failed</span><span class="success">Success</span><span class="pending">Pending</span></div></div>
        <div class="admin-bar-chart" aria-label="Sales totals by day and status for the last seven days">
            <?php foreach ($weeklyChart as $day): ?>
                <div class="admin-chart-day">
                    <div class="admin-chart-bars">
                        <?php foreach (['failed', 'success', 'pending'] as $status): ?>
                            <span class="<?= $status ?>" style="height: <?= round((float) $day[$status] / $chartMax * 100, 2) ?>%" title="<?= Security::e(ucfirst($status)) ?>: ₦<?= number_format((float) $day[$status], 2) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <small><?= Security::e($day['label']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel admin-mix-panel">
        <h2>Airtime Vs Data</h2>
        <p class="muted">Transaction volume</p>
        <div class="admin-donut" style="--airtime-share: <?= $airtimeShare ?>%"><div><strong><?= number_format($mixTotal) ?></strong><small>orders</small></div></div>
        <div class="admin-mix-legend"><span><i class="airtime"></i>Airtime <strong><?= number_format($serviceMix['airtime']) ?></strong></span><span><i class="data"></i>Data <strong><?= number_format($serviceMix['data']) ?></strong></span></div>
    </div>
</section>

<section class="panel admin-quick-panel">
    <div class="section-head">
        <div>
            <h2>Quick Access</h2>
            <p class="muted">Fast routes to the parts you will use every day.</p>
        </div>
    </div>
    <div class="service-grid admin-service-grid">
        <a class="service-tile" href="/admin/settings/general"><strong>General Settings</strong><span>Branding, mail, receipts, sessions and 2FA</span></a>
        <a class="service-tile" href="/admin/settings/payment"><strong>Payment Settings</strong><span>Gateways, funding, dedicated accounts and webhooks</span></a>
        <a class="service-tile" href="/admin/users"><strong>Users</strong><span>Create, edit, block, suspend and assign roles</span></a>
        <a class="service-tile" href="/admin/service-settings/data"><strong>Data Settings</strong><span>Add plans and configure Data API providers</span></a>
        <a class="service-tile" href="/admin/service-settings/airtime"><strong>Airtime Settings</strong><span>Set airtime plans, providers, and API credentials</span></a>
        <a class="service-tile" href="/admin/providers"><strong>All API Providers</strong><span>Central list of saved credentials and mappings</span></a>
        <a class="service-tile" href="/admin/services"><strong>Service Switches</strong><span>Turn services, networks and service types on or off</span></a>
        <a class="service-tile" href="/admin/commerce"><strong>Packages & Rewards</strong><span>Coupons, discounts, cashback and referrals</span></a>
        <a class="service-tile" href="/admin/homepage"><strong>Homepage</strong><span>Choose template and drag sections</span></a>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <h2>Recent Transactions</h2>
        <a class="button-link secondary" href="/admin/transactions">View All</a>
    </div>
    <table>
        <thead><tr><th>User</th><th>Reference</th><th>Service</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
            <tr>
                <td><?= Security::e($row['email']) ?></td>
                <td><?= Security::e($row['reference']) ?></td>
                <td><?= Security::e($row['service']) ?></td>
                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                <td><span class="status-pill <?= Security::e((string) $row['status']) ?>"><?= Security::e($row['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
