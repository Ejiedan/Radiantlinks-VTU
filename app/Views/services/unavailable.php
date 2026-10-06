<?php use App\Core\Security; ?>
<section class="panel">
    <h1>Service Unavailable</h1>
    <p><?= Security::e(strtoupper(str_replace('_', ' ', $type))) ?> is currently disabled by the admin.</p>
</section>

