<?php
/**
 * Simuleert een onbereikbare Mímir en controleert de directe BC-fallback.
 * Run: php tests/mimir_fallback_test.php
 */

$logFile = sys_get_temp_dir() . '/clio-mimir-fallback-test.log';
@unlink($logFile);
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];

$authPath = dirname(__DIR__) . '/web/auth.php';
register_shutdown_function(static function () use ($authPath): void {
    if (!is_file($authPath)) {
        return;
    }
    $raw = @file_get_contents($authPath);
    if (is_string($raw) && strpos($raw, 'from-auth-file') !== false) {
        @unlink($authPath);
    }
});

$calls = [];
$GLOBALS['CLIO_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    if (preg_match('#/ODataV4/Company(?:\\?|$)#', $url) === 1) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};

require dirname(__DIR__) . '/web/odata.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function fallback_log(): string
{
    global $logFile;
    $raw = @file_get_contents($logFile);
    return is_string($raw) ? $raw : '';
}

function fallback_count(): int
{
    return substr_count(fallback_log(), '[Clio] Mímir failed, falling back to direct OData:');
}

if (odata_mimir_connect_timeout_seconds() !== 10) {
    fail('connect-timeout moet 10s zijn');
}
if (odata_mimir_timeout_seconds_for_sapi('cli') !== 600) {
    fail('CLI-timeout moet 600s blijven');
}
if (odata_mimir_timeout_seconds_for_sapi('fpm-fcgi') !== 90 || odata_mimir_timeout_seconds_for_sapi('apache2handler') !== 90) {
    fail('web-timeout moet ongeveer 90s zijn');
}
if (PHP_SAPI === 'cli' && odata_mimir_timeout_seconds() !== 600) {
    fail('huidige CLI-sapi moet de lange timeout gebruiken');
}

$names = odata_mimir_list_companies(null);
$expectedNames = ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas'];
if ($names !== $expectedNames) {
    fail('company-fallback gaf ' . json_encode($names) . ' i.p.v. de gesorteerde BC-namen');
}
if (!odata_mimir_circuit_open()) {
    fail('circuit moet open na de eerste Mímir-fout');
}
if (count($calls) !== 1 || strpos($calls[0]['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('company-fallback riep de directe BC-fetch niet aan: ' . json_encode($calls));
}
if ($calls[0]['user'] !== 'bcuser') {
    fail('company-fallback gebruikte niet de BC-credentials');
}

$mimirBase = 'http://192.0.2.1:9';
$started = microtime(true);
$rows = odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    120
);
$elapsed = microtime(true) - $started;
if ($elapsed >= 2.0) {
    fail('circuit breaker sloeg Mímir niet over (' . round($elapsed, 3) . 's)');
}
if (($rows[0]['No'] ?? '') !== 'WO-1') {
    fail('entity-fallback gaf niet de gestubde BC-rijen terug');
}
$entityCall = $calls[1] ?? null;
$expectedEntityUrl = "https://bc.example:7148/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($entityCall) || $entityCall['url'] !== $expectedEntityUrl || $entityCall['user'] !== 'bcuser' || $entityCall['ttl'] !== 120) {
    fail('entity-fallback URL/auth/ttl klopt niet: ' . json_encode($entityCall));
}
if (fallback_count() !== 1) {
    fail('alleen de eerste Mímir-fout mag een fallback loggen, log=' . fallback_log());
}
$log = fallback_log();
if (strpos($log, 'mimir_test_key_should_not_leak') !== false || strpos($log, 'bc-secret') !== false) {
    fail('log bevat een geheim');
}
if (strpos($log, '[Clio] Mímir failed, falling back to direct OData:') === false) {
    fail('logregel mist het verwachte prefix');
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeQuery = count($calls);
$queryRows = odata_mimir_query('KVT Gas', 'AppResource', ['$select' => 'No,Name'], 60);
if (($queryRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_query viel niet terug op de stub');
}
$queryCall = $calls[$beforeQuery] ?? null;
if (!is_array($queryCall) || strpos($queryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppResource?") !== 0) {
    fail('query-fallback bouwde niet de pre-Mímir BC-URL: ' . json_encode($queryCall));
}

odata_mimir_circuit_reset();
$beforeFetch = count($calls);
$fetchRows = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No",
    15
);
if (($fetchRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_fetch_all viel niet terug');
}
$fetchCall = $calls[$beforeFetch] ?? null;
if (!is_array($fetchCall) || $fetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No") {
    fail('fetch_all-fallback herschreef de URL niet: ' . json_encode($fetchCall));
}

odata_mimir_circuit_reset();
$map = odata_mimir_company_environment_map(null);
if (($map['Hunter van Twist'] ?? '') !== 'Production' || ($map['KVT Gas'] ?? '') !== 'Production') {
    fail('environment-map viel niet terug op BC: ' . json_encode($map));
}

$auth_list['Sandbox'] = ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret'];
$GLOBALS['clio_company_environment_map']['Hunter van Twist'] = 'Sandbox';
odata_mimir_circuit_reset();
$loggedBeforeSandbox = fallback_count();
$beforeSandbox = count($calls);
$sandboxRows = odata_mimir_query('Hunter van Twist', 'AppResource', ['$select' => 'No'], 30);
if (($sandboxRows[0]['No'] ?? '') !== 'WO-1') {
    fail('query voor een tweede environment viel niet terug');
}
$sandboxCall = $calls[$beforeSandbox] ?? null;
$expectedSandboxPrefix = "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppResource?";
if (!is_array($sandboxCall) || strpos((string) ($sandboxCall['url'] ?? ''), $expectedSandboxPrefix) !== 0 || ($sandboxCall['user'] ?? '') !== 'sandbox-user') {
    fail('tweede environment gebruikte niet $auth_list[Sandbox]: ' . json_encode($sandboxCall));
}
if (count($calls) !== $beforeSandbox + 1) {
    fail('company-map mag geen extra companies-fetch doen: ' . json_encode($calls));
}
if (fallback_count() !== $loggedBeforeSandbox + 1) {
    fail('de eerste Mímir-fout van deze query moet precies één keer gelogd worden');
}

$loggedBeforeSegment = fallback_count();
$segmentRows = odata_get_all(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    30
);
$segmentCall = $calls[count($calls) - 1] ?? null;
$expectedSandboxEntity = "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No";
if (($segmentRows[0]['No'] ?? '') !== 'WO-1' || !is_array($segmentCall) || ($segmentCall['url'] ?? '') !== $expectedSandboxEntity || ($segmentCall['user'] ?? '') !== 'sandbox-user') {
    fail('URL-segment Sandbox moet winnen van het primaire environment: ' . json_encode($segmentCall));
}
if (fallback_count() !== $loggedBeforeSegment) {
    fail('een open circuit mag niet opnieuw een fallback loggen');
}

$placeholderRows = odata_get_all(
    "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    30
);
$placeholderCall = $calls[count($calls) - 1] ?? null;
if (($placeholderRows[0]['No'] ?? '') !== 'WO-1' || !is_array($placeholderCall) || ($placeholderCall['url'] ?? '') !== $expectedSandboxEntity || ($placeholderCall['user'] ?? '') !== 'sandbox-user') {
    fail('placeholder-segment mimir moet de company-map gebruiken: ' . json_encode($placeholderCall));
}

$spaced = odata_bc_url_from_odata_url("https://mimir.invalid/My%20Env/ODataV4/Company('X')/Projecten?\$select=No");
if ($spaced !== "https://bc.example:7148/My%20Env/ODataV4/Company('X')/Projecten?\$select=No") {
    fail('environment-segment werd dubbel geëncodeerd: ' . $spaced);
}

odata_mimir_circuit_reset();
$loggedBeforeCaller = fallback_count();
$callerError = null;
try {
    odata_mimir_or_direct(
        static function (): array {
            throw new Exception('caller boom');
        },
        static function (): array {
            return [['No' => 'should-not-run']];
        }
    );
    fail('een caller-exception moet doorgaan');
} catch (Throwable $exception) {
    $callerError = $exception;
}
if (!$callerError instanceof Throwable || $callerError->getMessage() !== 'caller boom') {
    fail('caller-exception kwam niet ongewijzigd terug');
}
if (odata_mimir_circuit_open()) {
    fail('een caller-exception mag het circuit niet openen');
}
if (fallback_count() !== $loggedBeforeCaller) {
    fail('een caller-exception mag geen fallback loggen');
}

$savedEnvironment = $environment;
$environment = 'mimir';
$cacheKey = build_cache_key('https://bc.example:7148/Production/ODataV4/Company', $auth);
$environment = $savedEnvironment;
if (strpos($cacheKey, '|Production') === false || strpos($cacheKey, '|mimir') !== false) {
    fail('cache-key moet het environment uit de URL gebruiken: ' . $cacheKey);
}
$placeholderKey = build_cache_key(
    "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders",
    $auth
);
if (strpos($placeholderKey, '|Sandbox') === false || strpos($placeholderKey, '|mimir') !== false) {
    fail('cache-key van een placeholder-URL moet het company-environment gebruiken: ' . $placeholderKey);
}
if (strpos(fallback_log(), 'sandbox-secret') !== false) {
    fail('log bevat het sandbox-wachtwoord');
}

$loggedBeforeRethrow = fallback_count();
$callsBeforeRethrow = count($calls);
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
$rethrown = null;
try {
    odata_get_all('https://mimir.invalid/mimir/ODataV4/Company(\'X\')/AppWerkorders', ['mode' => 'basic', 'user' => '', 'pass' => ''], 30);
    fail('zonder BC-credentials moet de oorspronkelijke Mímir-fout terugkomen');
} catch (Throwable $exception) {
    $rethrown = $exception;
}
if (!$rethrown instanceof Throwable) {
    fail('geen exception gevangen');
}
if (strpos($rethrown->getMessage(), 'Mímir') === false) {
    fail('hergooide fout is niet de Mímir-fout: ' . $rethrown->getMessage());
}
if (stripos($rethrown->getMessage(), 'credential') !== false) {
    fail('hergooide fout maskeert Mímir met een credentials-melding: ' . $rethrown->getMessage());
}
if (count($calls) !== $callsBeforeRethrow) {
    fail('zonder BC-credentials mag de directe fetch niet starten');
}
if (fallback_count() !== $loggedBeforeRethrow) {
    fail('zonder BC-credentials mag er geen fallback gelogd worden');
}

odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];
$loggedBeforeDirect = fallback_count();
$directOnlyUrl = 'https://mimir.invalid/Production/ODataV4/Company(\'KVT%20Gas\')/AppWerkorders?$select=No';
$directRows = odata_get_all($directOnlyUrl, $auth, 45);
if (odata_mimir_circuit_open()) {
    fail('lege $mimirApi mag Mímir niet proberen');
}
if (fallback_count() !== $loggedBeforeDirect) {
    fail('lege $mimirApi mag geen Mímir-fallback loggen');
}
$directCall = $calls[count($calls) - 1] ?? null;
if (($directRows[0]['No'] ?? '') !== 'WO-1' || !is_array($directCall) || $directCall['url'] !== $directOnlyUrl) {
    fail('lege $mimirApi moet de oude directe route ongewijzigd gebruiken: ' . json_encode($directCall));
}

odata_mimir_circuit_reset();
$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
unset($baseUrl, $environment, $auth, $auth_list);
file_put_contents(
    $authPath,
    "<?php\n"
    . "\$baseUrl = 'https://bc.example:7148/';\n"
    . "\$environment = 'Production';\n"
    . "\$auth = ['mode' => 'basic', 'user' => 'from-auth-file', 'pass' => 'file-secret'];\n"
    . "\$auth_list = ['Production' => \$auth];\n"
);
$beforeLazy = count($calls);
$lazyNames = odata_mimir_list_companies(null);
@unlink($authPath);
if ($lazyNames !== $expectedNames) {
    fail('lazy auth.php-fallback gaf ' . json_encode($lazyNames));
}
$lazyCall = $calls[$beforeLazy] ?? null;
if (!is_array($lazyCall) || ($lazyCall['user'] ?? '') !== 'from-auth-file' || strpos((string) ($lazyCall['url'] ?? ''), 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('fallback las de BC-credentials niet uit auth.php: ' . json_encode($lazyCall));
}
if (strpos(fallback_log(), 'file-secret') !== false) {
    fail('log bevat het wachtwoord uit auth.php');
}

$authCopyPath = sys_get_temp_dir() . '/clio-auth-globals-copy.php';
register_shutdown_function(static function () use ($authCopyPath): void {
    if (is_file($authCopyPath)) {
        @unlink($authCopyPath);
    }
});
file_put_contents(
    $authCopyPath,
    "<?php\n"
    . "\$baseUrl = 'https://loaded-bc.example:7148/';\n"
    . "\$environment = 'LoadedEnv';\n"
    . "\$auth = ['mode' => 'basic', 'user' => 'loaded-user', 'pass' => 'loaded-secret'];\n"
    . "\$auth_list = ['LoadedEnv' => \$auth];\n"
);
$GLOBALS['CLIO_AUTH_PHP_PATH'] = $authCopyPath;
unset($GLOBALS['CLIO_BC_AUTH_LOAD_TRIED']);
$GLOBALS['baseUrl'] = 'https://mimir.invalid/';
$GLOBALS['environment'] = 'mimir';
$GLOBALS['auth'] = [];
$GLOBALS['auth_list'] = [];
odata_bc_ensure_config_loaded();
if (($GLOBALS['baseUrl'] ?? '') !== 'https://loaded-bc.example:7148/') {
    fail('baseUrl-placeholder werd niet uit auth.php naar $GLOBALS gekopieerd');
}
if (($GLOBALS['environment'] ?? '') !== 'LoadedEnv') {
    fail('environment werd niet naar $GLOBALS gekopieerd');
}
if (($GLOBALS['auth']['user'] ?? '') !== 'loaded-user' || ($GLOBALS['auth_list']['LoadedEnv']['user'] ?? '') !== 'loaded-user') {
    fail('auth en auth_list werden niet naar $GLOBALS gekopieerd');
}
require_once $authCopyPath;
if (($GLOBALS['baseUrl'] ?? '') !== 'https://loaded-bc.example:7148/' || ($GLOBALS['environment'] ?? '') !== 'LoadedEnv') {
    fail('een tweede require_once van auth.php mocht de gekopieerde globals niet wissen');
}

$authKeepPath = sys_get_temp_dir() . '/clio-auth-globals-keep.php';
register_shutdown_function(static function () use ($authKeepPath): void {
    if (is_file($authKeepPath)) {
        @unlink($authKeepPath);
    }
});
file_put_contents(
    $authKeepPath,
    "<?php\n"
    . "\$baseUrl = 'https://other-bc.example:7148/';\n"
    . "\$environment = 'LoadedEnv';\n"
    . "\$auth = ['mode' => 'basic', 'user' => 'loaded-user', 'pass' => 'loaded-secret'];\n"
    . "\$auth_list = ['LoadedEnv' => \$auth];\n"
);
unset($GLOBALS['CLIO_BC_AUTH_LOAD_TRIED']);
$GLOBALS['CLIO_AUTH_PHP_PATH'] = $authKeepPath;
$GLOBALS['baseUrl'] = 'https://keep.example:7148/';
unset($GLOBALS['environment'], $GLOBALS['auth'], $GLOBALS['auth_list']);
odata_bc_ensure_config_loaded();
if (($GLOBALS['baseUrl'] ?? '') !== 'https://keep.example:7148/') {
    fail('een al gezette baseUrl mag niet overschreven worden');
}
if (($GLOBALS['environment'] ?? '') !== 'LoadedEnv' || ($GLOBALS['auth_list']['LoadedEnv']['user'] ?? '') !== 'loaded-user') {
    fail('ontbrekende environment/auth_list moeten alsnog gekopieerd worden');
}
unset($GLOBALS['CLIO_AUTH_PHP_PATH']);
@unlink($authCopyPath);
@unlink($authKeepPath);
if (strpos(fallback_log(), 'loaded-secret') !== false) {
    fail('log bevat het wachtwoord uit de gekopieerde auth.php');
}

echo "OK\n";
