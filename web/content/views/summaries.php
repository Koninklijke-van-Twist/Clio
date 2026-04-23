<section class="card summaries-card">
    <h2><?= h(LOC('summary.title')) ?></h2>
    <p class="muted"><?= h(LOC('summary.description')) ?></p>

    <?php if ($pageError !== ''): ?>
        <div class="flash flash-error"><?= h($pageError) ?></div>
    <?php endif; ?>

    <div class="summary-layout">
        <aside class="summary-list">
            <?php if ($summaries === []): ?>
                <p class="muted"><?= h(LOC('summary.empty')) ?></p>
            <?php endif; ?>

            <?php foreach ($summaries as $item): ?>
                <article class="summary-item <?= $selectedSummaryId === $item['drive_item_id'] ? 'active' : '' ?> <?= ($item['is_owned_by_user'] ?? false) ? 'summary-item-own' : '' ?>">
                    <?php if (($item['is_openable'] ?? false) === true): ?>
                        <a class="favorite-toggle"
                            href="<?= h(appUrl('index.php', ['page' => 'summaries', 'action' => 'toggle_favorite', 'summary_id' => $item['drive_item_id'], 'is_favorite' => ($item['is_favorite'] ?? false) ? '1' : '0', 'selected_summary_id' => $selectedSummaryId])) ?>"
                            aria-label="<?= h(($item['is_favorite'] ?? false) ? LOC('summary.favorite_remove') : LOC('summary.favorite_add')) ?>"
                            title="<?= h(($item['is_favorite'] ?? false) ? LOC('summary.favorite_remove') : LOC('summary.favorite_add')) ?>">
                            <?= ($item['is_favorite'] ?? false) ? '★' : '☆' ?>
                        </a>
                    <?php endif; ?>
                    <h3><?= h($item['name']) ?></h3>
                    <p class="muted">
                        <?= h(($item['is_openable'] ?? false) ? LOC('summary.status_ready') : LOC('summary.status_unprocessed')) ?>
                    </p>
                    <?php if (($item['is_openable'] ?? false) === false): ?>
                        <p class="muted"><?= h(LOC('summary.eta_inline', (string) ($item['eta_text'] ?? LOC('summary.eta_less_than_minute')))) ?></p>
                    <?php endif; ?>
                    <a class="button-secondary"
                        href="<?= h(appUrl('index.php', ['page' => 'summaries', 'summary_id' => $item['drive_item_id']])) ?>">
                        <?= h(LOC('summary.open')) ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </aside>

        <section class="summary-preview">
            <h3><?= h(LOC('summary.preview_title')) ?></h3>

            <?php if ($selectedSummaryPendingMessage !== ''): ?>
                <div class="flash flash-error"><?= h($selectedSummaryPendingMessage) ?></div>
            <?php endif; ?>

            <?php if ($selectedSummaryText === ''): ?>
                <p class="muted"><?= h(LOC('summary.preview_placeholder')) ?></p>
            <?php else: ?>
                <div class="preview-meta">
                    <?= h(LOC('summary.loaded', $selectedSummaryName !== '' ? $selectedSummaryName : $selectedSummaryId)) ?>
                </div>
                <article id="markdownPreview" class="markdown"></article>
                <a class="button-link" download
                    href="<?= h($selectedSummaryCacheUrl !== '' ? $selectedSummaryCacheUrl : appUrl('index.php', ['page' => 'summaries', 'action' => 'download_summary', 'summary_id' => $selectedSummaryId])) ?>">
                    <?= h(LOC('summary.download_md')) ?>
                </a>
                <?php foreach ($summaries as $item): ?>
                    <?php if ($item['drive_item_id'] === $selectedSummaryId && $item['web_url'] !== ''): ?>
                        <a class="button-link" target="_blank" rel="noopener noreferrer" href="<?= h($item['web_url']) ?>">
                            <?= h(LOC('summary.source')) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php if ($selectedSummaryText !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        (function ()
        {
            const sourceText = <?= json_encode($selectedSummaryText) ?>;
            const target = document.getElementById('markdownPreview');
            if (!target)
            {
                return;
            }

            target.innerHTML = marked.parse(sourceText);
        })();
    </script>
<?php endif; ?>