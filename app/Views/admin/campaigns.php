<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Campaigns</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Name <input name="name" required></label>
        <label>Subject <input name="subject" required></label>
        <label>Target rule
            <select name="target_rule">
                <option value="funded_wallet">Funded wallet</option>
                <option value="no_funds">No funds</option>
                <option value="active">Active within date range</option>
                <option value="inactive">Inactive within date range</option>
            </select>
        </label>
        <label>Date from <input type="date" name="date_from"></label>
        <label>Date to <input type="date" name="date_to"></label>
        <label>Mail driver
            <select name="mail_driver">
                <option value="php">PHP mail</option>
                <option value="smtp">SMTP</option>
            </select>
        </label>
        <label>Body <textarea name="body" rows="8"></textarea></label>
        <button>Create Campaign</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Rule</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <td><?= Security::e($campaign['name']) ?></td>
                    <td><?= Security::e($campaign['target_rule']) ?></td>
                    <td><?= Security::e($campaign['status']) ?> · <?= number_format((int) $campaign['total_recipients']) ?> queued</td>
                    <td>
                        <form method="post" action="/admin/campaigns/<?= (int) $campaign['id'] ?>/activate" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <button>Activate</button>
                        </form>
                        <form method="post" action="/admin/campaigns/<?= (int) $campaign['id'] ?>/send" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                            <button>Send Batch</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
