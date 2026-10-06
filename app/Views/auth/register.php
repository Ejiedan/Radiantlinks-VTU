<?php use App\Core\Security; ?>
<section class="auth-page">
    <div class="auth-card">
        <h1>Create account</h1>
        <p class="muted">Open your wallet account and start buying securely.</p>
        <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
        <form method="post" class="js-validate-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <div class="form-two">
                <label>First name <input name="first_name" value="<?= Security::e($old['first_name'] ?? '') ?>" required></label>
                <label>Last name <input name="last_name" value="<?= Security::e($old['last_name'] ?? '') ?>"></label>
            </div>
            <label>Username <input name="username" value="<?= Security::e($old['username'] ?? '') ?>" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" required></label>
            <label>Email <input type="email" name="email" value="<?= Security::e($old['email'] ?? '') ?>" required></label>
            <label>Phone <input name="phone" value="<?= Security::e($old['phone'] ?? '') ?>" inputmode="tel" data-phone-field required></label>
            <div class="form-two">
                <label>Password <input type="password" name="password" autocomplete="new-password" minlength="8" data-strong-password required><small class="field-hint">Use at least 8 characters with letters, numbers, and symbols.</small></label>
                <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
            </div>
            <label>Transaction PIN <input name="pin" value="<?= Security::e($old['pin'] ?? '') ?>" inputmode="numeric" minlength="4" maxlength="6" required></label>
            <label>Referral code <input name="referral_code" value="<?= Security::e($old['referral_code'] ?? ($_GET['ref'] ?? '')) ?>" placeholder="Optional"></label>
            <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off">
            <input type="hidden" name="form_started_at" value="<?= time() ?>">
            <label>Security check: what is <?= (int) (($_SESSION['register_bot_answer'] ?? 4) - 2) ?> + 2?
                <input name="bot_answer" inputmode="numeric" required>
            </label>
            <button>Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="/login">Login</a></p>
    </div>
</section>
