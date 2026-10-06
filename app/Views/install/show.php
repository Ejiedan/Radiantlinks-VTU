<?php use App\Core\Security; ?>
<section class="auth-card">
    <h1>Install Better VTU</h1>
    <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Owner activation key <input name="owner_license_key" required></label>
        <label>First name <input name="first_name" required></label>
        <label>Last name <input name="last_name"></label>
        <label>Email <input type="email" name="email" required></label>
        <label>Phone <input name="phone" required></label>
        <label>Password <input type="password" name="password" required></label>
        <label>Transaction PIN <input name="pin" required maxlength="6"></label>
        <button>Create Owner Account</button>
    </form>
</section>

