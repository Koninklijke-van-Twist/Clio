<section class="card email-card">
    <h2><?= h(LOC('email.title')) ?></h2>
    <p class="muted"><?= h(LOC('email.description')) ?></p>

    <?php if ($pageError !== ''): ?>
        <div class="flash flash-error"><?= h($pageError) ?></div>
    <?php endif; ?>

    <div class="email-layout">
        <aside class="email-thread-list">
            <?php if ($emailThreads === []): ?>
                <p class="muted"><?= h(LOC('email.empty')) ?></p>
            <?php endif; ?>

            <?php foreach ($emailThreads as $thread): ?>
                <a class="email-thread-link <?= $selectedEmailThreadFolder === $thread['folder_name'] ? 'active' : '' ?>"
                    href="<?= h(appUrl('index.php', ['page' => 'emails', 'thread' => $thread['folder_name']])) ?>">
                    <strong><?= h($thread['subject']) ?></strong><br>
                    <span class="muted"><?= h($thread['chain_id']) ?></span><br>
                    <span class="muted"><?= h(LOC('email.message_count', (int) ($thread['email_count'] ?? 0))) ?></span>
                </a>
            <?php endforeach; ?>
        </aside>

        <section class="email-preview">
            <h3><?= h(LOC('email.preview_title')) ?></h3>

            <?php if ($selectedEmailThread === null): ?>
                <p class="muted"><?= h(LOC('email.preview_placeholder')) ?></p>
            <?php else: ?>
                <?php $contacts = $selectedEmailThread['contacts'] ?? []; ?>
                <?php if (is_array($contacts) && $contacts !== []): ?>
                    <div class="preview-meta">
                        <strong><?= h(LOC('email.contacts')) ?>:</strong>
                        <?= h(implode(', ', array_map(static function (array $contact): string {
                            $name = trim((string) ($contact['name'] ?? ''));
                            $email = trim((string) ($contact['email'] ?? ''));
                            return $name !== '' ? $name . ' <' . $email . '>' : $email;
                        }, $contacts))) ?>
                    </div>
                <?php endif; ?>

                <?php foreach (($selectedEmailThread['emails'] ?? []) as $index => $email): ?>
                    <details class="email-message" <?= $index === 0 ? 'open' : '' ?>>
                        <summary><?= h((string) ($email['subject'] ?? $selectedEmailThread['subject'])) ?></summary>
                        <div class="email-message-body">
                            <div class="preview-meta">
                                <?php if (!empty($email['from'])): ?>
                                    <?= h(LOC('email.from', (string) $email['from'])) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($email['to'])): ?>
                                    <?= h(LOC('email.to', implode(', ', (array) $email['to']))) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($email['date'])): ?>
                                    <?= h(LOC('email.date', (string) $email['date'])) ?>
                                <?php endif; ?>
                            </div>
                            <?= h((string) ($email['body_text'] ?? '')) ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</section>
