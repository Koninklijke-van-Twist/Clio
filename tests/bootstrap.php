<?php

declare(strict_types=1);

putenv('CLIO_USE_MOCK_AUTH=1');
$_ENV['CLIO_USE_MOCK_AUTH'] = '1';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_GET = [];
$_POST = [];
$_SESSION = [];

require_once __DIR__ . '/../web/content/constants.php';
require_once __DIR__ . '/../web/content/localization.php';
require_once __DIR__ . '/../web/content/helpers.php';
require_once __DIR__ . '/../web/content/email_archive.php';
