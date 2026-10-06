<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Bulk Transactions</h1>
    <?php if (!empty($_SESSION['notice'])): ?><p class="alert"><?= Security::e($_SESSION['notice']) ?></p><?php unset($_SESSION['notice']); endif; ?>
    <form method="post" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <label>Service
            <select name="service">
                <option value="airtime">Airtime</option>
                <option value="data">Data</option>
                <option value="exam_epin">Exam ePIN</option>
                <option value="data_pin">Data PIN</option>
                <option value="electricity">Electricity</option>
            </select>
        </label>
        <label>Rows <textarea name="rows" rows="10" placeholder="recipient,provider,plan,amount,quantity"></textarea></label>
        <label>Transaction PIN <input type="password" name="pin" required maxlength="6"></label>
        <button>Process Bulk Batch</button>
    </form>
</section>
