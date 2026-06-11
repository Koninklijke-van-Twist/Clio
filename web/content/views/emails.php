<section class="card email-card">
    <h2><?= h(LOC('email.title')) ?></h2>

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
                    <?php $threadContacts = (array) ($thread['contacts'] ?? []); ?>
                    <?php if ($threadContacts !== []): ?>
                        <span class="email-thread-contacts muted">
                            <?php foreach ($threadContacts as $contact): ?>
                                <span><?= h((string) $contact) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
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
                            <div class="preview-meta email-message-meta">
                                <?php if (!empty($email['from'])): ?>
                                    <div><?= h(LOC('email.from', (string) $email['from'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($email['to'])): ?>
                                    <div><?= h(LOC('email.to', implode(', ', (array) $email['to']))) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($email['date'])): ?>
                                    <div><?= h(LOC('email.date', formatEmailArchiveDate((string) $email['date']))) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php $emailHtml = trim((string) ($email['body_html'] ?? '')); ?>
                            <?php if ($emailHtml !== ''): ?>
                                <iframe class="email-message-html" sandbox referrerpolicy="no-referrer"
                                    srcdoc="<?= h($emailHtml) ?>"></iframe>
                            <?php else: ?>
                                <div class="email-message-text"><?= h((string) ($email['body_text'] ?? '')) ?></div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</section>

<script>
    (function ()
    {
        const frames = document.querySelectorAll('.email-message-html');
        frames.forEach(function (frame)
        {
            frame.addEventListener('load', function ()
            {
                try
                {
                    const height = frame.contentWindow.document.documentElement.scrollHeight;
                    if (height > 0)
                    {
                        frame.style.height = Math.min(Math.max(height + 20, 320), 1400) + 'px';
                    }
                }
                catch (error)
                {
                    // Sandboxed mail HTML blijft bruikbaar met de vaste minimumhoogte.
                }
            });
        });
    })();
</script>