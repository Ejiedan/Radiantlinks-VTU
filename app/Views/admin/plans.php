<?php use App\Core\Security; use App\Services\ProviderAuthService; ?>
<section class="panel">
    <h1><?= ($filterService ?? '') !== '' ? Security::e(ucwords(str_replace('_', ' ', (string) $filterService)) . ' Settings') : 'All Service Plans' ?></h1>
    <div class="admin-tabs">
        <a class="<?= ($filterService ?? '') === 'data' ? 'active' : '' ?>" href="/admin/plans?service=data">Data</a>
        <a class="<?= ($filterService ?? '') === 'airtime' ? 'active' : '' ?>" href="/admin/plans?service=airtime">Airtime</a>
        <a class="<?= ($filterService ?? '') === 'cable' ? 'active' : '' ?>" href="/admin/plans?service=cable">Cable</a>
        <a class="<?= ($filterService ?? '') === 'electricity' ? 'active' : '' ?>" href="/admin/plans?service=electricity">Electricity</a>
        <a class="<?= ($filterService ?? '') === 'exam_epin' ? 'active' : '' ?>" href="/admin/plans?service=exam_epin">Exam ePIN</a>
        <a class="<?= ($filterService ?? '') === 'airtime_pin' ? 'active' : '' ?>" href="/admin/plans?service=airtime_pin">Airtime PIN</a>
        <a class="<?= ($filterService ?? '') === 'data_pin' ? 'active' : '' ?>" href="/admin/plans?service=data_pin">Data PIN</a>
        <?php if (($filterService ?? '') === ''): ?><a class="active" href="/admin/plans">All Services</a><?php endif; ?>
    </div>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <details class="admin-dropdown" open>
        <summary>Add / Edit Manual Plan</summary>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <?php if (($filterService ?? '') === ''): ?><label>Service
            <select name="service">
                <?php foreach ($services as $service): ?><option value="<?= Security::e($service['key']) ?>" <?= ($filterService ?? '') === $service['key'] ? 'selected' : '' ?>><?= Security::e($service['label']) ?></option><?php endforeach; ?>
            </select>
        </label><?php else: ?><input type="hidden" name="service" value="<?= Security::e((string) $filterService) ?>"><?php endif; ?>
        <div class="form-two">
            <label>Plan ID <input name="plan_id" placeholder="Copied API plan id/code" required></label>
            <label>Name <input name="name" placeholder="MTN 1GB 30 Days" required></label>
        </div>
        <div class="form-two">
            <label>Duration / Validity <input name="duration" placeholder="30 days, 7 days, 1 month"></label>
            <label>PIN Quantity / Units <input type="number" min="1" name="quantity" value="1" placeholder="1"></label>
        </div>
        <div class="form-two">
            <label>Price <input type="number" step="0.01" name="selling_price" value="0" required></label>
            <label>Network / Provider <input name="provider" placeholder="mtn, airtel, glo, 9mobile" required></label>
        </div>
        <div class="form-two">
            <label>Service Type
                <select name="service_type">
                    <option value="">None</option>
                    <option value="sme">SME</option>
                    <option value="corporate">Corporate</option>
                    <option value="gifting">Gifting</option>
                    <option value="vtu">VTU</option>
                    <option value="share_and_sell">Share and Sell</option>
                    <option value="prepaid">Prepaid</option>
                    <option value="postpaid">Postpaid</option>
                    <option value="local">Local PIN</option>
                </select>
            </label>
            <label>Cost Price <input type="number" step="0.01" name="cost_price" value="0" required></label>
        </div>
        <label>Sort Order <input type="number" name="list_order" value="0"></label>
        <label>Status
            <select name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <label>Metadata JSON <textarea name="metadata" rows="4" placeholder='{"validity":"30 days","api_provider":"clubkonnect"}'>{}</textarea></label>
        <button>Save Plan</button>
    </form>
    </details>
</section>
<?php if (($filterService ?? '') !== ''): ?>
<?php
$servicePresets = array_filter($presets ?? [], static fn (array $preset): bool => ($preset['service'] ?? '') === $filterService);
$serviceLabel = strtoupper(str_replace('_', ' ', (string) $filterService));
?>
<section class="panel service-api-panel">
    <div class="section-head">
        <div>
            <h2><?= Security::e($serviceLabel) ?> API Settings</h2>
            <p class="muted">Choose the API provider for this service here. Customers will only use the active provider saved for <?= Security::e($filterService) ?>.</p>
        </div>
        <a class="button-link secondary" href="/admin/providers">All APIs</a>
    </div>
    <?php if ($filterService === 'data'): ?>
        <div class="api-help-strip">
            <strong>Data API presets available:</strong>
            <span>VTpass</span>
            <span>VTU.ng</span>
            <span>VTU.com.ng</span>
            <span>VTU Africa</span>
            <span>Reloadly</span>
            <span>SmartTeck-style</span>
        </div>
    <?php endif; ?>
    <details class="admin-dropdown">
        <summary>API Setup</summary>
    <form method="post" action="/admin/providers" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <input type="hidden" name="service" value="<?= Security::e((string) $filterService) ?>">
        <input type="hidden" name="redirect_to" value="/admin/service-settings/<?= urlencode((string) $filterService) ?>">
        <label>Choose <?= Security::e($serviceLabel) ?> API Preset
            <select name="preset" id="service-provider-preset">
                <option value="">Custom <?= Security::e($serviceLabel) ?> API</option>
                <?php foreach ($servicePresets as $key => $preset): ?>
                    <option value="<?= Security::e((string) $key) ?>"
                        data-service="<?= Security::e($preset['service']) ?>"
                        data-name="<?= Security::e($preset['name']) ?>"
                        data-base-url="<?= Security::e($preset['base_url']) ?>"
                        data-auth-type="<?= Security::e($preset['auth_type'] ?? 'none') ?>"
                        data-mappings="<?= Security::e(json_encode($preset['mappings'])) ?>"
                        data-required="<?= Security::e(implode(',', $preset['required'])) ?>"
                        data-notes="<?= Security::e($preset['notes']) ?>">
                        <?= Security::e($preset['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="muted" id="service-provider-preset-notes"><?= empty($servicePresets) ? 'No stored preset for this service yet. Use Custom API and enter the provider details.' : 'Pick a provider preset or use Custom API.' ?></p>
        <label>Provider key <input name="provider_key" placeholder="vtpass_data, clubkonnect, husmodata" required></label>
        <label>Name <input name="name" required></label>
        <label>Base URL <input name="base_url" placeholder="https://provider.example/api"></label>
        <label>Authentication type<select name="auth_type" data-auth-type-select><?php foreach (ProviderAuthService::options() as $value => $label): ?><option value="<?= Security::e($value) ?>"><?= Security::e($label) ?></option><?php endforeach; ?></select></label>
        <div class="form-two">
            <label>API Key <input name="api_key" data-credential="api_key"></label>
            <label>Secret Key <input name="secret_key" data-credential="secret_key"></label>
        </div>
        <div class="form-two">
            <label>Bearer/Token <input name="token" data-credential="token"></label>
            <label>Public Key <input name="public_key" data-credential="public_key"></label>
        </div>
        <div class="form-two">
            <label>Client ID <input name="client_id" data-credential="client_id"></label>
            <label>Client Secret <input name="client_secret" data-credential="client_secret"></label>
        </div>
        <label>Mappings JSON <textarea name="mappings" rows="4" placeholder='{"mtn":"1","airtel":"2"}'>{}</textarea></label>
        <label>Status
            <select name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <button>Save <?= Security::e($serviceLabel) ?> API</button>
    </form>
    </details>
    <details class="admin-dropdown" <?= !empty($providers) ? 'open' : '' ?>>
        <summary>Configured APIs</summary>
    <h3>Configured <?= Security::e($serviceLabel) ?> APIs</h3>
    <table>
        <thead><tr><th>Provider</th><th>Name</th><th>Base URL</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach (($providers ?? []) as $provider): ?>
            <?php $providerCredentials = json_decode((string) ($provider['credentials'] ?? '{}'), true) ?: []; ?>
            <tr>
                <td><?= Security::e($provider['provider_key']) ?></td>
                <td><?= Security::e($provider['name']) ?></td>
                <td><?= Security::e($provider['base_url']) ?></td>
                <td><span class="status-pill <?= Security::e((string) $provider['status']) ?>"><?= Security::e($provider['status']) ?></span></td>
                <td>
                    <button type="button" class="secondary-button" data-package-price-toggle="provider-edit-<?= (int) $provider['id'] ?>">Edit</button>
                    <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/test" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="redirect_to" value="/admin/service-settings/<?= urlencode((string) $filterService) ?>">
                        <button class="wallet-btn topup">Test</button>
                    </form>
                    <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/delete" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="redirect_to" value="/admin/service-settings/<?= urlencode((string) $filterService) ?>">
                        <button class="wallet-btn reset" data-confirm="Delete this API provider?">Delete</button>
                    </form>
                </td>
            </tr>
            <tr id="provider-edit-<?= (int) $provider['id'] ?>" class="package-price-row" hidden>
                <td colspan="5">
                    <form method="post" action="/admin/providers/<?= (int) $provider['id'] ?>/update" class="stacked-form compact-provider-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="redirect_to" value="/admin/service-settings/<?= urlencode((string) $filterService) ?>">
                        <input type="hidden" name="service" value="<?= Security::e((string) $provider['service']) ?>">
                        <div class="form-two">
                            <label>Provider key <input name="provider_key" value="<?= Security::e($provider['provider_key']) ?>" required></label>
                            <label>Name <input name="name" value="<?= Security::e($provider['name']) ?>" required></label>
                        </div>
                        <label>Base URL <input name="base_url" value="<?= Security::e($provider['base_url']) ?>"></label>
                        <?php $selectedAuth = ProviderAuthService::type($providerCredentials, (string) (App\Services\ProviderPresetService::get((string) $provider['provider_key'])['auth_type'] ?? 'none')); ?>
                        <label>Authentication type<select name="auth_type" data-auth-type-select><?php foreach (ProviderAuthService::options() as $value => $label): ?><option value="<?= Security::e($value) ?>" <?= $selectedAuth === $value ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select></label>
                        <div class="form-two">
                            <label>API Key <input type="password" name="api_key" placeholder="<?= !empty($providerCredentials['api_key']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                            <label>Secret Key <input type="password" name="secret_key" placeholder="<?= !empty($providerCredentials['secret_key']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                        </div>
                        <div class="form-two">
                            <label>Bearer/Token <input type="password" name="token" placeholder="<?= !empty($providerCredentials['token']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                            <label>Public Key <input type="password" name="public_key" placeholder="<?= !empty($providerCredentials['public_key']) ? 'Saved - leave blank to keep' : 'Not configured' ?>"></label>
                        </div>
                        <div class="form-two">
                            <label>Basic Auth Username <input name="username" value="<?= Security::e($providerCredentials['username'] ?? '') ?>" placeholder="Optional; API key is the fallback"></label>
                            <label>Basic Auth Password <input type="password" name="password" placeholder="<?= !empty($providerCredentials['password']) ? 'Saved - leave blank to keep' : 'Optional; secret key is the fallback' ?>"></label>
                        </div>
                        <label>Mappings JSON <textarea name="mappings" rows="4"><?= Security::e($provider['mappings'] ?? '{}') ?></textarea></label>
                        <label>Status
                            <select name="status">
                                <option value="active" <?= $provider['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $provider['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </label>
                        <button>Update API Provider</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($providers)): ?>
            <tr><td colspan="5">No <?= Security::e($filterService) ?> API has been configured yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </details>
    <?php if ($filterService === 'data'): ?>
        <details class="admin-dropdown">
            <summary>Import Data Plans</summary>
        <form method="post" action="/admin/plans/import-provider" class="inline-form provider-import-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <select name="provider_config_id" required>
                <option value="">Choose TelecomAbode Data API</option>
                <?php foreach (($providers ?? []) as $provider): ?>
                    <?php if (($provider['provider_key'] ?? '') === 'telecomabode_normal_data'): ?>
                        <option value="<?= (int) $provider['id'] ?>"><?= Security::e($provider['name']) ?> · <?= Security::e($provider['status']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button>Import TelecomAbode Plans</button>
        </form>
        </details>
        <details class="admin-dropdown">
            <summary>Automatic Type Mapping</summary>
        <div class="type-mapping-panel">
            <div class="section-head">
                <div>
                    <h3>Automatic Data Type Mapping</h3>
                    <p class="muted">Imported plans are mapped by keyword before saving. Unknown new API types are still saved automatically as clean type names.</p>
                </div>
            </div>
            <form method="post" action="/admin/plans/type-mappings" class="inline-form type-mapping-form">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <input type="hidden" name="service" value="data">
                <input name="raw_keyword" placeholder="Provider keyword e.g CG, SME II" required>
                <input name="mapped_type" placeholder="Map to e.g corporate, sme, gifting" required>
                <input type="number" name="priority" value="50" placeholder="Priority">
                <label class="inline-check"><input type="checkbox" name="apply_existing" value="1"> Apply to existing plans</label>
                <button>Save Mapping</button>
            </form>
            <div class="mapping-grid">
                <?php foreach (($typeMappings ?? []) as $mapping): ?>
                    <form method="post" action="/admin/plans/type-mappings/<?= (int) $mapping['id'] ?>/delete" class="mapping-chip">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="service" value="data">
                        <span><?= Security::e($mapping['raw_keyword']) ?></span>
                        <strong><?= Security::e($mapping['mapped_type']) ?></strong>
                        <small><?= (int) $mapping['priority'] ?></small>
                        <label><input type="checkbox" name="apply_existing" value="1"> remap</label>
                        <button class="mini-danger">Delete</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        </details>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="panel">
    <h2>Configured Plans</h2>
    <?php $hideServiceColumn = ($filterService ?? '') !== ''; ?>
    <table class="compact-plans-table">
        <thead><tr><?php if (!$hideServiceColumn): ?><th>Service</th><?php endif; ?><th>Provider</th><th>Type</th><th>Plan</th><th>Duration</th><th>Cost</th><th>Default Price</th><th>Package Prices</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <?php $planFormId = 'plan-update-' . (int) $plan['id']; ?>
            <tr>
                <?php if (!$hideServiceColumn): ?>
                <td>
                    <form id="<?= Security::e($planFormId) ?>" method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/update" class="table-form"></form>
                    <input form="<?= Security::e($planFormId) ?>" type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                    <input form="<?= Security::e($planFormId) ?>" type="hidden" name="service" value="<?= Security::e($plan['service']) ?>">
                    <?= Security::e($plan['service']) ?>
                </td>
                <?php else: ?>
                    <td hidden>
                        <form id="<?= Security::e($planFormId) ?>" method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/update" class="table-form"></form>
                        <input form="<?= Security::e($planFormId) ?>" type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input form="<?= Security::e($planFormId) ?>" type="hidden" name="service" value="<?= Security::e($plan['service']) ?>">
                    </td>
                <?php endif; ?>
                <td><input form="<?= Security::e($planFormId) ?>" name="provider" value="<?= Security::e($plan['provider']) ?>"></td>
                <td><input form="<?= Security::e($planFormId) ?>" name="service_type" value="<?= Security::e($plan['service_type'] ?? '') ?>"></td>
                <td>
                    <input form="<?= Security::e($planFormId) ?>" name="name" value="<?= Security::e($plan['name']) ?>">
                    <input form="<?= Security::e($planFormId) ?>" name="plan_id" value="<?= Security::e($plan['plan_id']) ?>" class="muted-input">
                </td>
                <td><input form="<?= Security::e($planFormId) ?>" name="duration" value="<?= Security::e($plan['duration'] ?? '') ?>"></td>
                <td hidden><input form="<?= Security::e($planFormId) ?>" type="hidden" name="quantity" value="<?= (int) ($plan['quantity'] ?? 1) ?>"></td>
                <td><input form="<?= Security::e($planFormId) ?>" type="number" step="0.01" name="cost_price" value="<?= Security::e(rtrim(rtrim(number_format((float) $plan['cost_price'], 2, '.', ''), '0'), '.')) ?>"></td>
                <td><input form="<?= Security::e($planFormId) ?>" type="number" step="0.01" name="selling_price" value="<?= Security::e(rtrim(rtrim(number_format((float) $plan['selling_price'], 2, '.', ''), '0'), '.')) ?>"></td>
                <td>
                    <button type="button" class="secondary-button" data-package-price-toggle="prices-<?= (int) $plan['id'] ?>">Edit</button>
                </td>
                <td>
                    <select form="<?= Security::e($planFormId) ?>" name="status">
                        <option value="active" <?= $plan['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $plan['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <input form="<?= Security::e($planFormId) ?>" type="hidden" name="metadata" value="<?= Security::e($plan['metadata'] ?? '{}') ?>">
                    <input form="<?= Security::e($planFormId) ?>" type="hidden" name="list_order" value="<?= (int) ($plan['list_order'] ?? 0) ?>">
                </td>
                <td><button form="<?= Security::e($planFormId) ?>">Save</button></td>
            </tr>
            <tr id="prices-<?= (int) $plan['id'] ?>" class="package-price-row" hidden>
                <td colspan="<?= $hideServiceColumn ? 9 : 10 ?>">
                    <form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/package-prices" class="package-price-form">
                        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                        <input type="hidden" name="service" value="<?= Security::e($plan['service']) ?>">
                        <?php foreach (($packages ?? []) as $package): ?>
                            <label><?= Security::e($package['name']) ?>
                                <input type="number" step="0.01" min="0" name="package_prices[<?= (int) $package['id'] ?>]" value="<?= Security::e((string) ($packagePrices[(int) $plan['id']][(int) $package['id']] ?? '')) ?>" placeholder="Use default">
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($packages)): ?><p class="muted">Create packages first under Packages & Rewards.</p><?php endif; ?>
                        <button>Save Package Prices</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
