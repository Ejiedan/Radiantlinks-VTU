<?php use App\Core\Security; use App\Services\ProviderAuthService; ?>
<?php $selectedGateway = strtolower((string) ($settings['funding_gateway_provider'] ?? 'demo')); ?>
<section class="panel settings-shell">
    <div class="section-head"><div><h1>Payment Settings</h1><p class="muted">Choose a gateway below to configure its credentials, account generation, checkout, and webhook connection.</p></div><a class="button-link secondary" href="/admin/settings/general">General Settings</a></div>
    <?php if (!empty($notice)): ?><p class="alert <?= ($noticeType ?? 'success') === 'error' ? 'failed' : '' ?>"><?= Security::e($notice) ?></p><?php endif; ?>
    <div class="gateway-list">
        <?php foreach (($presets ?? []) as $key => $preset): ?>
            <?php $provider = $providers[$key] ?? null; $credentials = $provider ? (json_decode((string) ($provider['credentials'] ?? '{}'), true) ?: []) : []; $isActive = $selectedGateway === $key; ?>
            <details class="admin-dropdown gateway-card" <?= $isActive || $provider ? 'open' : '' ?>>
                <summary><span><strong><?= Security::e($preset['name']) ?></strong><small><?= Security::e($preset['notes'] ?? 'Payment gateway') ?></small></span><span class="gateway-status <?= $provider && $provider['status'] === 'active' ? 'on' : 'off' ?>"><?= $provider ? Security::e($provider['status']) : 'not configured' ?></span></summary>
                <form method="post" action="<?= $provider ? '/admin/providers/' . (int) $provider['id'] . '/update' : '/admin/providers' ?>" class="stacked-form gateway-form">
                    <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input type="hidden" name="redirect_to" value="/admin/settings/payment">
                    <input type="hidden" name="service" value="wallet_gateway">
                    <?php if (!$provider): ?><input type="hidden" name="preset" value="<?= Security::e((string) $key) ?>"><?php endif; ?>
                    <div class="form-two"><label>Gateway name<input name="name" value="<?= Security::e($provider['name'] ?? $preset['name']) ?>" required></label><label>Provider key<input name="provider_key" value="<?= Security::e($provider['provider_key'] ?? $key) ?>" required></label></div>
                    <label>Base URL<input name="base_url" value="<?= Security::e($provider['base_url'] ?? $preset['base_url'] ?? '') ?>" required></label>
                    <label>Authentication type<select name="auth_type"><?php foreach (ProviderAuthService::options() as $auth => $label): ?><option value="<?= Security::e($auth) ?>" <?= (($credentials['auth_type'] ?? $preset['auth_type'] ?? 'none') === $auth) ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select></label>
                    <div class="form-two">
                        <?php foreach (['api_key' => 'API key', 'secret_key' => 'Secret key', 'token' => 'Token/Bearer', 'public_key' => 'Public key', 'contract_code' => 'Contract code', 'account_id' => 'Account ID'] as $field => $label): ?>
                            <label><?= Security::e($label) ?><input name="<?= $field ?>" type="password" value="" placeholder="<?= !empty($credentials[$field]) ? 'Saved securely - leave blank to keep' : 'Enter ' . strtolower($label) ?>"></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-two"><label>Client ID<input name="client_id" value="<?= Security::e($credentials['client_id'] ?? '') ?>"></label><label>Client secret<input name="client_secret" type="password" placeholder="<?= !empty($credentials['client_secret']) ? 'Saved securely - leave blank to keep' : 'Optional' ?>"></label></div>
                    <div class="form-two"><label>Basic Auth username<input name="username" value="<?= Security::e($credentials['username'] ?? '') ?>"></label><label>Basic Auth password<input name="password" type="password" placeholder="<?= !empty($credentials['password']) ? 'Saved securely - leave blank to keep' : 'Optional' ?>"></label></div>
                    <label>Checkout endpoint<input name="checkout_endpoint" value="<?= Security::e($credentials['checkout_endpoint'] ?? '') ?>" placeholder="Optional gateway checkout URL"></label>
                    <label>Webhook secret<input name="webhook_secret" type="password" placeholder="<?= !empty($credentials['webhook_secret']) ? 'Saved securely - leave blank to keep' : 'Optional provider webhook secret' ?>"></label>
                    <label>Status<select name="status"><option value="active" <?= (($provider['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option><option value="inactive" <?= (($provider['status'] ?? 'inactive') !== 'active') ? 'selected' : '' ?>>Inactive</option></select></label>
                    <button><?= $provider ? 'Update ' : 'Save ' ?><?= Security::e($preset['name']) ?></button>
                </form>
                <?php if ($provider): ?><form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/delete" class="inline-form gateway-delete"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="redirect_to" value="/admin/settings/payment"><button class="wallet-btn reset" data-confirm="Delete this payment gateway configuration?">Delete configuration</button></form><?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>
    <form method="post" action="/admin/settings" class="settings-form payment-controls">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><input type="hidden" name="redirect_to" value="/admin/settings/payment">
        <h2>Funding controls</h2>
        <div class="settings-switch-grid"><label class="setting-switch"><input type="checkbox" name="settings[funding_manual_enabled]" value="on" <?= (($settings['funding_manual_enabled'] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b>Manual funding</b><small>Allow transfer submissions.</small></span></label><label class="setting-switch"><input type="checkbox" name="settings[funding_gateway_enabled]" value="on" <?= (($settings['funding_gateway_enabled'] ?? 'off') === 'on') ? 'checked' : '' ?>><span><b>Gateway checkout</b><small>Allow online checkout.</small></span></label><label class="setting-switch"><input type="checkbox" name="settings[funding_dedicated_enabled]" value="on" <?= (($settings['funding_dedicated_enabled'] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b>Dedicated accounts</b><small>Allow account number generation.</small></span></label></div>
        <div class="settings-grid"><label>Active gateway<select name="settings[funding_gateway_provider]"><?php foreach (array_keys($presets ?? []) as $gateway): ?><option value="<?= Security::e($gateway) ?>" <?= $selectedGateway === $gateway ? 'selected' : '' ?>><?= Security::e($presets[$gateway]['name']) ?></option><?php endforeach; ?></select></label><label>Webhook secret<input type="password" name="settings[payment_webhook_secret]" placeholder="<?= !empty($settings['payment_webhook_secret']) ? 'Configured securely - leave blank to keep' : 'Generated automatically' ?>"></label></div>
        <button>Save funding controls</button>
    </form>
</section>
