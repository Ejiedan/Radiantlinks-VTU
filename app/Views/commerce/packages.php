<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Packages</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <p>Current package: <strong><?= Security::e($currentPackage['name'] ?? 'Default') ?></strong></p>
    <div class="service-grid">
        <?php foreach ($packages as $package): ?>
            <form method="post" action="/packages/change" class="service-tile">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="package_id" value="<?= (int) $package['id'] ?>">
                <strong><?= Security::e($package['name']) ?></strong>
                <span><?= Security::e($package['description'] ?? '') ?></span>
                <span>Change cost: ₦<?= number_format((float) ($package['upgrade_cost'] ?? 0), 2) ?></span>
                <button><?= (int) ($user['package_id'] ?? 0) === (int) $package['id'] ? 'Current' : 'Switch Package' ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
