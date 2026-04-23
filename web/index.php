<?php

/**
 * Includes/requires
 */

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/helpers.php';
require_once __DIR__ . '/content/variables.php';
require_once __DIR__ . '/content/actions.php';
require_once __DIR__ . '/content/data.php';
?>
<!doctype html>
<html lang="<?= h(getCurrentLanguage()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(LOC('app.title')) ?></title>

    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Literata:opsz,wght@7..72,400;7..72,600&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg: #f7f3eb;
            --bg-alt: #efe5d5;
            --ink: #222020;
            --muted: #675f55;
            --accent: #0f766e;
            --accent-strong: #0b4d48;
            --warning-bg: #fff4e5;
            --warning-border: #ffb74d;
            --error-bg: #ffe7e7;
            --error-border: #d94a4a;
            --success-bg: #e7f8ef;
            --success-border: #3e9b72;
            --card: #fffcf7;
            --shadow: 0 10px 30px rgba(34, 32, 32, 0.08);
            --radius: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 90% 10%, #ffd9a8 0%, rgba(255, 217, 168, 0) 40%),
                radial-gradient(circle at 5% 95%, #a9e7e2 0%, rgba(169, 231, 226, 0) 38%),
                linear-gradient(170deg, var(--bg) 0%, var(--bg-alt) 100%);
            min-height: 100vh;
        }

        .shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 14px 28px;
            animation: reveal 0.5s ease;
        }

        .hero {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;
            margin-bottom: 14px;
        }

        .hero img {
            width: 145px;
            height: auto;
            display: block;
            margin-bottom: 10px;
        }

        .hero h1 {
            font-family: 'Literata', serif;
            margin: 0;
            font-size: 1.4rem;
        }

        .hero p {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
            position: relative;
        }

        .tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .tab-link {
            text-decoration: none;
            color: var(--ink);
            border: 1px solid #d7cab6;
            border-radius: 999px;
            padding: 8px 14px;
            background: #fff9f1;
            font-weight: 500;
            font-size: 0.95rem;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .tab-link.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .tab-link:hover {
            transform: translateY(-1px);
        }

        .language-switch {
            position: relative;
            isolation: isolate;
        }

        .lang-trigger,
        .lang-option {
            appearance: none;
            background: transparent;
            border: 0;
            padding: 0;
            margin: 0;
            line-height: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 24px;
        }

        .lang-trigger svg,
        .lang-option svg {
            width: 34px;
            height: 24px;
            display: block;
        }

        .lang-menu {
            position: absolute;
            right: 0;
            top: 30px;
            background: rgba(255, 252, 247, 0.98);
            border-radius: 8px;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
            padding: 6px;
            display: grid;
            gap: 6px;
            min-width: 46px;
        }

        .lang-menu[hidden] {
            display: none;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px;
        }

        .muted {
            color: var(--muted);
        }

        .upload-form {
            display: grid;
            gap: 14px;
        }

        .dropzone {
            display: grid;
            gap: 8px;
            border: 2px dashed #bfab90;
            border-radius: 14px;
            padding: 18px;
            background: #fff8ee;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .dropzone.is-dragover {
            border-color: var(--accent);
            background: #e8faf8;
        }

        .dropzone input[type='file'] {
            display: none;
        }

        .selected-file {
            font-size: 0.9rem;
            color: var(--accent-strong);
        }

        .warning-box {
            background: var(--warning-bg);
            border-left: 5px solid var(--warning-border);
            border-radius: 10px;
            padding: 12px;
            font-size: 0.95rem;
        }

        .checkbox-row {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .button-primary,
        .button-secondary,
        .button-link {
            display: inline-block;
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .button-primary {
            background: var(--accent);
            color: #fff;
            font-weight: 600;
        }

        .button-secondary {
            background: #f3e8d8;
            color: var(--ink);
        }

        .button-link {
            background: transparent;
            border: 1px solid #d7cab6;
            color: var(--ink);
            margin-top: 10px;
        }

        .flash {
            padding: 10px 12px;
            border-radius: 10px;
            margin: 0 0 12px;
        }

        .flash-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
        }

        .flash-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
        }

        .summary-layout {
            display: grid;
            gap: 14px;
            grid-template-columns: 1fr;
        }

        .summary-list {
            gap: 10px;
        }

        .summary-item {
            max-height: 150px;
            margin-bottom: 10px;
            border: 1px solid #d9cab2;
            border-radius: 12px;
            padding: 10px;
            background: #fffaf3;
        }

        .summary-item.active {
            border-color: var(--accent);
            box-shadow: inset 0 0 0 1px var(--accent);
        }

        .summary-item h3 {
            margin: 0 0 8px;
            font-size: 1rem;
        }

        .summary-preview {
            background: #fffaf5;
            border: 1px solid #ead9bf;
            border-radius: 12px;
            padding: 12px;
        }

        .preview-meta {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .markdown {
            line-height: 1.55;
        }

        .markdown h1,
        .markdown h2,
        .markdown h3 {
            font-family: 'Literata', serif;
            line-height: 1.25;
        }

        @media (min-width: 840px) {
            .shell {
                padding-top: 28px;
            }

            .hero {
                display: grid;
                grid-template-columns: 1fr auto;
                align-items: center;
                gap: 14px;
            }

            .toolbar {
                justify-content: flex-end;
                margin-top: 0;
            }

            .summary-layout {
                grid-template-columns: 330px 1fr;
            }
        }

        @keyframes reveal {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <main class="shell">
        <?php $currentLang = getCurrentLanguage(); ?>
        <header class="hero">
            <div>
                <img src="logo-website.png" alt="KVT logo">
                <h1><?= h(LOC('app.title')) ?></h1>
                <p><?= h(LOC('app.subtitle')) ?></p>
            </div>
            <nav class="toolbar language-switch" aria-label="<?= h(LOC('lang.menu_label')) ?>">
                <button type="button" class="lang-trigger" id="langTrigger" aria-label="<?= h(LOC('lang.current')) ?>"
                    aria-haspopup="true" aria-expanded="false">
                    <?= getLanguageFlagSvg($currentLang) ?>
                </button>
                <div class="lang-menu" id="langMenu" hidden>
                    <?php foreach (SUPPORTED_LANGUAGES as $langCode => $langData): ?>
                        <?php if ($langCode === $currentLang): ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <a class="lang-option" href="<?= h(appUrl('index.php', ['lang' => $langCode, 'page' => $page])) ?>"
                            aria-label="<?= h($langData['label']) ?>">
                            <?= getLanguageFlagSvg($langCode) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </header>

        <nav class="tabs" aria-label="tabs">
            <a class="tab-link <?= $page === 'upload' ? 'active' : '' ?>"
                href="<?= h(appUrl('index.php', ['page' => 'upload'])) ?>">
                <?= h(LOC('nav.upload')) ?>
            </a>
            <a class="tab-link <?= $page === 'summaries' ? 'active' : '' ?>"
                href="<?= h(appUrl('index.php', ['page' => 'summaries'])) ?>">
                <?= h(LOC('nav.summaries')) ?>
            </a>
        </nav>

        <?php if (is_array($flash) && isset($flash['type'], $flash['message'])): ?>
            <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
                <?= h($flash['type'] === 'success' ? LOC('flash.success', $flash['message']) : LOC('flash.error', $flash['message'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'summaries'): ?>
            <?php require __DIR__ . '/content/views/summaries.php'; ?>
        <?php else: ?>
            <?php require __DIR__ . '/content/views/upload.php'; ?>
        <?php endif; ?>
    </main>
    <script>
        (function ()
        {
            const trigger = document.getElementById('langTrigger');
            const menu = document.getElementById('langMenu');

            if (!trigger || !menu)
            {
                return;
            }

            function closeMenu ()
            {
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }

            trigger.addEventListener('click', function (event)
            {
                event.stopPropagation();
                const open = !menu.hidden;
                menu.hidden = open;
                trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
            });

            document.addEventListener('click', function (event)
            {
                if (!menu.hidden && !menu.contains(event.target) && event.target !== trigger)
                {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function (event)
            {
                if (event.key === 'Escape')
                {
                    closeMenu();
                }
            });
        })();
    </script>
</body>

</html>