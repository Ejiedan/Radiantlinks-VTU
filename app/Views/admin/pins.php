<?php use App\Core\Security; ?>
<section class="panel">
    <h1>PIN Manager</h1>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
    <p class="muted">Upload recharge card, data PIN and exam ePIN stock. Customer purchases can reserve from this stock before falling back to a configured API.</p>

    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <div class="form-two">
            <label>Service
                <select name="service" required>
                    <option value="airtime_pin">Airtime PIN</option>
                    <option value="data_pin">Data PIN</option>
                    <option value="exam_epin">Exam ePIN</option>
                    <option value="recharge_card">Recharge Card</option>
                </select>
            </label>
            <label>Provider / Exam Body <input name="provider" placeholder="mtn, airtel, waec, jamb" required></label>
        </div>
        <div class="form-two">
            <label>Service Type <input name="service_type" value="local" required></label>
            <label>Value / Plan Code <input name="value" placeholder="100, 1GB, waec, jamb" required></label>
        </div>
        <label>Price <input type="number" name="price" step="0.01" min="1" required></label>
        <label>PIN Data
            <textarea name="pins_data" rows="8" placeholder="PIN,SERIAL&#10;PIN2,SERIAL2" required></textarea>
            <small class="field-hint">One PIN per line. Serial is optional. Duplicate PINs are ignored.</small>
        </label>
        <button>Upload PIN Stock</button>
    </form>
</section>

<section class="panel">
    <h2>Stock Summary</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Service</th><th>Provider</th><th>Type</th><th>Value</th><th>Price</th><th>Unused</th><th>Used</th></tr></thead>
            <tbody>
            <?php foreach ($stock as $row): ?>
                <tr>
                    <td><?= Security::e($row['service']) ?></td>
                    <td><?= Security::e($row['provider']) ?></td>
                    <td><?= Security::e($row['service_type']) ?></td>
                    <td><?= Security::e($row['value']) ?></td>
                    <td>₦<?= number_format((float) $row['price'], 2) ?></td>
                    <td><?= (int) $row['unused_count'] ?></td>
                    <td><?= (int) $row['used_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Recent PINs</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Service</th><th>Provider</th><th>Value</th><th>PIN</th><th>Serial</th><th>Status</th><th>Used</th></tr></thead>
            <tbody>
            <?php foreach ($pins as $pin): ?>
                <tr>
                    <td><?= Security::e($pin['service']) ?></td>
                    <td><?= Security::e($pin['provider']) ?></td>
                    <td><?= Security::e($pin['value']) ?></td>
                    <td><?= Security::e($pin['pin']) ?></td>
                    <td><?= Security::e($pin['serial']) ?></td>
                    <td><?= Security::e($pin['status']) ?></td>
                    <td><?= Security::e($pin['used_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
