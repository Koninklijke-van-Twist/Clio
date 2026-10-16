<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/web/odata.php';
require_once dirname(__DIR__) . '/web/content/action_access.php';

/**
 * odata.php-acties zijn niet anoniem, cache_status lekt geen voorbeeldrijen,
 * en data/ plus cache/ zijn niet via HTTP te lezen.
 */
final class OdataActionAccessTest extends TestCase
{
    public function testLoginGateRunsBeforeCacheActions(): void
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
        $gate = substr($odataSource, $gateStart, $gateEnd - $gateStart);
        $emailAt = strpos($gate, 'odata_email_is_allowed');
        $releaseAt = strpos($gate, 'odata_release_session_lock()');
        $sessionAt = strpos($gate, 'session_start()');
        $requireLibAt = strpos($gate, 'require_once $loginLib');
        $this->assertNotFalse($emailAt);
        $this->assertNotFalse($releaseAt);
        $this->assertNotFalse($sessionAt);
        $this->assertNotFalse($requireLibAt);
        $this->assertLessThan($releaseAt, $emailAt);
        $this->assertLessThan($requireLibAt, $sessionAt);

        $this->assertStringContainsString('php_sapi_name()', $odataSource);
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
        odata_release_session_lock();
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function testAllowListAndRunnerSecret(): void
    {
        $allowed = [
            'analyst@kvt.nl',
            'planner@kvt.nl' => [15, 40],
        ];
        $this->assertTrue(odata_email_is_allowed('Analyst@kvt.nl', $allowed));
        $this->assertTrue(odata_email_is_allowed('planner@kvt.nl', $allowed));
        $this->assertFalse(odata_email_is_allowed('andere@kvt.nl', $allowed));
        $this->assertFalse(odata_email_is_allowed('', $allowed));
        $this->assertFalse(odata_email_is_allowed('analyst@kvt.nl', null));

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
        $this->assertSame('uit-de-header', odata_presented_runner_secret());
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
            if ($previousRunner !== null) {
                $GLOBALS['clioRunnerSecret'] = $previousRunner;
            }
            if ($previousEnv !== false && $previousEnv !== '') {
                putenv('CLIO_RUNNER_SECRET=' . $previousEnv);
            }
            @unlink($authPath);
        }
    }

    public function testCacheStatusOmitsSampleRows(): void
    {
        $cacheDir = cache_base_dir();
        $fixtureName = 'clio-access-test-' . bin2hex(random_bytes(8)) . '.json';
        $fixturePath = $cacheDir . '/' . $fixtureName;
        $customerNo = 'C-SECRET-44021';
        $customerName = 'Voorbeeld Klant Jansen';
        $documentNo = 'VF-998877';
        $sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/Customer_Ledger_Entries";
        $now = time();
        $fixture = [
            '_meta' => [
                'cached_at' => $now,
                'expires_at' => $now + 86400,
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
            $root . '/web/data',
            $root . '/web/data/emails',
            $root . '/web/data/php_errors',
            $root . '/web/data/user_prefs',
            $root . '/web/cache',
            $root . '/web/cache/odata',
            $root . '/web/cache/summaries',
        ];

        foreach ($directories as $directory) {
            $rules = (string) file_get_contents($directory . '/.htaccess');
            $this->assertStringContainsString('Options -Indexes', $rules, $directory);
            $this->assertStringContainsString('Require all denied', $rules, $directory);
            $this->assertStringContainsString('Deny from all', $rules, $directory);
            $this->assertStringContainsString('<IfModule mod_authz_core.c>', $rules, $directory);
            $this->assertStringContainsString('<IfModule !mod_authz_core.c>', $rules, $directory);

            foreach (['index.html', 'index.php'] as $indexName) {
                $indexFile = $directory . '/' . $indexName;
                $this->assertFileExists($indexFile);
                $indexBody = (string) file_get_contents($indexFile);
                $this->assertLessThanOrEqual(16, strlen($indexBody), $indexFile);
                $this->assertStringNotContainsString('Customer', $indexBody);
            }
        }
    }

    public function testFtpDeployUploadsGuardsWithoutReplacingData(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy-ftp.yml');
        $this->assertStringNotContainsString('--delete-excluded', $workflow);

        $markers = [
            '--exclude-glob .htaccess',
            '--exclude-glob cache/**',
            '--exclude-glob cache/odata/**',
            '--exclude-glob cache/summaries/**',
            '--exclude-glob data/**',
            '--exclude-glob data/emails/**',
            '--exclude-glob email-worker/config.json',
            '--exclude-glob auth.php',
            '--include-glob cache/.htaccess',
            '--include-glob cache/index.html',
            '--include-glob cache/index.php',
            '--include-glob cache/odata/.htaccess',
            '--include-glob cache/odata/index.html',
            '--include-glob cache/odata/index.php',
            '--include-glob cache/summaries/.htaccess',
            '--include-glob cache/summaries/index.html',
            '--include-glob cache/summaries/index.php',
            '--include-glob data/.htaccess',
            '--include-glob data/index.html',
            '--include-glob data/index.php',
            '--include-glob data/emails/.htaccess',
            '--include-glob data/emails/index.html',
            '--include-glob data/emails/index.php',
            '--include-glob data/php_errors/.htaccess',
            '--include-glob data/user_prefs/.htaccess',
        ];
        $cursor = 0;
        foreach ($markers as $needle) {
            $at = strpos($workflow, $needle, $cursor);
            $this->assertNotFalse($at, $needle);
            $cursor = $at + strlen($needle);
        }

        $includeAt = strpos($workflow, '--include-glob cache/.htaccess');
        $includeDataAt = strpos($workflow, '--include-glob data/.htaccess');
        $excludeCacheAt = strpos($workflow, '--exclude-glob cache/**');
        $excludeDataAt = strpos($workflow, '--exclude-glob data/**');
        $this->assertGreaterThan($excludeCacheAt, $includeAt);
        $this->assertGreaterThan($excludeDataAt, $includeDataAt);
        $this->assertGreaterThan($excludeDataAt, strpos($workflow, '--include-glob data/emails/.htaccess'));
    }

    public function testEmailsAndSummariesStayBehindPhp(): void
    {
        $root = dirname(__DIR__);
        $emails = (string) file_get_contents($root . '/web/content/views/emails.php');
        $actions = (string) file_get_contents($root . '/web/content/actions.php');
        $data = (string) file_get_contents($root . '/web/content/data.php');

        $this->assertStringContainsString('getEmailArchiveEmlDownloadUrl', $emails);
        $this->assertStringNotContainsString('data/emails/', $emails);
        $this->assertStringContainsString('download_email_eml', $actions);
        $this->assertStringContainsString('clio_require_page_action_access()', $actions);
        $this->assertStringNotContainsString('getSummaryCacheWebPath', $actions);
        $this->assertStringContainsString('download_summary', $data);
        $this->assertStringNotContainsString('getSummaryCacheWebPath', $data);

        $requireAt = strpos($actions, 'clio_require_page_action_access()');
        $firstAction = strpos($actions, "=== 'toggle_favorite'");
        $this->assertNotFalse($requireAt);
        $this->assertNotFalse($firstAction);
        $this->assertLessThan($firstAction, $requireAt);
        $this->assertLessThan($requireAt, strpos($actions, 'clio_script_is_direct_action_endpoint'));
    }

    public function testPageActionsRequireLoginAndOptionalAllowList(): void
    {
        $allowed = [
            'analyst@kvt.nl',
            'planner@kvt.nl' => [15, 40],
        ];
        $this->assertSame('skip', clio_page_action_decision('', 'analyst@kvt.nl', $allowed));
        $this->assertSame('skip', clio_page_action_decision('nope', 'analyst@kvt.nl', null));
        $this->assertSame('deny', clio_page_action_decision('download_email_eml', '', null));
        $this->assertSame('deny', clio_page_action_decision('download_summary', '', $allowed));
        $this->assertSame('allow', clio_page_action_decision('download_email_eml', 'Analyst@kvt.nl', null));
        $this->assertSame('allow', clio_page_action_decision('upload_transcript', 'analyst@kvt.nl', $allowed));
        $this->assertSame('allow', clio_page_action_decision('toggle_favorite', 'planner@kvt.nl', $allowed));
        $this->assertSame('deny', clio_page_action_decision('download_email_eml', 'andere@kvt.nl', $allowed));
        $this->assertTrue(clio_script_is_direct_action_endpoint('/var/www/clio/content/actions.php'));
        $this->assertFalse(clio_script_is_direct_action_endpoint('/var/www/clio/index.php'));

        foreach (['toggle_favorite', 'download_summary', 'download_email_eml', 'upload_transcript'] as $action) {
            $this->assertContains($action, clio_known_page_actions());
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['clio_page_probe'] = 1;
        clio_release_page_session_lock();
        $this->assertNotSame(PHP_SESSION_ACTIVE, session_status());
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function testAnonymousOdataActionIsDenied(): void
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

        $stdout = '';
        $stderr = '';
        $exitCode = 1;
        try {
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
        } finally {
            @unlink($script);
        }

        $stdout = is_string($stdout) ? $stdout : '';
        $stderr = is_string($stderr) ? $stderr : '';
        if ($exitCode !== 0 && strpos($stdout, 'Geen toegang') === false) {
            $this->fail('anonieme cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
        }
        $this->assertStringContainsString('Geen toegang', $stdout);
        $this->assertStringNotContainsString('Customer_Ledger_Entries', $stdout);
    }

    public function testAllowlistedSessionCookieCanRunCacheStatus(): void
    {
        $phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
        if ($phpCgi === '') {
            $this->markTestSkipped('php-cgi ontbreekt');
        }

        $root = dirname(__DIR__);
        $loginDir = $root . '/login';
        $loginLib = $loginDir . '/lib.php';
        if (is_file($loginLib)) {
            $this->markTestSkipped('login/lib.php bestaat al');
        }
        $sessionDir = sys_get_temp_dir() . '/clio-odata-sess-' . bin2hex(random_bytes(4));
        $authPath = sys_get_temp_dir() . '/clio-odata-auth-' . bin2hex(random_bytes(4)) . '.php';
        $script = sys_get_temp_dir() . '/clio-odata-session-' . bin2hex(random_bytes(4)) . '.php';
        $statusPath = sys_get_temp_dir() . '/clio-odata-login-status-' . bin2hex(random_bytes(4)) . '.txt';
        $sessionName = 'PHPSESSID';
        $allowedId = bin2hex(random_bytes(16));
        $otherId = bin2hex(random_bytes(16));
        $odataFile = $root . '/web/odata.php';

        $createdLoginDir = !is_dir($loginDir);
        try {
            if ($createdLoginDir && !mkdir($loginDir, 0700, true) && !is_dir($loginDir)) {
                $this->fail('login-map aanmaken mislukt');
            }
            mkdir($sessionDir, 0700, true);
            file_put_contents($authPath, "<?php\n\$allowedUsers = ['analyst@kvt.nl'];\n");
            file_put_contents($loginLib, "<?php\n"
                . 'file_put_contents(' . var_export($statusPath, true) . ', (string) session_status());' . "\n"
                . 'if (session_status() === PHP_SESSION_ACTIVE) {' . "\n"
                . '    session_write_close();' . "\n"
                . '}' . "\n");
            file_put_contents($script, "<?php\n"
                . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
                . '$_SERVER["SCRIPT_NAME"] = "/clio/odata.php";' . "\n"
                . '$_SERVER["PHP_SELF"] = "/clio/odata.php";' . "\n"
                . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
                . '$_SERVER["SERVER_ADDR"] = "198.51.100.10";' . "\n"
                . '$_GET["action"] = "cache_status";' . "\n"
                . '$GLOBALS["CLIO_AUTH_PHP_PATH"] = ' . var_export($authPath, true) . ";\n"
                . 'require ' . var_export($odataFile, true) . ";\n");

            $this->writeOdataProbeSession($sessionDir, $sessionName, $allowedId, 'analyst@kvt.nl');
            $this->writeOdataProbeSession($sessionDir, $sessionName, $otherId, 'andere@kvt.nl');

            $allowed = $this->runOdataCgiAction($phpCgi, $script, $root, $sessionDir, $sessionName, $allowedId);
            $this->assertSame('2', trim((string) @file_get_contents($statusPath)));
            $this->assertStringNotContainsString('Status: 403', $allowed['stdout']);
            $this->assertIsArray($allowed['json'], $allowed['stdout']);
            $this->assertArrayHasKey('bytes', $allowed['json']);
            $this->assertArrayHasKey('entries', $allowed['json']);
            $this->assertArrayNotHasKey('error', $allowed['json']);

            @unlink($statusPath);
            $other = $this->runOdataCgiAction($phpCgi, $script, $root, $sessionDir, $sessionName, $otherId);
            $this->assertStringContainsString('Status: 403', $other['stdout']);
            $this->assertSame('Geen toegang', $other['json']['error'] ?? null);

            @unlink($statusPath);
            $anonymous = $this->runOdataCgiAction($phpCgi, $script, $root, $sessionDir, $sessionName, '');
            $this->assertStringContainsString('Status: 403', $anonymous['stdout']);
            $this->assertSame('Geen toegang', $anonymous['json']['error'] ?? null);
        } finally {
            @unlink($loginLib);
            if ($createdLoginDir) {
                @rmdir($loginDir);
            }
            @unlink($authPath);
            @unlink($script);
            @unlink($statusPath);
            foreach (glob($sessionDir . '/sess_*') ?: [] as $sessionFile) {
                @unlink($sessionFile);
            }
            @rmdir($sessionDir);
        }
    }

    /**
     * @return array{stdout: string, json: ?array}
     */
    private function runOdataCgiAction(
        string $phpCgi,
        string $script,
        string $root,
        string $sessionDir,
        string $sessionName,
        string $sessionId
    ): array {
        $command = [
            $phpCgi,
            '-d', 'display_errors=1',
            '-d', 'session.auto_start=0',
            '-d', 'session.use_cookies=1',
            '-d', 'session.use_only_cookies=1',
            '-d', 'session.use_strict_mode=0',
            '-d', 'session.serialize_handler=php',
            '-d', 'session.name=' . $sessionName,
            '-d', 'session.save_path=' . $sessionDir,
            $script,
        ];
        $env = [];
        foreach (array_merge($_ENV, getenv() ?: []) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        unset($env['HTTP_COOKIE']);
        if ($sessionId !== '') {
            $env['HTTP_COOKIE'] = $sessionName . '=' . $sessionId;
        }

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $root, $env);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $stdout = is_string($stdout) ? $stdout : '';
        $stderr = is_string($stderr) ? $stderr : '';
        if ($exitCode !== 0 && strpos($stdout, 'Geen toegang') === false) {
            $this->fail('cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
        }

        $body = $stdout;
        $separator = strpos($stdout, "\r\n\r\n");
        if ($separator !== false) {
            $body = substr($stdout, $separator + 4);
        }
        $decoded = json_decode($body, true);

        return [
            'stdout' => $stdout,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    private function writeOdataProbeSession(string $sessionDir, string $sessionName, string $sessionId, string $email): void
    {
        $writer = sys_get_temp_dir() . '/clio-odata-sess-writer-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($writer, "<?php\n"
            . 'session_save_path($argv[1]);' . "\n"
            . 'session_name($argv[2]);' . "\n"
            . 'session_id($argv[3]);' . "\n"
            . 'session_start();' . "\n"
            . '$_SESSION["user"] = ["email" => $argv[4]];' . "\n"
            . 'session_write_close();' . "\n");
        $php = PHP_BINARY;
        $command = escapeshellarg($php)
            . ' -d session.use_strict_mode=0 -d session.serialize_handler=php '
            . escapeshellarg($writer) . ' '
            . escapeshellarg($sessionDir) . ' '
            . escapeshellarg($sessionName) . ' '
            . escapeshellarg($sessionId) . ' '
            . escapeshellarg($email);
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);
        @unlink($writer);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testDirectActionsScriptIsDenied(): void
    {
        $phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
        if ($phpCgi === '') {
            $this->markTestSkipped('php-cgi ontbreekt');
        }

        $root = dirname(__DIR__);
        $actionsFile = $root . '/web/content/actions.php';
        $script = sys_get_temp_dir() . '/clio-actions-cgi-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($script, "<?php\n"
            . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($actionsFile, true) . ";\n"
            . '$_SERVER["SCRIPT_NAME"] = "/clio/content/actions.php";' . "\n"
            . '$_SERVER["PHP_SELF"] = "/clio/content/actions.php";' . "\n"
            . '$_SERVER["REQUEST_METHOD"] = "GET";' . "\n"
            . 'require ' . var_export($actionsFile, true) . ";\n");
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $stdout = '';
        try {
            $process = proc_open(
                [$phpCgi, '-q', '-d', 'display_errors=0', $script],
                $descriptor,
                $pipes,
                $root
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } finally {
            @unlink($script);
        }
        $stdout = is_string($stdout) ? $stdout : '';
        $this->assertStringContainsString('Geen toegang', $stdout);
        $this->assertStringNotContainsString('download_email_eml', $stdout);
    }
}
