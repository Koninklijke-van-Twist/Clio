<?php

declare(strict_types=1);

function clioResolveAuthFile(): string
{
    $webDir = __DIR__;
    $authFile = $webDir . '/auth.php';
    $mockFile = $webDir . '/mock-auth.php';

    if (getenv('CLIO_USE_MOCK_AUTH') === '1') {
        if (!is_file($mockFile)) {
            throw new RuntimeException('mock-auth.php ontbreekt');
        }

        return $mockFile;
    }

    if (is_file($authFile)) {
        return $authFile;
    }

    throw new RuntimeException('auth.php ontbreekt; zet CLIO_USE_MOCK_AUTH=1 voor tests');
}

if (!defined('CLIO_AUTH_LOADED')) {
    require_once clioResolveAuthFile();
    define('CLIO_AUTH_LOADED', true);
}
