<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/web/odata.php';
require_once dirname(__DIR__) . '/web/content/directory_guard.php';

/**
 * odata.php-acties zijn niet anoniem, en cache_status lekt geen voorbeeldrijen.
 */
final class OdataActionAccessTest extends TestCase
{
    public function testDirectActionsRequireLoginBeforeCacheWork(): void
    {
        $root = dirname(__DIR__);
        $odataSource = (string) file_get_contents($root . '/web/odata.php');
        $dispatch = substr($odataSource, (int) strrpos($odataSource, '$odataAction = '));
        $requireAt = strpos($dispatch, 'odata_require_direct_action_access()');
        $statusAt = strpos($dispatch, 'odata_send_cache_status_json()');
        $deleteAt = strpos($dispatch, 'odata_send_cache_delete_json()');
        $clearAt = strpos($dispatch, 'odata_send_cache_clear_json()');
        $this->assertNotFalse($requireAt);
        $this->assertNotFalse($statusAt);
        $this->assertNotFalse($deleteAt);
        $this->assertNotFalse($clearAt);
        $this->assertLessThan($statusAt, $requireAt);
        $this->assertLessThan($deleteAt, $requireAt);
        $this->assertLessThan($clearAt, $requireAt);

        $gateStart = strpos($odataSource, 'function odata_require_direct_action_access');
        $gateEnd = strpos($odataSource, 'function odata_release_session_lock');
        $this->assertNotFalse($gateStart);
        $this->assertNotFalse($gateEnd);
        $this->assertGreaterThan($gateStart, $gateEnd);
        $gate = substr($odataSource, (int) $gateStart, (int) $gateEnd - (int) $gateStart);
        $emailAt = strpos($gate, 'odata_email_is_allowed');
        $releaseAt = strpos($gate, 'odata_release_session_lock()');
        $this->assertNotFalse($emailAt);
        $this->assertNotFalse($releaseAt);
        $this->assertLessThan($releaseAt, $emailAt);
        $this->assertStringContainsString('session_write_close()', $odataSource);
        $this->assertStringContainsString("php_sapi_name()", $odataSource);
        $this->assertStringContainsString("=== 'cli'", $odataSource);
        $this->assertStringNotContainsString('is_trusted_requester', $odataSource);
        $this->assertStringNotContainsString('odata_cache_extract_attributes_from_payload', $odataSource);
        $this->assertStringNotContainsString('entry.attributes', $odataSource);
    }

    public function testSessionLockIsReleasedAfterTheCheck(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['clio_access_probe'] = 1;

        try {
            odata_release_session_lock();
            $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
        } finally {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
        }
    }

    public function testAllowedUsersAcceptsListAndMap(): void
    {
        $allowed = [
            'analyst@kvt.nl',
            'planner@kvt.nl' => [15, 40],
        ];
        $this->assertTrue(odata_email_is_allowed('Analyst@kvt.nl', $allowed));
        $this->assertTrue(odata_email_is_allowed('planner@kvt.nl', $allowed));
        $this->assertFalse(odata_email_is_allowed('andere@kvt.nl', $allowed));
        $this->assertFalse(odata_email_is_allowed('', $allowed));
        $this->assertTrue(odata_email_is_allowed('analyst@kvt.nl', null));
    }

    public function testRunnerSecretSkipsLoginOnlyForCliOrMatchingHeader(): void
    {
        $this->assertTrue(odata_direct_action_skips_login('cli', '', ''));
        $this->assertFalse(odata_direct_action_skips_login('fpm-fcgi', '', ''));
        $this->assertFalse(odata_direct_action_skips_login('fpm-fcgi', '', 'server-secret'));
        $this->assertFalse(odata_direct_action_skips_login('fpm-fcgi', 'server-secret', ''));
        $this->assertFalse(odata_direct_action_skips_login('fpm-fcgi', 'nope', 'server-secret'));
        $this->assertTrue(odata_direct_action_skips_login('cgi-fcgi', 'server-secret', 'server-secret'));
        $this->assertFalse(odata_direct_action_skips_login('apache2handler', 'server-secret', 'ander-geheim'));

        $savedHeader = $_SERVER['HTTP_X_CLIO_SECRET'] ?? null;
        $savedGetSecret = $_GET['secret'] ?? null;
        $_SERVER['HTTP_X_CLIO_SECRET'] = '  uit-de-header  ';
        $_GET['secret'] = 'uit-de-query';
        try {
            $this->assertSame('uit-de-header', odata_presented_runner_secret());
        } finally {
            if ($savedHeader === null) {
                unset($_SERVER['HTTP_X_CLIO_SECRET']);
            } else {
                $_SERVER['HTTP_X_CLIO_SECRET'] = $savedHeader;
            }
            if ($savedGetSecret === null) {
                unset($_GET['secret']);
            } else {
                $_GET['secret'] = $savedGetSecret;
            }
        }
    }

    public function testRunnerSecretComesFromGlobalThenAuthFileThenEnv(): void
    {
        $authPath = sys_get_temp_dir() . '/clio-auth-runner-secret.php';
        file_put_contents($authPath, "<?php\n\$clioRunnerSecret = \"auth-php-geheim\";\n\$allowedUsers = ['nacht@kvt.nl'];\n");
        $previousAuthPath = $GLOBALS['CLIO_AUTH_PHP_PATH'] ?? null;
        $previousRunner = $GLOBALS['clioRunnerSecret'] ?? null;
        unset($GLOBALS['clioRunnerSecret']);
        $previousEnv = getenv('CLIO_RUNNER_SECRET');
        putenv('CLIO_RUNNER_SECRET');
        $GLOBALS['CLIO_AUTH_PHP_PATH'] = $authPath;

        try {
            $fromFile = odata_read_auth_file_vars();
            $this->assertSame('auth-php-geheim', odata_configured_runner_secret($fromFile));
            $this->assertTrue(odata_email_is_allowed('nacht@kvt.nl', $fromFile['allowedUsers'] ?? null));
            putenv('CLIO_RUNNER_SECRET=env-geheim');
            $this->assertSame('auth-php-geheim', odata_configured_runner_secret($fromFile));
            $GLOBALS['clioRunnerSecret'] = 'global-geheim';
            $this->assertSame('global-geheim', odata_configured_runner_secret($fromFile));
        } finally {
            unset($GLOBALS['clioRunnerSecret']);
            putenv('CLIO_RUNNER_SECRET');
            if ($previousAuthPath === null) {
                unset($GLOBALS['CLIO_AUTH_PHP_PATH']);
            } else {
                $GLOBALS['CLIO_AUTH_PHP_PATH'] = $previousAuthPath;
            }
            if (is_string($previousRunner)) {
                $GLOBALS['clioRunnerSecret'] = $previousRunner;
            }
            if ($previousEnv !== false && $previousEnv !== '') {
                putenv('CLIO_RUNNER_SECRET=' . $previousEnv);
            }
            @unlink($authPath);
        }
    }

    public function testCacheStatusOmitsExampleRowsAndAttributes(): void
    {
        $cacheDir = cache_base_dir();
        $fixtureName = 'clio-access-test-' . bin2hex(random_bytes(8)) . '.json';
        $fixturePath = $cacheDir . '/' . $fixtureName;
        $customerNo = 'C-SECRET-44021';
        $customerName = 'Voorbeeld Klant Jansen';
        $documentNo = 'VF-998877';
        $sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/Customer_Ledger_Entries";
        $fixture = [
            '_meta' => [
                'cached_at' => time(),
                'expires_at' => time() + 3600,
                'source_url' => $sourceUrl,
                'fetched' => true,
            ],
            'data' => [[
                'Customer_No' => $customerNo,
                'Customer_Name' => $customerName,
                'Document_No' => $documentNo,
                'Amount' => 12345.67,
                '@odata.etag' => 'W/"example"',
            ]],
        ];
        file_put_contents($fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $payload = odata_cache_status_payload();
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString($customerNo, $encoded);
            $this->assertStringNotContainsString($customerName, $encoded);
            $this->assertStringNotContainsString($documentNo, $encoded);
            $this->assertStringNotContainsString('12345.67', $encoded);
            $this->assertStringNotContainsString('attributes', $encoded);
            $this->assertStringNotContainsString('Customer_Name', $encoded);

            $match = null;
            foreach ($payload['entries'] as $entry) {
                if (($entry['id'] ?? '') === $fixtureName) {
                    $match = $entry;
                    break;
                }
            }
            $this->assertIsArray($match);
            $this->assertArrayNotHasKey('attributes', $match);
            $this->assertArrayNotHasKey('data', $match);
            $this->assertSame($sourceUrl, $match['url'] ?? '');
            $this->assertGreaterThan(0, (int) ($match['size_bytes'] ?? 0));
        } finally {
            @unlink($fixturePath);
        }

        $widget = injectTimerHtml([
            'statusUrl' => 'odata.php?action=cache_status',
        ]);
        $this->assertStringNotContainsString('attributes', $widget);
        $this->assertStringContainsString('odata.php?action=cache_status', $widget);
    }

    public function testDataAndCacheDirectoriesAreDenied(): void
    {
        $root = dirname(__DIR__);
        $directories = [
            '/web/data',
            '/web/data/emails',
            '/web/data/php_errors',
            '/web/data/user_prefs',
            '/web/cache',
            '/web/cache/odata',
            '/web/cache/summaries',
        ];
        foreach ($directories as $directory) {
            $htaccess = $root . $directory . '/.htaccess';
            $rules = (string) file_get_contents($htaccess);
            $this->assertStringContainsString('Options -Indexes', $rules, $htaccess);
            $this->assertStringContainsString('Require all denied', $rules, $htaccess);
            $this->assertStringContainsString('Deny from all', $rules, $htaccess);
            $this->assertStringContainsString('<IfModule mod_authz_core.c>', $rules, $htaccess);
            $this->assertStringContainsString('<IfModule !mod_authz_core.c>', $rules, $htaccess);
            $this->assertSame(clioDirectoryGuardHtaccess(), $rules, $htaccess);
            foreach (['index.html', 'index.php'] as $indexName) {
                $indexFile = $root . $directory . '/' . $indexName;
                $this->assertFileExists($indexFile);
                $body = (string) file_get_contents($indexFile);
                $this->assertLessThanOrEqual(16, strlen($body), $indexFile);
                $this->assertStringNotContainsString('Customer', $body);
            }
        }
    }

    public function testUiDoesNotLinkToRawMailOrSummaryCache(): void
    {
        $root = dirname(__DIR__);
        $emails = (string) file_get_contents($root . '/web/content/views/emails.php');
        $this->assertStringContainsString('getEmailArchiveEmlDownloadUrl', $emails);
        $this->assertStringNotContainsString('data/emails', $emails);

        $summaries = (string) file_get_contents($root . '/web/content/views/summaries.php');
        $this->assertStringContainsString('download_summary', $summaries);
        $this->assertStringNotContainsString('cache/summaries', $summaries);

        $actions = (string) file_get_contents($root . '/web/content/actions.php');
        $this->assertStringContainsString('download_email_eml', $actions);
        $this->assertStringNotContainsString('getSummaryCacheWebPath', $actions);
        $this->assertStringContainsString("header('Content-Type: message/rfc822')", $actions);
    }

    public function testAnonymousCgiActionIsDenied(): void
    {
        $phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
        if ($phpCgi === '') {
            $this->markTestSkipped('php-cgi ontbreekt');
        }

        $root = dirname(__DIR__);
        $script = sys_get_temp_dir() . '/clio-odata-cgi-' . bin2hex(random_bytes(4)) . '.php';
        $odataFile = $root . '/web/odata.php';
        file_put_contents($script, "<?php\n"
            . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
            . '$_SERVER["SCRIPT_NAME"] = "/clio/odata.php";' . "\n"
            . '$_SERVER["PHP_SELF"] = "/clio/odata.php";' . "\n"
            . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
            . '$_SERVER["SERVER_ADDR"] = "198.51.100.10";' . "\n"
            . '$_GET["action"] = "cache_status";' . "\n"
            . 'require ' . var_export($odataFile, true) . ";\n");

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($script);
        $stdout = is_string($stdout) ? $stdout : '';
        $stderr = is_string($stderr) ? $stderr : '';
        if ($exitCode !== 0 && strpos($stdout, 'Geen toegang') === false) {
            $this->fail('anonieme cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
        }
        $this->assertStringContainsString('Geen toegang', $stdout);
        $this->assertStringNotContainsString('Customer_Ledger_Entries', $stdout);
    }
}
