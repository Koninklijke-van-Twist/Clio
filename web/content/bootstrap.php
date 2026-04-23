<?php

/**
 * Includes/requires
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../logincheck.php';

/**
 * Page load
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
