<?php use App\Core\Security; ?>
<section class="hero-panel">
    <p class="eyebrow">Verification</p>
    <h1>Customer KYC</h1>
    <p>Submit your identity details so payment providers can generate dedicated account numbers securely.</p>
</section>
<?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
<?php if (!empty($submission)): ?>
<section class="panel">
    <h2>Latest Submission</h2>
    <div class="summary-grid">
        <p><span>Status</span><strong><?= Security::e($submission['status']) ?></strong></p>
        <p><span>Name</span><strong><?= Security::e($submission['full_name']) ?></strong></p>
        <p><span>ID Type</span><strong><?= strtoupper(Security::e($submission['id_type'])) ?></strong></p>
        <p><span>Submitted</span><strong><?= Security::e($submission['created_at']) ?></strong></p>
    </div>
    <?php if (!empty($submission['admin_note'])): ?><p class="muted"><?= Security::e($submission['admin_note']) ?></p><?php endif; ?>
</section>
<?php endif; ?>
<section class="panel">
    <h2>Submit Verification</h2>
    <form method="post" action="/verification" enctype="multipart/form-data" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Full name <input name="full_name" required value="<?= Security::e($submission['full_name'] ?? '') ?>"></label>
        <label>Date of birth <input type="date" name="dob" required value="<?= Security::e($submission['dob'] ?? '') ?>"></label>
        <label>NIN/BVN type
            <select name="id_type" required>
                <option value="bvn" <?= (($submission['id_type'] ?? '') === 'bvn') ? 'selected' : '' ?>>BVN</option>
                <option value="nin" <?= (($submission['id_type'] ?? '') === 'nin') ? 'selected' : '' ?>>NIN</option>
            </select>
        </label>
        <label>NIN/BVN number <input name="id_number" inputmode="numeric" minlength="11" maxlength="11" required placeholder="11 digits"></label>
        <label>Address <textarea name="address" rows="3"><?= Security::e($submission['address'] ?? '') ?></textarea></label>
        <label>Selfie <input type="file" name="selfie" accept="image/jpeg,image/png,image/webp" required></label>
        <label>Valid ID document <input type="file" name="id_document" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
        <button>Submit Verification</button>
    </form>
</section>
