<section class="card email-card">
    <h2><?= h(LOC('email.title')) ?></h2>

    <?php if ($pageError !== ''): ?>
        <div class="flash flash-error"><?= h($pageError) ?></div>
    <?php endif; ?>

    <label class="email-search" for="emailSearch">
        <span class="email-search-label"><?= h(LOC('email.search_label')) ?></span>
        <input type="search" id="emailSearch" class="email-search-input"
            placeholder="<?= h(LOC('email.search_placeholder')) ?>" autocomplete="off" spellcheck="false">
    </label>
    <script type="application/json" id="emailSearchData"><?= json_encode(
        $emailSearchData,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>

    <div class="email-layout">
        <aside class="email-thread-list">
            <?php if ($emailThreads === []): ?>
                <p class="muted"><?= h(LOC('email.empty')) ?></p>
            <?php endif; ?>

            <p class="email-search-empty muted" id="emailSearchEmpty" hidden><?= h(LOC('email.search_no_results')) ?></p>

            <?php foreach ($emailThreads as $thread): ?>
                <a class="email-thread-link <?= $selectedEmailThreadFolder === $thread['folder_name'] ? 'active' : '' ?>"
                    data-thread-key="<?= h((string) ($thread['folder_name'] ?? '')) ?>"
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
                <?php $contactLabels = $selectedEmailThread['contact_labels'] ?? []; ?>
                <?php if ($contactLabels !== []): ?>
                    <div class="preview-meta">
                        <strong><?= h(LOC('email.contacts')) ?>:</strong>
                        <?= h(implode(', ', $contactLabels)) ?>
                    </div>
                <?php endif; ?>

                <?php foreach (($selectedEmailThread['emails'] ?? []) as $index => $email): ?>
                    <?php $emlFile = basename((string) ($email['eml_file'] ?? '')); ?>
                    <details class="email-message" data-message-key="<?= (int) $index ?>"
                        <?= $index === 0 ? 'open' : '' ?>>
                        <summary class="email-message-summary">
                            <span class="email-message-subject"><?= h((string) ($email['subject'] ?? $selectedEmailThread['subject'])) ?></span>
                            <?php if ($emlFile !== ''): ?>
                                <a class="email-message-download button-link" download
                                    href="<?= h(getEmailArchiveEmlDownloadUrl((string) $selectedEmailThread['folder_name'], $emlFile)) ?>"
                                    onclick="event.stopPropagation();">
                                    <?= h(LOC('email.download_eml')) ?>
                                </a>
                            <?php endif; ?>
                        </summary>
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

        const searchInput = document.getElementById('emailSearch');
        const searchEmpty = document.getElementById('emailSearchEmpty');
        const searchDataElement = document.getElementById('emailSearchData');
        let emailSearchData = { threads: {}, messages: {} };

        if (searchDataElement)
        {
            try
            {
                emailSearchData = JSON.parse(searchDataElement.textContent || '{}');
            }
            catch (error)
            {
                emailSearchData = { threads: {}, messages: {} };
            }
        }

        if (searchInput)
        {
            function normalizeSearchQuery(value)
            {
                return value.trim().toLowerCase().replace(/\s+/g, ' ');
            }

            function threadMatchesQuery(threadKey, query)
            {
                if (query === '')
                {
                    return true;
                }

                const emailHaystacks = emailSearchData.threads && emailSearchData.threads[threadKey];
                if (Array.isArray(emailHaystacks))
                {
                    return emailHaystacks.some(function (haystack)
                    {
                        return typeof haystack === 'string' && haystack.includes(query);
                    });
                }

                if (typeof emailHaystacks === 'string' && emailHaystacks !== '')
                {
                    return emailHaystacks.includes(query);
                }

                return false;
            }

            function getMessageHaystack(message, messageKey)
            {
                const indexed = emailSearchData.messages && emailSearchData.messages[messageKey];
                if (typeof indexed === 'string' && indexed !== '')
                {
                    return indexed;
                }

                return normalizeSearchQuery(message.textContent || '');
            }

            function applyEmailSearch()
            {
                const query = normalizeSearchQuery(searchInput.value);
                let visibleThreads = 0;

                document.querySelectorAll('.email-thread-link').forEach(function (link)
                {
                    const threadKey = link.getAttribute('data-thread-key') || '';
                    const matches = threadMatchesQuery(threadKey, query);
                    link.classList.toggle('is-search-hidden', !matches);
                    if (matches)
                    {
                        visibleThreads += 1;
                    }
                });

                document.querySelectorAll('.email-message').forEach(function (message)
                {
                    const messageKey = message.getAttribute('data-message-key') || '';
                    const haystack = getMessageHaystack(message, messageKey);
                    const matches = query === '' || haystack.includes(query);
                    message.classList.toggle('is-search-hidden', !matches);
                });

                if (searchEmpty)
                {
                    const hasThreads = document.querySelectorAll('.email-thread-link').length > 0;
                    searchEmpty.classList.toggle('is-search-hidden', !hasThreads || query === '' || visibleThreads > 0);
                }
            }

            searchInput.addEventListener('input', applyEmailSearch);
            searchInput.addEventListener('search', applyEmailSearch);
        }
    })();
</script>