<?php use App\Core\Security; ?>
<?php
$sections = $sections ?? [];
$settings = $appSettings ?? [];
$siteTitle = (string) ($settings['site_title'] ?? 'Radiantlinks VTU');
$logoUrl = trim((string) ($settings['site_logo_url'] ?? ''));
$logoUrl = $logoUrl !== '' ? stored_media_url($logoUrl) : '';
$template = (string) ($settings['landing_template'] ?? 'template1');
$navLines = preg_split('/\R/', (string) ($settings['public_nav_links'] ?? "Home|/\nServices|#services\nPricing|#plans\nReseller|#reseller\nContact|#contact\nCreate Account|/register")) ?: [];
$navLinks = [];
foreach ($navLines as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '#');
    $navLinks[] = ['label' => $label, 'url' => $url ?: '#'];
}
$renderBrand = static function (string $class = 'public-brand') use ($logoUrl, $siteTitle): void { ?>
    <a class="<?= Security::e($class) ?>" href="/">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= Security::e($logoUrl) ?>" alt="<?= Security::e($siteTitle) ?> logo">
        <?php else: ?>
            <span class="brand-mark">R</span>
        <?php endif; ?>
        <span><?= Security::e($siteTitle) ?></span>
    </a>
<?php };
?>

<?php if ($template === 'template2'): ?>
<div class="rl-topbar">
    <span>Fast, Affordable & Reliable VTU Services</span>
    <span>Nigeria's Trusted VTU Platform</span>
</div>
<nav class="public-nav rl-nav">
    <?php $renderBrand('public-brand rl-brand'); ?>
    <div>
        <?php foreach ($navLinks as $index => $link): ?>
            <a class="<?= $index === count($navLinks) - 1 ? 'button-link' : '' ?>" href="<?= Security::e($link['url']) ?>"><?= Security::e($link['label']) ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<section class="rl-hero">
    <div class="rl-hero-copy">
        <p class="rl-pill">Trusted by customers across Nigeria</p>
        <h1>Stay Connected. Pay Bills. <span>Live Smarter.</span></h1>
        <p>Get instant data, airtime, electricity and cable TV payments at the best rates, anytime, anywhere.</p>
        <div class="hero-actions">
            <a class="button-link" href="/register">Get Started</a>
            <a class="button-link secondary" href="/login">Login</a>
        </div>
        <div class="rl-proof">
            <div><b>Lightning fast</b><span>Instant delivery</span></div>
            <div><b>Secure wallet</b><span>Protected payments</span></div>
            <div><b>24/7 support</b><span>Always available</span></div>
        </div>
    </div>
    <div class="rl-hero-art">
        <div class="rl-person-card">
            <div class="rl-avatar"></div>
            <p>Same connections. Brighter possibilities.</p>
        </div>
        <div class="rl-phone">
            <div class="phone-top"><span></span><span></span><span></span></div>
            <strong><?= Security::e($siteTitle) ?></strong>
            <div class="rl-check">✓</div>
            <b>Data Purchase Successful</b>
            <em>1GB MTN Data</em>
            <h3>₦245</h3>
            <small>Status: Successful</small>
            <button>Done</button>
        </div>
    </div>
</section>

<section class="rl-networks">
    <p>All Major Networks & Billers Supported</p>
    <div><b>MTN</b><b>Airtel</b><b>Glo</b><b>9mobile</b><b>DStv</b><b>GOtv</b><b>Electricity</b></div>
</section>

<section class="rl-section" id="services">
    <div class="rl-center">
        <h2>Everything You Need in One Place</h2>
        <p>Simple. Fast. Reliable. Designed for Nigerians.</p>
    </div>
    <div class="rl-card-grid">
        <a class="rl-card" href="/service/data"><b>Buy Data</b><span>Get the best data plans for all networks.</span></a>
        <a class="rl-card" href="/service/airtime"><b>Airtime Recharge</b><span>Top up any network instantly.</span></a>
        <a class="rl-card" href="/service/electricity"><b>Electricity Bills</b><span>Pay your electricity bills with ease.</span></a>
        <a class="rl-card" href="/service/cable"><b>Cable TV</b><span>Renew your DStv, GOtv or StarTimes subscription.</span></a>
    </div>
</section>

<section class="rl-section" id="plans">
    <div class="section-head">
        <div><h2>Popular Data Plans</h2><p class="muted">Affordable plans for your everyday needs.</p></div>
        <a href="/service/data">View All Plans</a>
    </div>
    <div class="rl-plan-grid">
        <?php foreach ([['MTN 1GB', '₦245'], ['MTN 2GB', '₦490'], ['MTN 5GB', '₦1,225']] as $plan): ?>
            <article class="rl-plan"><span>MTN</span><div><b><?= Security::e($plan[0]) ?></b><small>Valid for 30 Days</small><strong><?= Security::e($plan[1]) ?></strong></div><a class="button-link" href="/service/data">Buy Now</a></article>
        <?php endforeach; ?>
    </div>
</section>

<section class="rl-steps">
    <h2>How It Works</h2>
    <div>
        <article><b>1</b><strong>Create Account</strong><span>Sign up in a few seconds.</span></article>
        <article><b>2</b><strong>Fund Wallet</strong><span>Add money using your preferred method.</span></article>
        <article><b>3</b><strong>Make Payment</strong><span>Choose a service and complete your payment.</span></article>
    </div>
</section>

<section class="rl-reseller" id="reseller">
    <div>
        <p class="eyebrow dark">For Entrepreneurs</p>
        <h2>Start Your Own VTU Business</h2>
        <p>Earn extra income with reseller rates, a powerful dashboard, and detailed transaction tracking.</p>
        <a class="button-link" href="/register">Become a Reseller</a>
    </div>
    <div class="rl-dashboard-card">
        <b>Wallet Balance</b><strong>₦52,340.00</strong><span>Total Transactions 1,248</span>
    </div>
</section>

<section class="rl-section">
    <h2>Why Choose <?= Security::e($siteTitle) ?>?</h2>
    <div class="rl-card-grid">
        <div class="rl-card"><b>Instant Delivery</b><span>All services delivered in seconds.</span></div>
        <div class="rl-card"><b>Best Rates</b><span>Get competitive prices.</span></div>
        <div class="rl-card"><b>Secure Transactions</b><span>Your money and privacy are safe.</span></div>
        <div class="rl-card"><b>Reliable Support</b><span>Our team is available 24/7.</span></div>
    </div>
</section>

<section class="rl-whatsapp" id="contact">
    <div><h2>Buy Data Simply by Chatting on WhatsApp</h2><p>No registration required.</p></div>
    <a class="button-link secondary" href="https://wa.me/" target="_blank">Chat on WhatsApp</a>
</section>

<footer class="rl-footer">
    <?php $renderBrand('public-brand rl-brand'); ?>
    <p>Your trusted VTU platform for data, airtime, electricity and TV payments.</p>
    <small>Made for a more connected Nigeria.</small>
</footer>
<?php else: ?>
<nav class="public-nav">
    <?php $renderBrand(); ?>
    <div>
        <?php foreach ($navLinks as $index => $link): ?>
            <a class="<?= $index === count($navLinks) - 1 ? 'button-link' : '' ?>" href="<?= Security::e($link['url']) ?>"><?= Security::e($link['label']) ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<?php foreach ($sections as $section): ?>
    <?php
    $type = (string) ($section['type'] ?? 'text');
    $title = (string) ($section['title'] ?? '');
    $subtitle = (string) ($section['subtitle'] ?? '');
    $content = is_array($section['content'] ?? null) ? $section['content'] : [];
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
    ?>

    <?php if ($type === 'hero'): ?>
        <section class="public-hero premium-hero">
            <div class="hero-copy">
                <?php if ($subtitle !== ''): ?><p class="eyebrow"><?= Security::e($subtitle) ?></p><?php endif; ?>
                <h1><?= Security::e($title) ?></h1>
                <p><?= Security::e((string) ($content['text'] ?? 'Fast purchases, clean receipts, and a secure wallet built for everyday digital payments.')) ?></p>
                <div class="hero-actions">
                    <a class="button-link" href="<?= Security::e((string) ($section['button_url'] ?: '/register')) ?>"><?= Security::e((string) ($section['button_label'] ?: 'Start Buying')) ?></a>
                    <a class="button-link secondary" href="/login">Access Dashboard</a>
                </div>
                <?php $proof = is_array($content['proof'] ?? null) ? $content['proof'] : []; ?>
                <?php if ($proof): ?><div class="hero-proof"><?php foreach ($proof as $line): ?><span><?= Security::e((string) $line) ?></span><?php endforeach; ?></div><?php endif; ?>
            </div>
            <div class="phone-mockup" aria-label="Dashboard preview">
                <div class="phone-top"><span></span><span></span><span></span></div>
                <div class="wallet-card"><small>Wallet Balance</small><strong>₦24,850.00</strong><em>Active customer</em></div>
                <div class="quick-grid"><div>Airtime</div><div>Data</div><div>ePIN</div><div>Bills</div></div>
                <div class="mini-list"><p><span>MTN Data</span><b>Success</b></p><p><span>WAEC PIN</span><b>Ready</b></p><p><span>Wallet Credit</span><b>Approved</b></p></div>
            </div>
        </section>
    <?php elseif ($type === 'stats'): ?>
        <section class="landing-stats"><?php foreach ($items as $item): ?><div><strong><?= Security::e((string) ($item['title'] ?? '')) ?></strong><span><?= Security::e((string) ($item['text'] ?? '')) ?></span></div><?php endforeach; ?></section>
    <?php elseif ($type === 'services' || $type === 'feature_cards'): ?>
        <section class="landing-section"><div class="section-head"><div><?php if ($subtitle !== ''): ?><p class="eyebrow dark"><?= Security::e($subtitle) ?></p><?php endif; ?><?php if ($title !== ''): ?><h2><?= Security::e($title) ?></h2><?php endif; ?></div></div><div class="service-grid landing-services"><?php foreach ($items as $item): ?><div class="service-tile"><strong><?= Security::e((string) ($item['title'] ?? '')) ?></strong><span><?= Security::e((string) ($item['text'] ?? '')) ?></span></div><?php endforeach; ?></div></section>
    <?php elseif ($type === 'split'): ?>
        <section class="landing-section split-band"><div><?php if ($subtitle !== ''): ?><p class="eyebrow dark"><?= Security::e($subtitle) ?></p><?php endif; ?><h2><?= Security::e($title) ?></h2><p><?= Security::e((string) ($content['text'] ?? 'Customers get fast purchases, wallet history, printable receipts, and a simple dashboard that works beautifully on mobile.')) ?></p></div><div class="feature-preview"><?php foreach ($items as $item): ?><p><span><?= Security::e((string) ($item['title'] ?? '')) ?></span><b><?= Security::e((string) ($item['text'] ?? '')) ?></b></p><?php endforeach; ?></div></section>
    <?php elseif ($type === 'cta'): ?>
        <section class="landing-section cta-band"><p class="eyebrow dark"><?= Security::e($subtitle ?: 'Get Started') ?></p><h2><?= Security::e($title) ?></h2><a class="button-link" href="<?= Security::e((string) ($section['button_url'] ?: '/register')) ?>"><?= Security::e((string) ($section['button_label'] ?: 'Create Account')) ?></a></section>
    <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
