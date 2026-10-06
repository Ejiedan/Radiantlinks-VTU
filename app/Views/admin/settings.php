<?php use App\Core\Security; ?>
<?php $logoUrl = trim((string) ($settings['site_logo_url'] ?? '')); ?>
<?php $gatewayProvider = strtolower((string) ($settings['funding_gateway_provider'] ?? 'paymentpoint')); ?>
<?php $callbackUrl = absolute_url('/payment/callback?provider=' . rawurlencode($gatewayProvider)); ?>
<?php $webhookUrl = absolute_url('/payment/webhook/' . rawurlencode($gatewayProvider)); ?>
<?php $settingsArea = $settingsArea ?? 'general'; ?>
<?php $activeSettingsTab = $settingsArea === 'payment' ? 'funding' : 'general'; ?>
<section class="panel settings-shell">
    <div class="section-head"><div><h1><?= $settingsArea === 'payment' ? 'Payment Settings' : 'General Settings' ?></h1><p class="muted"><?= $settingsArea === 'payment' ? 'Configure wallet funding methods and payment gateway connections.' : 'Branding, security, notifications, email, and receipts.' ?></p></div></div>
    <?php if (!empty($notice)): ?><p class="alert <?= $noticeType === 'error' ? 'failed' : '' ?>"><?= Security::e($notice) ?></p><?php endif; ?>
    <nav class="settings-tabs" aria-label="Settings sections" role="tablist">
        <?php $settingsTabs = $settingsArea === 'payment' ? ['funding' => ['wallet-outline', 'Funding']] : ['general' => ['palette-outline', 'Branding'], 'security' => ['shield-lock-outline', 'Security'], 'notifications' => ['bell-outline', 'Notifications'], 'mail' => ['email-outline', 'Email'], 'receipts' => ['receipt-text-outline', 'Receipts']]; ?>
        <?php foreach ($settingsTabs as $tab => [$icon, $label]): ?>
            <button type="button" role="tab" aria-controls="settings-<?= $tab ?>" aria-selected="<?= $tab === $activeSettingsTab ? 'true' : 'false' ?>" class="<?= $tab === $activeSettingsTab ? 'active' : '' ?>" data-settings-tab="<?= $tab ?>"><i class="mdi mdi-<?= $icon ?>"></i><span><?= $label ?></span></button>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="/admin/settings" enctype="multipart/form-data" class="settings-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <input type="hidden" name="redirect_to" value="/admin/settings/general">

        <?php if ($settingsArea !== 'payment'): ?>
        <section id="settings-general" class="settings-panel active" role="tabpanel" data-settings-panel="general">
            <div class="settings-panel-head"><h2>Branding and homepage</h2><p>Control the identity customers see across the site and receipts.</p></div>
            <div class="settings-grid">
                <label>Site title<input name="settings[site_title]" value="<?= Security::e($settings['site_title'] ?? 'Radiantlinks VTU') ?>" required></label>
                <label>Landing page template<select name="settings[landing_template]"><option value="template1" <?= (($settings['landing_template'] ?? 'template1') === 'template1') ? 'selected' : '' ?>>Template 1 - Builder layout</option><option value="template2" <?= (($settings['landing_template'] ?? '') === 'template2') ? 'selected' : '' ?>>Template 2 - Green VTU landing</option></select></label>
            </div>
            <div class="logo-upload-row">
                <div class="logo-preview" data-logo-preview><?php if ($logoUrl !== ''): ?><img src="<?= Security::e(stored_media_url($logoUrl)) ?>" alt="Current site logo"><?php else: ?><span><?= Security::e(strtoupper(substr((string) ($settings['site_title'] ?? 'R'), 0, 1))) ?></span><?php endif; ?></div>
                <label class="logo-upload-control">Upload site logo<input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp" data-logo-input><small>PNG, JPG, or WEBP. Maximum 2MB.</small></label>
                <?php if ($logoUrl !== ''): ?><label class="setting-switch danger"><input type="checkbox" name="remove_site_logo" value="1"><span>Remove current logo</span></label><?php endif; ?>
            </div>
            <label>Homepage navigation links<textarea name="settings[public_nav_links]" rows="6" placeholder="Home|/&#10;Login|/login&#10;Create Account|/register"><?= Security::e($settings['public_nav_links'] ?? "Home|/\nLogin|/login\nCreate Account|/register") ?></textarea><small>One link per line in Label|URL format.</small></label>
        </section>

        <section id="settings-security" class="settings-panel" role="tabpanel" data-settings-panel="security" hidden>
            <div class="settings-panel-head"><h2>Access and account protection</h2><p>Manage sessions, verification, lockouts, and transaction velocity.</p></div>
            <div class="settings-switch-grid">
                <label class="setting-switch"><input type="checkbox" name="settings[two_factor_admin_enabled]" value="on" <?= (($settings['two_factor_admin_enabled'] ?? 'off') === 'on') ? 'checked' : '' ?>><span><b>Admin email 2FA</b><small>Require a six-digit code for owners and admins.</small></span></label>
                <label class="setting-switch"><input type="checkbox" name="settings[two_factor_customer_enabled]" value="on" <?= (($settings['two_factor_customer_enabled'] ?? 'off') === 'on') ? 'checked' : '' ?>><span><b>Customer email 2FA</b><small>Require a six-digit code for customer login.</small></span></label>
                <label class="setting-switch"><input type="checkbox" name="settings[security_lockout_enabled]" value="on" <?= (($settings['security_lockout_enabled'] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b>Automatic lockout</b><small>Block repeated wrong password or PIN attempts.</small></span></label>
            </div>
            <div class="settings-grid three">
                <label>Session timeout (minutes)<input type="number" min="1" max="1440" name="settings[session_timeout_minutes]" value="<?= Security::e($settings['session_timeout_minutes'] ?? '30') ?>"></label>
                <label>Wrong password limit<input type="number" min="1" max="20" name="settings[wrong_password_limit]" value="<?= Security::e($settings['wrong_password_limit'] ?? '3') ?>"></label>
                <label>Password block minutes<input type="number" min="1" max="1440" name="settings[wrong_password_block_minutes]" value="<?= Security::e($settings['wrong_password_block_minutes'] ?? '15') ?>"></label>
                <label>Wrong PIN limit<input type="number" min="1" max="20" name="settings[wrong_pin_limit]" value="<?= Security::e($settings['wrong_pin_limit'] ?? '3') ?>"></label>
                <label>PIN block minutes<input type="number" min="1" max="1440" name="settings[wrong_pin_block_minutes]" value="<?= Security::e($settings['wrong_pin_block_minutes'] ?? '15') ?>"></label>
                <label>Transactions per minute<input type="number" min="1" max="60" name="settings[transaction_per_minute_limit]" value="<?= Security::e($settings['transaction_per_minute_limit'] ?? '5') ?>"></label>
                <label>Referral bonus occurrences<input type="number" min="1" max="50" name="settings[referral_bonus_times]" value="<?= Security::e($settings['referral_bonus_times'] ?? '1') ?>"></label>
            </div>
        </section>

        <?php endif; ?>
        <?php if ($settingsArea === 'payment'): ?><section id="settings-funding" class="settings-panel active" role="tabpanel" data-settings-panel="funding">
            <div class="settings-panel-head"><h2>Wallet funding</h2><p>Choose available methods, gateway, webhook security, and charges.</p></div>
            <div class="settings-switch-grid">
                <label class="setting-switch"><input type="checkbox" name="settings[funding_manual_enabled]" value="on" <?= (($settings['funding_manual_enabled'] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b>Manual funding</b><small>Customers submit transfers for admin review.</small></span></label>
                <label class="setting-switch"><input type="checkbox" name="settings[funding_gateway_enabled]" value="on" <?= (($settings['funding_gateway_enabled'] ?? 'off') === 'on') ? 'checked' : '' ?>><span><b>Gateway checkout</b><small>Enable online gateway wallet funding.</small></span></label>
                <label class="setting-switch"><input type="checkbox" name="settings[funding_dedicated_enabled]" value="on" <?= (($settings['funding_dedicated_enabled'] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b>Dedicated accounts</b><small>Allow customers to generate account numbers.</small></span></label>
            </div>
            <div class="settings-grid">
                <label>Active payment gateway<select name="settings[funding_gateway_provider]"><?php foreach (['demo' => 'Demo / Local XAMPP', 'paymentpoint' => 'PaymentPoint', 'securewave' => 'Securewave', 'monnify' => 'Monnify', 'paystack' => 'Paystack', 'flutterwave' => 'Flutterwave'] as $value => $label): ?><option value="<?= Security::e($value) ?>" <?= (($settings['funding_gateway_provider'] ?? 'demo') === $value) ? 'selected' : '' ?>><?= Security::e($label) ?></option><?php endforeach; ?></select></label>
                <label>Payment webhook secret<input type="password" name="settings[payment_webhook_secret]" value="" autocomplete="new-password" placeholder="<?= !empty($settings['payment_webhook_secret']) ? 'Configured securely - leave blank to keep' : 'Generated automatically when settings are saved' ?>"><small><?= !empty($settings['payment_webhook_secret']) ? 'A webhook secret is saved.' : 'A secure secret will be generated on Save.' ?></small></label>
            </div>
            <div class="endpoint-grid" data-gateway-endpoints data-callback-base="<?= Security::e(absolute_url('/payment/callback')) ?>" data-webhook-base="<?= Security::e(absolute_url('/payment/webhook')) ?>">
                <label>Gateway callback URL<div class="copy-field"><input value="<?= Security::e($callbackUrl) ?>" readonly data-callback-url><button type="button" class="secondary-button" data-copy-target="callback">Copy</button></div><small>Use as the checkout return/callback URL.</small></label>
                <label>Gateway webhook URL<div class="copy-field"><input value="<?= Security::e($webhookUrl) ?>" readonly data-webhook-url><button type="button" class="secondary-button" data-copy-target="webhook">Copy</button></div><small>Enter this in the provider dashboard for payment notifications.</small></label>
            </div>
            <label>Manual bank details<textarea name="settings[manual_bank_details]" rows="5"><?= Security::e($settings['manual_bank_details'] ?? "Bank: Your Bank\nAccount Name: Radiantlinks VTU\nAccount Number: 0000000000") ?></textarea></label>
            <div class="funding-charge-grid"><?php foreach (['manual' => 'Manual funding', 'gateway' => 'Gateway funding', 'dedicated' => 'Dedicated account'] as $prefix => $label): ?><div class="funding-charge-card"><h3><?= Security::e($label) ?></h3><label>Charge type<select name="settings[funding_<?= $prefix ?>_charge_type]"><option value="fixed" <?= (($settings['funding_' . $prefix . '_charge_type'] ?? 'fixed') === 'fixed') ? 'selected' : '' ?>>Fixed amount</option><option value="percentage" <?= (($settings['funding_' . $prefix . '_charge_type'] ?? '') === 'percentage') ? 'selected' : '' ?>>Percentage</option></select></label><label>Charge value<input type="number" step="0.01" min="0" name="settings[funding_<?= $prefix ?>_charge_value]" value="<?= Security::e($settings['funding_' . $prefix . '_charge_value'] ?? '0') ?>"></label></div><?php endforeach; ?></div>
        </section><?php endif; ?>

        <?php if ($settingsArea !== 'payment'): ?>
        <section id="settings-notifications" class="settings-panel" role="tabpanel" data-settings-panel="notifications" hidden>
            <div class="settings-panel-head"><h2>Notification rules</h2><p>Choose which system events generate emails.</p></div>
            <div class="settings-switch-grid"><?php foreach (['admin_notify_registrations' => ['New registrations', 'Notify admins whenever a customer registers.'], 'admin_notify_transactions' => ['All transactions', 'Notify admins after completed purchases.'], 'admin_notify_blocks' => ['Blocked users', 'Notify admins whenever an account is blocked.'], 'user_notify_registration' => ['Customer registration', 'Send the customer an account creation notice.'], 'user_notify_transactions' => ['Customer transactions', 'Send customers their transaction outcome.']] as $key => [$label, $description]): ?><label class="setting-switch"><input type="checkbox" name="settings[<?= Security::e($key) ?>]" value="on" <?= (($settings[$key] ?? 'on') === 'on') ? 'checked' : '' ?>><span><b><?= Security::e($label) ?></b><small><?= Security::e($description) ?></small></span></label><?php endforeach; ?></div>
        </section>

        <section id="settings-mail" class="settings-panel" role="tabpanel" data-settings-panel="mail" hidden>
            <div class="settings-panel-head"><h2>Email delivery and welcome message</h2><p>SMTP is recommended for login codes, password reset, and notifications.</p></div>
            <div class="settings-grid three">
                <label>Mail driver<select name="settings[mail_driver]"><option value="php" <?= (($settings['mail_driver'] ?? '') === 'php') ? 'selected' : '' ?>>PHP mail</option><option value="smtp" <?= (($settings['mail_driver'] ?? '') === 'smtp') ? 'selected' : '' ?>>SMTP</option></select></label>
                <label>SMTP host<input name="settings[smtp_host]" value="<?= Security::e($settings['smtp_host'] ?? '') ?>"></label><label>SMTP port<input type="number" name="settings[smtp_port]" value="<?= Security::e($settings['smtp_port'] ?? '587') ?>"></label>
                <label>SMTP username<input name="settings[smtp_username]" value="<?= Security::e($settings['smtp_username'] ?? '') ?>" autocomplete="off"></label><label>SMTP password<input type="password" name="settings[smtp_password]" value="" autocomplete="new-password" placeholder="<?= !empty($settings['smtp_password']) ? 'Configured securely - leave blank to keep' : 'Enter SMTP password' ?>"><small><?= !empty($settings['smtp_password']) ? 'SMTP password is saved.' : 'No SMTP password saved.' ?></small></label>
                <label>SMTP encryption<select name="settings[smtp_encryption]"><option value="tls" <?= (($settings['smtp_encryption'] ?? '') === 'tls') ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= (($settings['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option><option value="none" <?= (($settings['smtp_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>None</option></select></label>
            </div>
            <label>Welcome subject<input name="settings[welcome_subject]" value="<?= Security::e($settings['welcome_subject'] ?? 'Welcome to Radiantlinks VTU') ?>"></label>
            <label>Welcome template<textarea name="settings[welcome_template]" rows="7"><?= Security::e($settings['welcome_template'] ?? 'Hello {first_name}, welcome to {site_name}.') ?></textarea><small>Available placeholders: {first_name}, {site_name}.</small></label>
        </section>

        <section id="settings-receipts" class="settings-panel" role="tabpanel" data-settings-panel="receipts" hidden>
            <div class="settings-panel-head"><h2>Receipt defaults</h2><p>Set the standard business text used on customer receipts.</p></div>
            <label>Electricity receipt footer<textarea name="settings[electricity_receipt_footer]" rows="7"><?= Security::e($settings['electricity_receipt_footer'] ?? '') ?></textarea></label>
        </section>

        <?php endif; ?>
        <div class="settings-save-bar"><span>Changes affect the customer site immediately.</span><button type="submit"><i class="mdi mdi-content-save-outline"></i> Save Settings</button></div>
    </form>
</section>
