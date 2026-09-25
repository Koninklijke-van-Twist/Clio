<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/web/odata.php';

/**
 * OData-routing: Mímir als $mimirApi gezet is, BC als de key ontbreekt.
 */
final class MimirOdataRoutingTest extends TestCase
{
    private static $server = null;

    /** @var array<int, resource> */
    private static array $pipes = [];

    private static int $port = 0;

    private static string $mockScript = '';

    private static string $mockLog = '';

    private static string $authPath = '';

    private static ?string $authBackup = null;

    /** @var list<string> */
    private static array $cacheBefore = [];

    public static function setUpBeforeClass(): void
    {
        self::$authPath = dirname(__DIR__) . '/web/auth.php';
        if (is_file(self::$authPath)) {
            $raw = file_get_contents(self::$authPath);
            self::$authBackup = $raw === false ? '' : $raw;
        }

        self::$mockLog = sys_get_temp_dir() . '/clio-mimir-mock.log';
        self::$mockScript = sys_get_temp_dir() . '/clio-mimir-mock.php';
        self::writeMock();
        @unlink(self::$mockLog);

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException('Geen vrije poort: ' . $errstr);
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        self::$port = (int) substr($name, (int) strrpos($name, ':') + 1);

        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, self::$mockScript],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            self::$pipes,
            sys_get_temp_dir()
        );
        if (!is_resource(self::$server)) {
            throw new RuntimeException('Mock-server start mislukt.');
        }

        $ready = false;
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);
            if (is_resource($fp)) {
                fclose($fp);
                $ready = true;
                break;
            }
            usleep(50000);
        }
        if (!$ready) {
            throw new RuntimeException('Mock-server werd niet bereikbaar.');
        }

        self::$cacheBefore = self::cacheFiles();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            foreach (self::$pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close(self::$server);
        }

        if (self::$authBackup === null) {
            if (is_file(self::$authPath)) {
                @unlink(self::$authPath);
            }
        } else {
            file_put_contents(self::$authPath, self::$authBackup);
        }

        @unlink(self::$mockScript);
        @unlink(self::$mockLog);
        self::removeNewCacheFiles();
    }

    protected function setUp(): void
    {
        unset(
            $GLOBALS['mimirApi'],
            $GLOBALS['mimirBase'],
            $GLOBALS['auth_list'],
            $GLOBALS['environment'],
            $GLOBALS['auth'],
            $GLOBALS['baseUrl']
        );
        @unlink(self::$mockLog);
    }

    public function testMimirUitZonderKey(): void
    {
        $this->assertFalse(odata_mimir_enabled());
        $this->assertSame('https://sleutels.kvt.nl/mimir/api', odata_mimir_base_url());

        $GLOBALS['mimirApi'] = '   ';
        $GLOBALS['mimirBase'] = '   ';
        $this->assertFalse(odata_mimir_enabled());
        $this->assertSame('https://sleutels.kvt.nl/mimir/api', odata_mimir_base_url());
    }

    public function testEntityEnCompaniesUrls(): void
    {
        $spaceUrl = self::entityUrl(
            'https://bc.example',
            'Production',
            'Koninklijke van Twist',
            'Projecten',
            [
                '$select' => 'No,Description',
                '$filter' => "No eq 'PRJ1'",
            ]
        );
        $parsedSpace = odata_mimir_parse_entity_url($spaceUrl);
        $this->assertSame('Koninklijke van Twist', $parsedSpace['company'] ?? null);
        $this->assertSame('Projecten', $parsedSpace['entity'] ?? null);
        $this->assertSame('No,Description', $parsedSpace['query']['$select'] ?? null);
        $this->assertSame("No eq 'PRJ1'", $parsedSpace['query']['$filter'] ?? null);
        $this->assertNull(odata_mimir_parse_companies_url($spaceUrl));

        $apostropheUrl = self::entityUrl(
            '',
            'Production',
            "Van Twist's",
            'JobLedgerEntries',
            ['$select' => 'Job_No,Total_Cost_LCY']
        );
        $parsedApostrophe = odata_mimir_parse_entity_url($apostropheUrl);
        $this->assertSame("Van Twist's", $parsedApostrophe['company'] ?? null);
        $this->assertSame('JobLedgerEntries', $parsedApostrophe['entity'] ?? null);
        $this->assertSame('Job_No,Total_Cost_LCY', $parsedApostrophe['query']['$select'] ?? null);

        $companiesUrl = 'https://bc.example/Production/ODataV4/Companies?$select=Name';
        $parsedCompanies = odata_mimir_parse_companies_url($companiesUrl);
        $this->assertSame('Production', $parsedCompanies['environment'] ?? null);
        $this->assertNull(odata_mimir_parse_entity_url($companiesUrl));

        $companySingular = 'https://bc.example/Sandbox/ODataV4/Company?$select=Name';
        $parsedSingular = odata_mimir_parse_companies_url($companySingular);
        $this->assertSame('Sandbox', $parsedSingular['environment'] ?? null);
    }

    public function testMimirReadsZonderBcConfig(): void
    {
        $this->poisonAuth();
        $GLOBALS['mimirApi'] = 'mimir_test_key';
        $GLOBALS['mimirBase'] = 'http://127.0.0.1:' . self::$port . '/mimir/api';

        $before = self::cacheFiles();
        $rows = odata_get_all(
            self::entityUrl(
                'http://127.0.0.1:' . self::$port,
                'Production',
                'Koninklijke van Twist',
                'Projecten',
                [
                    '$select' => 'No,Description',
                    '$filter' => "No eq 'PRJ1'",
                ]
            ),
            [],
            60
        );

        $this->assertSame('Koninklijke van Twist', $rows[0]['company'] ?? null);
        $this->assertSame('Projecten', $rows[0]['table'] ?? null);
        $this->assertSame("No eq 'PRJ1'", $rows[0]['filter'] ?? null);
        $this->assertSame(60, $rows[0]['max_age'] ?? null);
        $this->assertContains('No', $rows[0]['select'] ?? []);
        $this->assertContains('Description', $rows[0]['select'] ?? []);
        $this->assertSame($before, self::cacheFiles());

        $companyRows = odata_get_all(
            'http://127.0.0.1:' . self::$port . '/Sandbox/ODataV4/Company?$select=Name',
            [],
            30
        );
        $names = array_map(static function (array $row): string {
            return (string) ($row['Name'] ?? '');
        }, $companyRows);
        $this->assertSame(['Hunter van Twist'], $names);

        $listed = odata_mimir_list_companies(null);
        $this->assertSame(
            ['Hunter van Twist', 'Koninklijke van Twist', "Van Twist's"],
            $listed
        );
        $map = odata_mimir_company_environment_map(null);
        $this->assertSame('Production', $map['Koninklijke van Twist'] ?? null);
        $this->assertSame('Sandbox', $map['Hunter van Twist'] ?? null);

        $zeroTtl = odata_get_all(
            self::entityUrl('http://127.0.0.1:' . self::$port, 'Production', "Van Twist's", 'JobLedgerEntries', []),
            [],
            0
        );
        $this->assertSame("Van Twist's", $zeroTtl[0]['company'] ?? null);
        $this->assertSame(3600, $zeroTtl[0]['max_age'] ?? null);

        $requests = self::mockRequests();
        $hitBc = false;
        $sawMimir = false;
        foreach ($requests as $request) {
            $uri = (string) ($request['uri'] ?? '');
            if (str_contains($uri, '/ODataV4/')) {
                $hitBc = true;
            }
            if (
                ($request['ua'] ?? '') === 'Clio-MimirClient/1.0'
                && str_contains($uri, '/mimir/api/')
                && ($request['api_key'] ?? '') === 'mimir_test_key'
                && str_contains((string) ($request['authorization'] ?? ''), 'Bearer mimir_test_key')
            ) {
                $sawMimir = true;
            }
        }
        $this->assertFalse($hitBc);
        $this->assertTrue($sawMimir);
        $this->assertSame($before, self::cacheFiles());
    }

    public function testBcBlijftWerkenZonderKey(): void
    {
        $base = 'http://127.0.0.1:' . self::$port;
        $authList = [
            'Production' => [
                'mode' => 'basic',
                'user' => 'bcuser',
                'pass' => 'bcpass',
            ],
        ];
        file_put_contents(
            self::$authPath,
            "<?php\n\$baseUrl = " . var_export($base, true) . ";\n\$environment = 'Production';\n\$auth_list = " . var_export($authList, true) . ";\n\$mimirApi = '';\n"
        );
        $GLOBALS['mimirApi'] = '';
        $GLOBALS['environment'] = 'Production';
        $GLOBALS['baseUrl'] = $base;

        $before = self::cacheFiles();
        $rows = odata_get_all(
            $base . '/Production/ODataV4/Companies?$select=Name',
            $authList['Production'],
            30
        );

        $this->assertSame('bc', $rows[0]['via'] ?? null);
        $this->assertSame('bcuser', $rows[0]['user'] ?? null);
        $this->assertNotSame($before, self::cacheFiles());

        $hitMimir = false;
        $sawBcUser = false;
        foreach (self::mockRequests() as $request) {
            if (str_contains((string) ($request['uri'] ?? ''), '/mimir/')) {
                $hitMimir = true;
            }
            if (
                str_contains((string) ($request['uri'] ?? ''), '/ODataV4/Companies')
                && ($request['php_auth_user'] ?? '') === 'bcuser'
                && str_contains((string) ($request['ua'] ?? ''), 'Demeter-ODataClient/1.0')
            ) {
                $sawBcUser = true;
            }
        }
        $this->assertFalse($hitMimir);
        $this->assertTrue($sawBcUser);

        self::removeNewCacheFiles();
    }

    private function poisonAuth(): void
    {
        file_put_contents(
            self::$authPath,
            "<?php\nthrow new RuntimeException('auth.php mag niet geladen worden voor Mímir-reads');\n"
        );
    }

    /**
     * @param array<string, string> $query
     */
    private static function entityUrl(string $baseUrl, string $environment, string $company, string $entitySet, array $query): string
    {
        $safeCompany = str_replace("'", "''", trim($company));
        $url = rtrim($baseUrl, '/') . '/' . rawurlencode($environment) . '/ODataV4/Company(\'' . rawurlencode($safeCompany) . '\')/' . rawurlencode($entitySet);
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    private static function writeMock(): void
    {
        $log = var_export(self::$mockLog, true);
        $php = <<<'PHP'
<?php
$log = LOG_PATH;
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
file_put_contents($log, json_encode([
    'uri' => $uri,
    'method' => $method,
    'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'authorization' => $authorization,
    'api_key' => (string) ($_SERVER['HTTP_X_API_KEY'] ?? ''),
    'php_auth_user' => (string) ($_SERVER['PHP_AUTH_USER'] ?? ''),
], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
header('Content-Type: application/json');

if (str_contains($uri, '/mimir/api/companies.php')) {
    echo json_encode(['value' => [
        ['name' => 'Koninklijke van Twist', 'environment' => 'Production'],
        ['name' => "Van Twist's", 'environment' => 'Production'],
        ['name' => 'Hunter van Twist', 'environment' => 'Sandbox'],
    ]]);
    return;
}

if (str_contains($uri, '/mimir/api/query.php')) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = [];
    }
    echo json_encode(['value' => [[
        'No' => 'PRJ1',
        'company' => (string) ($body['company'] ?? ''),
        'table' => (string) ($body['table'] ?? ''),
        'select' => $body['select'] ?? [],
        'filter' => (string) ($body['filter'] ?? ''),
        'max_age' => $body['max_age'] ?? null,
    ]]]);
    return;
}

echo json_encode(['value' => [[
    'Name' => 'BC Company',
    'via' => 'bc',
    'user' => (string) ($_SERVER['PHP_AUTH_USER'] ?? ''),
]]]);
PHP;
        file_put_contents(self::$mockScript, str_replace('LOG_PATH', $log, $php));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mockRequests(): array
    {
        if (!is_file(self::$mockLog)) {
            return [];
        }
        $rows = [];
        foreach (file(self::$mockLog, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function cacheFiles(): array
    {
        $dir = dirname(__DIR__) . '/web/cache/odata';
        $files = glob($dir . '/*.json') ?: [];
        sort($files);

        return $files;
    }

    private static function removeNewCacheFiles(): void
    {
        $known = array_fill_keys(self::$cacheBefore, true);
        foreach (self::cacheFiles() as $file) {
            if (!isset($known[$file])) {
                @unlink($file);
            }
        }
    }
}
