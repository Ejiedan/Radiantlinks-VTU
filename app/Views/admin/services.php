<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Service Controls</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <div class="toggle-list">
            <?php foreach ($services as $service): ?>
                <div class="toggle-row">
                    <span>
                        <strong><?= Security::e($service['label']) ?></strong>
                        <small><?= Security::e($service['key']) ?></small>
                    </span>
                    <span class="on-off-control">
                        <input id="service-<?= Security::e($service['key']) ?>-on" type="radio" name="services[<?= Security::e($service['key']) ?>]" value="on" <?= $service['status'] === 'on' ? 'checked' : '' ?>>
                        <label class="on" for="service-<?= Security::e($service['key']) ?>-on">ON</label>
                        <input id="service-<?= Security::e($service['key']) ?>-off" type="radio" name="services[<?= Security::e($service['key']) ?>]" value="off" <?= $service['status'] !== 'on' ? 'checked' : '' ?>>
                        <label class="off" for="service-<?= Security::e($service['key']) ?>-off">OFF</label>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <h2>Add Custom Service</h2>
        <div class="form-two">
            <label>Service key <input name="new_service_key" placeholder="verification, insurance"></label>
            <label>Service label <input name="new_service_label" placeholder="Verification"></label>
        </div>
        <h2>Service Type Controls</h2>
        <p class="muted">Use provider <code>*</code> or type <code>*</code> to apply a rule broadly. Examples: data / mtn / sme, data / mtn / corporate, data / * / sme.</p>
        <?php foreach ($typeControls as $control): ?>
            <div class="toggle-row">
                <span>
                    <strong><?= Security::e($control['service']) ?> · <?= Security::e($control['provider']) ?> · <?= Security::e($control['service_type']) ?></strong>
                    <small>Service type control</small>
                </span>
                <input type="hidden" name="type_controls[<?= (int) $control['id'] ?>][service]" value="<?= Security::e($control['service']) ?>">
                <input type="hidden" name="type_controls[<?= (int) $control['id'] ?>][provider]" value="<?= Security::e($control['provider']) ?>">
                <input type="hidden" name="type_controls[<?= (int) $control['id'] ?>][service_type]" value="<?= Security::e($control['service_type']) ?>">
                <span class="on-off-control">
                    <input id="type-control-<?= (int) $control['id'] ?>-on" type="radio" name="type_controls[<?= (int) $control['id'] ?>][status]" value="on" <?= $control['status'] === 'on' ? 'checked' : '' ?>>
                    <label class="on" for="type-control-<?= (int) $control['id'] ?>-on">ON</label>
                    <input id="type-control-<?= (int) $control['id'] ?>-off" type="radio" name="type_controls[<?= (int) $control['id'] ?>][status]" value="off" <?= $control['status'] !== 'on' ? 'checked' : '' ?>>
                    <label class="off" for="type-control-<?= (int) $control['id'] ?>-off">OFF</label>
                </span>
            </div>
        <?php endforeach; ?>
        <div class="form-two">
            <label>Service <input name="new_type_service" placeholder="data"></label>
            <label>Provider/network <input name="new_type_provider" placeholder="mtn or *"></label>
        </div>
        <label>Service type <input name="new_service_type" placeholder="sme, corporate, gifting or *"></label>
        <button>Save Service Status</button>
    </form>
</section>
