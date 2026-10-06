<?php use App\Core\Security; ?>
<section class="auth-page">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="muted">Login with your username or email to continue.</p>
        <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
        <form method="post" class="js-validate-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <label>Username or email <input name="email" autocomplete="username" required></label>
            <label>Password <input type="password" name="password" autocomplete="current-password" minlength="8" required></label>
            <button>Login</button>
        </form>
        <p class="auth-switch"><a href="/forgot-password">Forgot password?</a></p>
        <p class="auth-switch">Don’t have an account? <a href="/register">Register</a></p>
    </div>
</section>
