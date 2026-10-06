<?php use App\Core\Security; ?>
<div class="admin-user-create">
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <details class="admin-dropdown admin-create-user">
    <summary>Create User</summary>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <div class="form-two">
            <label>First name <input name="first_name" required></label>
            <label>Last name <input name="last_name"></label>
        </div>
        <div class="form-two">
            <label>Username <input name="username" required></label>
            <label>Email <input type="email" name="email" required></label>
        </div>
        <div class="form-two">
            <label>Phone <input name="phone" required></label>
            <label>Password <input type="password" name="password" required minlength="8"></label>
        </div>
        <div class="form-two">
            <label>Transaction PIN <input name="pin" value="1234" required></label>
            <label>Role
                <select name="role">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                    <option value="owner">Owner</option>
                </select>
            </label>
        </div>
        <label>Package
            <select name="package_id">
                <option value="">None</option>
                <?php foreach ($packages as $package): ?><option value="<?= (int) $package['id'] ?>"><?= Security::e($package['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></select>
        </label>
        <button>Create User</button>
    </form>
    </details>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Contact</th><th>Role & Status</th><th>Security</th><th>Balance</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td>#<?= (int) $row['id'] ?> <?= Security::e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= Security::e($row['email']) ?><br><?= Security::e($row['phone']) ?></td>
                    <td>
                        <form method="post" action="/admin/users/<?= (int) $row['id'] ?>/update" class="stacked-form compact-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <input name="first_name" value="<?= Security::e($row['first_name']) ?>">
                            <input name="last_name" value="<?= Security::e($row['last_name']) ?>">
                            <input name="phone" value="<?= Security::e($row['phone']) ?>">
                            <select name="role">
                                <?php foreach (['customer', 'staff', 'admin', 'owner'] as $role): ?><option value="<?= $role ?>" <?= $row['role'] === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option><?php endforeach; ?>
                            </select>
                            <select name="status">
                                <?php foreach (['active', 'inactive', 'blocked'] as $status): ?><option value="<?= $status ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?>
                            </select>
                            <select name="package_id">
                                <option value="">No package</option>
                                <?php foreach ($packages as $package): ?><option value="<?= (int) $package['id'] ?>" <?= (int) ($row['package_id'] ?? 0) === (int) $package['id'] ? 'selected' : '' ?>><?= Security::e($package['name']) ?></option><?php endforeach; ?>
                            </select>
                            <input type="datetime-local" name="blocked_until" value="<?= !empty($row['blocked_until']) ? Security::e(str_replace(' ', 'T', substr($row['blocked_until'], 0, 16))) : '' ?>">
                            <input name="blocked_reason" value="<?= Security::e($row['blocked_reason'] ?? '') ?>" placeholder="Block/suspend reason">
                            <button>Save</button>
                        </form>
                    </td>
                    <td>
                        <?php if (($row['status'] ?? '') === 'blocked'): ?>
                            <b>Blocked</b><br>
                            <span class="muted"><?= Security::e($row['blocked_reason'] ?? '') ?></span><br>
                            <span class="muted">Until <?= Security::e($row['blocked_until'] ?? '') ?></span>
                            <form method="post" action="/admin/users/<?= (int) $row['id'] ?>/unblock" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                <button>Unblock</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Clear</span>
                        <?php endif; ?>
                    </td>
                    <td>₦<?= number_format((float) $row['balance'], 2) ?></td>
                    <td>
                        <a class="button-link secondary" href="/admin/users/<?= (int) $row['id'] ?>/dashboard">Dashboard</a>
                        <div class="wallet-admin-actions">
                            <form method="post" action="/admin/wallet/credit" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Top up amount" required>
                                <button class="wallet-btn topup">Top Up</button>
                            </form>
                            <form method="post" action="/admin/wallet/debit" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Deduct amount" required>
                                <button class="wallet-btn deduct">Deduct</button>
                            </form>
                            <form method="post" action="/admin/wallet/reset" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <input type="number" step="0.01" min="0" name="amount" placeholder="Set balance" required>
                                <button class="wallet-btn reset" data-confirm="Reset this user wallet balance to the entered amount?">Reset</button>
                            </form>
                        </div>
                        <form method="post" action="/admin/users/<?= (int) $row['id'] ?>/delete" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <button data-confirm="Delete this user? Users with transaction records should be blocked instead.">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
