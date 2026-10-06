<?php use App\Core\Security; ?>

<section class="panel">
    <div class="receipt-head">
        <div>
            <h1>Homepage Builder</h1>
            <p class="muted">Drag sections to arrange the public homepage. Items use one line each: <code>Title | Text</code>.</p>
        </div>
        <a class="button-link secondary" href="/" target="_blank">Preview Home</a>
    </div>
    <?php if (!empty($notice)): ?><p class="alert"><?= Security::e($notice) ?></p><?php endif; ?>
</section>

<section class="panel builder-grid">
    <form method="post" action="/admin/homepage" class="stacked-form builder-form">
        <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
        <input type="hidden" name="id" id="builder-id">
        <h2>Add or edit section</h2>
        <label>Section type
            <select name="type" id="builder-type">
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= Security::e($value) ?>"><?= Security::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Title <input name="title" id="builder-title" required></label>
        <label>Small heading / eyebrow <input name="subtitle" id="builder-subtitle" placeholder="Example: Services"></label>
        <label>Body text <textarea name="content_text" id="builder-content-text" rows="4" placeholder="Main section paragraph"></textarea></label>
        <label>Button label <input name="button_label" id="builder-button-label" placeholder="Optional"></label>
        <label>Button URL <input name="button_url" id="builder-button-url" placeholder="/register"></label>
        <label>Sort order <input name="sort_order" id="builder-sort-order" type="number" value="10"></label>
        <label>Status
            <select name="status" id="builder-status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>
        <label>Items / proof points
            <textarea name="items" id="builder-items" rows="8" placeholder="Airtime | Top up any major network&#10;Data | Send data instantly"></textarea>
        </label>
        <button>Save Section</button>
    </form>

    <div>
        <h2>Current sections</h2>
        <form method="post" action="/admin/homepage/order" class="homepage-order-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <input type="hidden" name="section_order" id="section-order">
            <div class="homepage-builder-list" data-homepage-builder>
                <?php foreach ($sections as $section): ?>
                    <?php
                    $content = is_array($section['content'] ?? null) ? $section['content'] : [];
                    $items = $section['type'] === 'hero'
                        ? implode(', ', array_map('strval', $content['proof'] ?? []))
                        : implode("\n", array_map(fn ($item) => ($item['title'] ?? '') . ' | ' . ($item['text'] ?? ''), $content['items'] ?? []));
                    $contentText = (string) ($content['text'] ?? '');
                    ?>
                    <article class="builder-section" draggable="true" data-id="<?= (int) $section['id'] ?>">
                        <div>
                            <strong><?= Security::e((string) $section['title']) ?></strong>
                            <span><?= Security::e((string) $section['type']) ?> · <?= Security::e((string) $section['status']) ?> · order <?= (int) $section['sort_order'] ?></span>
                        </div>
                        <div class="builder-actions">
                            <button type="button"
                                data-edit-section
                                data-id="<?= (int) $section['id'] ?>"
                                data-type="<?= Security::e((string) $section['type']) ?>"
                                data-title="<?= Security::e((string) $section['title']) ?>"
                                data-subtitle="<?= Security::e((string) $section['subtitle']) ?>"
                                data-content-text="<?= Security::e($contentText) ?>"
                                data-button-label="<?= Security::e((string) $section['button_label']) ?>"
                                data-button-url="<?= Security::e((string) $section['button_url']) ?>"
                                data-sort-order="<?= (int) $section['sort_order'] ?>"
                                data-status="<?= Security::e((string) $section['status']) ?>"
                                data-items="<?= Security::e($items) ?>">Edit</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($sections): ?><button class="secondary-button">Save Drag Order</button><?php endif; ?>
        </form>

        <?php foreach ($sections as $section): ?>
            <form method="post" action="/admin/homepage/<?= (int) $section['id'] ?>/delete" class="inline-form">
                <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
                <button data-confirm="Delete this homepage section?">Delete #<?= (int) $section['id'] ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
