<?php use App\Core\Security; ?>
<section class="auth-page">
    <div class="auth-card">
        <h1>Reset password</h1>
        <p class="muted">Enter your username or email. We will send a 6-digit verification code.</p>
        <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
        <form method="post" class="js-validate-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <label>Username or email <input name="login" required></label>
            <button>Send Reset Code</button>
        </form>
        <p class="auth-switch"><a href="/login">Back to login</a></p>
    </div>
</section>
