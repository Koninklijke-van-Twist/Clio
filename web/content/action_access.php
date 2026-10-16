<?php

/**
 * Login-gate voor de UI-acties in actions.php.
 *
 * logincheck.php heeft login/lib.php al geladen voordat dit bestand meedraait.
 * $allowedUsers uit auth.php geldt zodra die lijst bestaat. Zonder lijst blijft
 * elke ingelogde gebruiker toegelaten, zodat e-mails in de UI opvraagbaar blijven.
 */

/**
 * @return list<string>
 */
function clio_known_page_actions(): array
{
    return [
        'toggle_favorite',
        'download_summary',
        'download_email_eml',
        'upload_transcript',
    ];
}

function clio_request_page_action(): string
{
    $fromGet = trim((string) ($_GET['action'] ?? ''));
    if ($fromGet !== '') {
        return $fromGet;
    }

    return trim((string) ($_POST['action'] ?? ''));
}

/**
 * Zelfde vorm als de odata-gate: lijst van adressen, of map met het adres als sleutel.
 *
 * @param mixed $allowedUsers
 */
function clio_email_is_allowed(string $email, $allowedUsers): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !is_array($allowedUsers)) {
        return false;
    }

    foreach ($allowedUsers as $emailKey => $value) {
        $allowedEmail = is_int($emailKey) ? (string) $value : (string) $emailKey;
        if (strtolower(trim($allowedEmail)) === $email) {
            return true;
        }
    }

    return false;
}

/**
 * skip: geen bekende actie, de pagina mag door.
 * deny: niet ingelogd, of wel een whitelist maar dit adres staat er niet op.
 * allow: ingelogd (en op de whitelist als die er is).
 */
function clio_page_action_decision(string $action, string $email, $allowedUsers): string
{
    if ($action === '' || !in_array($action, clio_known_page_actions(), true)) {
        return 'skip';
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return 'deny';
    }

    if (is_array($allowedUsers) && !clio_email_is_allowed($email, $allowedUsers)) {
        return 'deny';
    }

    return 'allow';
}

function clio_script_is_direct_action_endpoint(string $scriptFilename): bool
{
    return basename(str_replace('\\', '/', $scriptFilename)) === 'actions.php';
}

function clio_deny_page_action(): void
{
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo 'Geen toegang';
    exit;
}

function clio_release_page_session_lock(): void
{
    if (function_exists('session_status') && function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function clio_require_page_action_access(): void
{
    $allowedUsers = $GLOBALS['allowedUsers'] ?? null;
    $decision = clio_page_action_decision(
        clio_request_page_action(),
        (string) ($_SESSION['user']['email'] ?? ''),
        $allowedUsers
    );

    if ($decision === 'skip') {
        return;
    }

    if ($decision === 'deny') {
        $forbidden = dirname(__DIR__) . '/../login/403.php';
        if (is_file($forbidden)) {
            require $forbidden;
            die();
        }
        clio_deny_page_action();
    }

    clio_release_page_session_lock();
}
