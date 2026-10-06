<?php use App\Core\Security; use App\Services\ProviderAuthService; ?>
<section class="panel">
    <h1>Provider APIs</h1>
    <p class="muted">Saved configurations stay listed below. Open one to review or update it, or test its endpoint without making a purchase.</p>
    <?php if (!empty($notice)): ?><p class="alert <?= ($noticeType ?? 'success') === 'error' ? 'failed' : '' ?>"><?= Security::e($notice) ?></p><?php endif; ?>
    <details class="admin-dropdown provider-create-panel">
    <summary>Add API configuration</summary>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Choose Stored API Preset
            <select name="preset" id="provider-preset">
                <option value="">Custom API</option>
                <?php foreach ($presets as $key => $preset): ?>
                    <option value="<?= Security::e($key) ?>"
                        data-service="<?= Security::e($preset['service']) ?>"
                        data-name="<?= Security::e($preset['name']) ?>"
                        data-base-url="<?= Security::e($preset['base_url']) ?>"
                        data-auth-type="<?= Security::e($preset['auth_type'] ?? 'none') ?>"
                        data-mappings="<?= Security::e(json_encode($preset['mappings'])) ?>"
                        data-required="<?= Security::e(implode(',', $preset['required'])) ?>"
                        data-notes="<?= Security::e($preset['notes']) ?>">
                        <?= Security::e($preset['name']) ?> · <?= Security::e($preset['service']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted" id="provider-preset-notes"></p>
        <label>Service
            <select name="service">
                <option value="wallet_gateway">Wallet Gateway</option>
                <option value="airtime_pin">Airtime PIN</option>
                <?php foreach ($services as $service): ?><option value="<?= Security::e($service['key']) ?>"><?= Security::e($service['label']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Provider key <input name="provider_key" placeholder="clubkonnect, husmodata, custom_epin" required></label>
        <label>Name <input name="name" required></label>
        <label>Base URL <input name="base_url" placeholder="https://provider.example/api"></label>
        <label>Authentication type
            <select name="auth_type" data-auth-type-select>
                <?php foreach (ProviderAuthService::options() as $value => $label): ?><option value="<?= Security::e($value) ?>"><?= Security::e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>API Key <input name="api_key" data-credential="api_key"></label>
        <label>Secret Key <input name="secret_key" data-credential="secret_key"></label>
        <label>Public Key <input name="public_key" data-credential="public_key"></label>
        <label>Contract Code <input name="contract_code" data-credential="contract_code"></label>
        <label>Name on Card <input name="name_on_card" data-credential="name_on_card"></label>
        <label>Manual Bank Accounts <textarea name="bank_accounts" rows="3" data-credential="bank_accounts" placeholder="Bank: Account Name: Account Number"></textarea></label>
        <label>Bearer/Token <input name="token" data-credential="token"></label>
        <label>Client ID <input name="client_id" data-credential="client_id"></label>
        <label>Client Secret <input name="client_secret" data-credential="client_secret"></label>
        <label>Account ID <input name="account_id" data-credential="account_id"></label>
        <label>Bank Type <input name="bank_type" data-credential="bank_type" placeholder="For example 20946"></label>
        <label>Business ID <input name="business_id" data-credential="business_id"></label>
        <label>Bank Codes <input name="bank_code" data-credential="bank_code" placeholder="Comma-separated provider bank codes"></label>
        <label>Checkout Endpoint <input type="url" name="checkout_endpoint" data-credential="checkout_endpoint" placeholder="Optional hosted checkout URL"></label>
        <label>Callback URL <input type="url" name="callback_url" data-credential="callback_url" placeholder="<?= Security::e(absolute_url('/payment/callback')) ?>"><small>Optional override. The platform callback is used automatically when blank.</small></label>
        <label>Webhook Signing Secret <input type="password" name="webhook_secret" data-credential="webhook_secret"><small>Optional provider-specific webhook signing secret.</small></label>
        <label>Environment
            <select name="environment">
                <option value="live">Live</option>
                <option value="sandbox">Sandbox/Test</option>
            </select>
        </label>
        <label>Username <input name="username"></label>
        <label>Password <input type="password" name="password"></label>
        <label>Mappings JSON <textarea name="mappings" rows="5" placeholder='{"mtn":"1","waec":"WAEC"}'>{}</textarea></label>
        <label>Status
            <select name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <button>Save Provider</button>
    </form>
    </details>
    <div class="section-head provider-list-head"><div><h2>Saved API Configurations</h2><p class="muted"><?= number_format(count($providers)) ?> configuration<?= count($providers) === 1 ? '' : 's' ?> saved</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Service</th><th>Provider</th><th>Name</th><th>Authentication</th><th>Base URL</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $provider): ?>
                <?php $credentials = json_decode((string) ($provider['credentials'] ?? '{}'), true) ?: []; ?>
                <tr>
                    <td><?= Security::e($provider['service']) ?></td>
                    <td><?= Security::e($provider['provider_key']) ?></td>
                    <td><?= Security::e($provider['name']) ?></td>
                    <td><?= Security::e(ProviderAuthService::options()[$credentials['auth_type'] ?? (ProviderAuthService::type($credentials, (string) ((App\Services\ProviderPresetService::get((string) $provider['provider_key'])['auth_type'] ?? 'none'))))] ?? 'Custom') ?></td>
                    <td><?= Security::e($provider['base_url']) ?></td>
                    <td><span class="status-pill <?= Security::e((string) $provider['status']) ?>"><?= Security::e($provider['status']) ?></span></td>
                    <td>
                        <button type="button" class="secondary-button" data-package-price-toggle="provider-edit-<?= (int) $provider['id'] ?>">Edit</button>
                        <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/test" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <button class="wallet-btn topup">Test</button>
                        </form>
                        <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/delete" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <button class="wallet-btn reset" data-confirm="Delete this API provider?">Delete</button>
                        </form>
                    </td>
                </tr>
                <tr id="provider-edit-<?= (int) $provider['id'] ?>" class="package-price-row" hidden>
                    <td colspan="7">
                        <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/update" class="stacked-form compact-provider-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <p class="config-loaded"><strong>Saved configuration loaded.</strong> Secret values remain encrypted from the page and are preserved when left blank.</p>
                            <label>Service
                                <select name="service">
                                    <option value="wallet_gateway" <?= $provider['service'] === 'wallet_gateway' ? 'selected' : '' ?>>Wallet Gateway</option>
                                    <option value="airtime_pin" <?= $provider['service'] === 'airtime_pin' ? 'selected' : '' ?>>Airtime PIN</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= Security::e($service['key']) ?>" <?= $provider['service'] === $service['key'] ? 'selected' : '' ?>><?= Security::e($service['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="form-two">
                                <label>Provider key <input name="provider_key" value="<?= Security::e($provider['provider_key']) ?>" required></label>
                                <label>Name <input name="name" value="<?= Security::e($provider['name']) ?>" required></label>
                            </div>
                            <label>Base URL <input name="base_url" value="<?= Security::e($provider['base_url']) ?>"></label>
                            <label>Authentication type
                                <?php $selectedAuth = ProviderAuthService::type($credentials, (string) (App\Services\ProviderPresetService::get((string) $provider['provider_key'])['auth_type'] ?? 'none')); ?>
                                <select name="auth_type" data-auth-type-select><?php foreach (ProviderAuthService::options() as $value => $label): ?><option value="<?= Security::e($value) ?>" <?= $selectedAuth === $value ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select>
                            </label>
                            <div class="form-two">
                                <label>API Key <input type="password" name="api_key" value="" placeholder="<?= !empty($credentials['api_key']) ? 'Configured securely - leave blank to keep' : 'Not configured' ?>"><small><?= !empty($credentials['api_key']) ? 'API key is saved.' : 'No API key saved.' ?></small></label>
                                <label>Secret Key <input type="password" name="secret_key" value="" placeholder="<?= !empty($credentials['secret_key']) ? 'Configured securely - leave blank to keep' : 'Not configured' ?>"><small><?= !empty($credentials['secret_key']) ? 'Secret key is saved.' : 'No secret key saved.' ?></small></label>
                            </div>
                            <div class="form-two">
                                <label>Public Key <input type="password" name="public_key" value="" placeholder="<?= !empty($credentials['public_key']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                                <label>Contract Code <input name="contract_code" value="<?= Security::e($credentials['contract_code'] ?? '') ?>"></label>
                            </div>
                            <div class="form-two">
                                <label>Name on Card <input name="name_on_card" value="<?= Security::e($credentials['name_on_card'] ?? '') ?>"></label>
                                <label>Bearer/Token <input type="password" name="token" value="" placeholder="<?= !empty($credentials['token']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                            </div>
                            <div class="form-two">
                                <label>Client ID <input name="client_id" value="<?= Security::e($credentials['client_id'] ?? '') ?>"></label>
                                <label>Client Secret <input type="password" name="client_secret" value="" placeholder="<?= !empty($credentials['client_secret']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                            </div>
                            <div class="form-two">
                                <label>Bank Type <input name="bank_type" value="<?= Security::e($credentials['bank_type'] ?? '') ?>"></label>
                                <label>Business ID <input name="business_id" value="<?= Security::e($credentials['business_id'] ?? '') ?>"></label>
                            </div>
                            <label>Bank Codes <input name="bank_code" value="<?= Security::e($credentials['bank_code'] ?? '') ?>" placeholder="Comma-separated provider bank codes"></label>
                            <div class="form-two">
                                <label>Checkout Endpoint <input type="url" name="checkout_endpoint" value="<?= Security::e($credentials['checkout_endpoint'] ?? '') ?>"></label>
                                <label>Callback URL <input type="url" name="callback_url" value="<?= Security::e($credentials['callback_url'] ?? '') ?>"></label>
                            </div>
                            <?php if ($provider['service'] === 'wallet_gateway'): ?>
                            <div class="endpoint-grid">
                                <label>Platform callback URL<div class="copy-field"><input value="<?= Security::e(absolute_url('/payment/callback?provider=' . rawurlencode((string) $provider['provider_key']))) ?>" readonly><button type="button" class="secondary-button" data-copy-adjacent>Copy</button></div></label>
                                <label>Provider webhook URL<div class="copy-field"><input value="<?= Security::e(absolute_url('/payment/webhook/' . rawurlencode((string) $provider['provider_key']))) ?>" readonly><button type="button" class="secondary-button" data-copy-adjacent>Copy</button></div></label>
                            </div>
                            <label>Webhook Signing Secret <input type="password" name="webhook_secret" placeholder="<?= !empty($credentials['webhook_secret']) ? 'Configured securely - leave blank to keep' : 'Uses global or provider secret when blank' ?>"><small><?= !empty($credentials['webhook_secret']) ? 'Provider webhook secret is saved.' : 'No separate provider webhook secret saved.' ?></small></label>
                            <?php endif; ?>
                            <div class="form-two">
                                <label>Account ID <input name="account_id" value="<?= Security::e($credentials['account_id'] ?? '') ?>"></label>
                                <label>Environment
                                    <select name="environment">
                                        <option value="live" <?= ($credentials['environment'] ?? 'live') === 'live' ? 'selected' : '' ?>>Live</option>
                                        <option value="sandbox" <?= ($credentials['environment'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox/Test</option>
                                    </select>
                                </label>
                            </div>
                            <div class="form-two">
                                <label>Username <input name="username" value="<?= Security::e($credentials['username'] ?? '') ?>"></label>
                                <label>Password <input type="password" name="password" value="" placeholder="<?= !empty($credentials['password']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                            </div>
                            <label>Manual Bank Accounts <textarea name="bank_accounts" rows="3"><?= Security::e($credentials['bank_accounts'] ?? '') ?></textarea></label>
                            <label>Mappings JSON <textarea name="mappings" rows="5"><?= Security::e($provider['mappings'] ?? '{}') ?></textarea></label>
                            <label>Status
                                <select name="status">
                                    <option value="active" <?= $provider['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $provider['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </label>
                            <button>Update Provider</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($providers)): ?>
                <tr><td colspan="7">No API provider has been saved yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
