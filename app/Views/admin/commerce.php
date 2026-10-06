<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Packages, Coupons & Rewards</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
</section>

<section class="panel builder-grid">
    <form method="post" action="/admin/commerce/packages" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <h2>User Package</h2>
        <label>Name <input name="name" required placeholder="Reseller, Agent, VIP"></label>
        <label>Description <textarea name="description" rows="4"></textarea></label>
        <label>Upgrade/change cost <input type="number" step="0.01" name="upgrade_cost" value="0"></label>
        <label>Status
            <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </label>
        <button>Save Package</button>
    </form>

    <form method="post" action="/admin/commerce/rules" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <h2>Discount / Cashback Rule</h2>
        <label>Name <input name="name" required></label>
        <label>Rule type
            <select name="rule_type">
                <option value="coupon">Coupon</option>
                <option value="package_discount">Package discount</option>
                <option value="cashback">Cashback</option>
                <option value="referral">Referral bonus</option>
            </select>
        </label>
        <label>Service
            <select name="service">
                <option value="">All services</option>
                <?php foreach ($services as $service): ?><option value="<?= Security::e($service['key']) ?>"><?= Security::e($service['label']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Package
            <select name="package_id">
                <option value="">Any package</option>
                <?php foreach ($packages as $package): ?><option value="<?= (int) $package['id'] ?>"><?= Security::e($package['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Coupon code <input name="coupon_code" placeholder="WELCOME10"></label>
        <label>Value type
            <select name="value_type"><option value="percentage">Percentage</option><option value="fixed">Direct subtraction / fixed amount</option></select>
        </label>
        <label>Value <input type="number" step="0.01" name="value" required></label>
        <label>Max discount <input type="number" step="0.01" name="max_discount"></label>
        <label>Starts at <input type="datetime-local" name="starts_at"></label>
        <label>Ends at <input type="datetime-local" name="ends_at"></label>
        <label>Status
            <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </label>
        <button>Save Rule</button>
    </form>
</section>

<section class="panel">
    <h2>Packages</h2>
    <table>
        <thead><tr><th>Name</th><th>Description</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($packages as $package): ?><tr><td><?= Security::e($package['name']) ?></td><td><?= Security::e($package['description']) ?></td><td><?= Security::e($package['status']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
</section>

<section class="panel">
    <h2>Rules</h2>
    <table>
        <thead><tr><th>Name</th><th>Type</th><th>Service</th><th>Package</th><th>Code</th><th>Value</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rules as $rule): ?>
            <tr>
                <td><?= Security::e($rule['name']) ?></td>
                <td><?= Security::e($rule['rule_type']) ?></td>
                <td><?= Security::e($rule['service'] ?? 'All') ?></td>
                <td><?= Security::e($rule['package_name'] ?? 'Any') ?></td>
                <td><?= Security::e($rule['coupon_code'] ?? '') ?></td>
                <td><?= Security::e($rule['value_type']) ?> <?= number_format((float) $rule['value'], 2) ?></td>
                <td><?= Security::e($rule['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
