<?php use App\Core\Security; ?>
<section class="auth-page">
    <div class="auth-card">
        <h1>Verify login</h1>
        <p class="muted">Enter the 6-digit code sent to your email. The code expires in 10 minutes.</p>
        <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
        <form method="post" class="js-validate-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <label>Verification code <input name="code" inputmode="numeric" minlength="6" maxlength="6" pattern="[0-9]{6}" required></label>
            <button>Verify</button>
        </form>
        <form method="post" action="/verify-2fa/resend" class="auth-switch">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <button class="secondary-button">Resend code</button>
        </form>
    </div>
</section>
