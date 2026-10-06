<?php use App\Core\Security; ?>
<section class="panel">
    <h1><?= Security::e(strtoupper(str_replace('_', ' ', $type))) ?></h1>
    <?php if (!empty($_SESSION['notice'])): ?><p class="alert"><?= Security::e($_SESSION['notice']) ?></p><?php unset($_SESSION['notice']); endif; ?>
    <?php if (!empty($error)): ?><p class="alert"><?= Security::e($error) ?></p><?php endif; ?>
    <form method="post" class="stacked-form js-service-form" data-service="<?= Security::e($type) ?>">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <input type="hidden" name="_purchase_token" value="<?= Security::e($purchaseToken ?? '') ?>">
        <?php if (in_array($type, ['airtime', 'data'], true)): ?>
            <label><span data-dynamic-label="network">Network</span>
                <select name="provider" data-plan-filter required>
                    <option value="mtn">MTN</option>
                    <option value="airtel">Airtel</option>
                    <option value="glo">Glo</option>
                    <option value="9mobile">9mobile</option>
                </select>
            </label>
            <label><span data-dynamic-label="recipient">Phone Number</span> <input name="recipient" inputmode="tel" data-phone-field data-single-recipient placeholder="08031234567" required></label>
            <?php if ($type === 'data'): ?>
                <label>Service Type
                    <select name="service_type" data-plan-filter>
                        <option value="">All available data types</option>
                        <option value="sme">SME</option>
                        <option value="corporate">Corporate</option>
                        <option value="gifting">Gifting</option>
                    </select>
                </label>
                <label>Plan
                    <select name="plan_id" data-plan-select>
                        <option value="">Select plan</option>
                    </select>
                    <small class="field-hint" data-plan-help>Select a network and service type to load available plans.</small>
                </label>
            <?php endif; ?>
            <label class="inline-check">
                <input type="checkbox" name="bulk_enabled" value="1" data-bulk-toggle>
                <span>Bulk <?= Security::e($type === 'data' ? 'data' : 'airtime') ?> purchase</span>
            </label>
            <label class="bulk-recipient-box" hidden>Phone Numbers
                <textarea name="bulk_recipients" rows="4" placeholder="08031234567, 08039876543, 08123456789"></textarea>
                <small class="field-hint">Separate phone numbers with commas. This bulk purchase uses this <?= Security::e($type) ?> setup only.</small>
            </label>
        <?php elseif ($type === 'exam_epin'): ?>
            <label>Exam Body
                <select name="provider" data-plan-filter required>
                    <option value="waec">WAEC</option>
                    <option value="neco">NECO</option>
                    <option value="jamb">JAMB</option>
                    <option value="nabteb">NABTEB</option>
                </select>
            </label>
            <label>Customer Phone <input name="recipient" inputmode="tel" data-phone-field placeholder="08031234567" required></label>
            <label>Plan
                <select name="plan_id" data-plan-select required>
                    <option value="">Select plan</option>
                </select>
                <small class="field-hint" data-plan-help>Only active available plans will show here.</small>
            </label>
        <?php elseif ($type === 'data_pin' || $type === 'airtime_pin'): ?>
            <label>Network
                <select name="provider" required>
                    <option value="mtn">MTN</option>
                    <option value="airtel">Airtel</option>
                    <option value="glo">Glo</option>
                    <option value="9mobile">9mobile</option>
                </select>
            </label>
            <label>Receiver Phone <input name="recipient" inputmode="tel" data-phone-field placeholder="08031234567" required></label>
            <input type="hidden" name="service_type" value="local">
            <label>Plan
                <select name="plan_id" data-plan-select>
                    <option value="">Select plan</option>
                </select>
                <small class="field-hint" data-plan-help>Only active available plans will show here.</small>
            </label>
        <?php elseif ($type === 'electricity'): ?>
            <label>Disco
                <select name="provider" required>
                    <option value="benin">Benin</option>
                    <option value="eko">Eko</option>
                    <option value="ikeja">Ikeja</option>
                    <option value="ibadan">Ibadan</option>
                    <option value="abuja">Abuja</option>
                    <option value="kano">Kano</option>
                    <option value="jos">Jos</option>
                    <option value="kaduna">Kaduna</option>
                    <option value="enugu">Enugu</option>
                    <option value="portharcourt">Port Harcourt</option>
                    <option value="aba">Aba</option>
                    <option value="yola">Yola</option>
                </select>
            </label>
            <label>Meter Number
                <div class="field-with-action">
                    <input name="recipient" placeholder="Enter prepaid meter number" data-verify-recipient required>
                    <button type="button" class="secondary-button verify-btn" data-verify-customer>Verify</button>
                </div>
                <small class="verification-result" data-verify-result>Enter meter number and click verify.</small>
                <input type="hidden" name="customer_name" data-verified-name>
            </label>
            <label>Electricity Type
                <select name="plan_id">
                    <option value="prepaid" selected>Prepaid</option>
                    <option value="postpaid">Postpaid</option>
                </select>
            </label>
            <label>Customer Phone <input name="customer_phone" inputmode="tel" data-phone-field placeholder="08031234567" required></label>
        <?php elseif ($type === 'cable'): ?>
            <label>Cable Provider
                <select name="provider" data-plan-filter required>
                    <option value="dstv">DSTV</option>
                    <option value="gotv">GOtv</option>
                    <option value="startimes">StarTimes</option>
                </select>
            </label>
            <label><span data-dynamic-label="recipient">Smartcard / IUC Number</span>
                <div class="field-with-action">
                    <input name="recipient" placeholder="Enter smartcard or IUC number" data-verify-recipient required>
                    <button type="button" class="secondary-button verify-btn" data-verify-customer>Verify</button>
                </div>
                <small class="verification-result" data-verify-result>Enter IUC and click verify.</small>
                <input type="hidden" name="customer_name" data-verified-name>
            </label>
            <label>Package
                <select name="plan_id" data-plan-select required>
                    <option value="">Select package</option>
                </select>
                <small class="field-hint" data-plan-help>Only active packages for the selected provider will show here.</small>
            </label>
            <label>Customer Phone <input name="customer_phone" inputmode="tel" data-phone-field placeholder="08031234567" required></label>
            <label>Subscription Type
                <select name="subscription_type">
                    <option value="change">Change package</option>
                    <option value="renew">Renew current package</option>
                </select>
            </label>
        <?php else: ?>
            <label>Provider <input name="provider" placeholder="Provider or network" required></label>
            <label>Recipient <input name="recipient" placeholder="Phone, account, meter or smartcard number" required></label>
            <label>Plan ID <input name="plan_id" placeholder="Optional provider plan ID"></label>
        <?php endif; ?>
        <label><span data-dynamic-label="amount">Amount</span> <input type="number" step="0.01" name="amount" placeholder="Enter amount" required data-amount-input <?= $type === 'airtime' ? 'data-min-amount="50" min="50"' : '' ?>></label>
        <label>Coupon code <input name="coupon_code" placeholder="Optional"></label>
        <label>Transaction PIN <input type="password" name="pin" required maxlength="6"></label>
        <?php if ($type === 'electricity'): ?>
            <label>Customer address <textarea name="customer_address"></textarea></label>
            <label><input type="checkbox" name="receipt_show_amount" value="1" checked> Show amount on receipt</label>
            <label>Custom receipt amount <input type="number" step="0.01" name="receipt_display_amount"></label>
            <label>Other receipt details <textarea name="receipt_extra_details"></textarea></label>
        <?php endif; ?>
        <button>Buy <?= Security::e(str_replace('_', ' ', $type)) ?></button>
    </form>
</section>
