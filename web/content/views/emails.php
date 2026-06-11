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
                                <iframe class="email-message-html" sandbox="allow-same-origin" referrerpolicy="no-referrer"
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
        const heightPadding = 8;

        function resizeEmailFrame(frame)
        {
            try
            {
                const doc = frame.contentDocument;
                const body = doc && doc.body;
                const html = doc && doc.documentElement;
                if (!body || !html)
                {
                    return;
                }

                frame.style.height = '0px';
                const height = Math.max(
                    body.scrollHeight,
                    body.offsetHeight,
                    html.scrollHeight,
                    html.offsetHeight
                );
                if (height > 0)
                {
                    frame.style.height = (height + heightPadding) + 'px';
                }
            }
            catch (error)
            {
                // allow-same-origin is nodig om de iframe-inhoud te kunnen meten.
            }
        }

        function watchEmailFrame(frame)
        {
            frame.addEventListener('load', function ()
            {
                resizeEmailFrame(frame);

                try
                {
                    const body = frame.contentDocument && frame.contentDocument.body;
                    if (body && typeof ResizeObserver !== 'undefined')
                    {
                        const observer = new ResizeObserver(function ()
                        {
                            resizeEmailFrame(frame);
                        });
                        observer.observe(body);
                    }
                }
                catch (error)
                {
                    // ResizeObserver is optioneel; load-resize blijft werken.
                }
            });

            const details = frame.closest('details');
            if (details)
            {
                details.addEventListener('toggle', function ()
                {
                    if (details.open)
                    {
                        resizeEmailFrame(frame);
                    }
                });
            }
        }

        document.querySelectorAll('.email-message-html').forEach(watchEmailFrame);
    })();
</script>