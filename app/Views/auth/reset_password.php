<?php use App\Core\Security; ?>
<section class="auth-page">
    <div class="auth-card">
        <h1>Choose new password</h1>
        <p class="muted">Enter the email code and your transaction PIN before setting a new password.</p>
        <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
        <form method="post" class="js-validate-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <label>6-digit code <input name="code" inputmode="numeric" minlength="6" maxlength="6" pattern="[0-9]{6}" required></label>
            <label>Transaction PIN <input type="password" name="pin" minlength="4" maxlength="6" required></label>
            <label>New password <input type="password" name="password" minlength="8" data-strong-password required><small class="field-hint">Use at least 8 characters with letters, numbers, and symbols.</small></label>
            <label>Confirm password <input type="password" name="password_confirmation" minlength="8" required></label>
            <button>Reset Password</button>
        </form>
    </div>
</section>
