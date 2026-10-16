<?php

/**
 * Functies
 */

function clioDirectoryGuardHtaccess(): string
{
    return implode("\n", [
        'Options -Indexes',
        '<IfModule mod_authz_core.c>',
        '    Require all denied',
        '</IfModule>',
        '<IfModule !mod_authz_core.c>',
        '    Deny from all',
        '</IfModule>',
        '',
    ]);
}

/**
 * Zet in een data- of cachemap (en alleen die map) directory-listing uit en
 * weigert directe HTTP-requests. Bestaande bestanden blijven staan.
 */
function clioProtectWebDirectory(string $dir): void
{
    static $done = [];

    $dir = rtrim($dir, '\\/');
    if ($dir === '' || isset($done[$dir]) || !is_dir($dir)) {
        return;
    }
    $done[$dir] = true;

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    $expected = clioDirectoryGuardHtaccess();
    $current = is_file($htaccess) ? (string) file_get_contents($htaccess) : null;
    if ($current !== $expected) {
        @file_put_contents($htaccess, $expected);
    }

    foreach (['index.html', 'index.php'] as $indexName) {
        $indexPath = $dir . DIRECTORY_SEPARATOR . $indexName;
        if (!is_file($indexPath)) {
            @file_put_contents($indexPath, '');
        }
    }
}
