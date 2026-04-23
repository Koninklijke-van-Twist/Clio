<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $GLOBALS['apiKeys'] = [];
        unset($GLOBALS['sharepointTokenProvider']);
        putenv('SHAREPOINT_SITE_ID');
        putenv('SHAREPOINT_DRIVE_ID');
        putenv('SHAREPOINT_LIST_ID');
        putenv('SHAREPOINT_UPLOAD_FOLDER');
        putenv('SHAREPOINT_STATUS_FIELD');
        putenv('SHAREPOINT_ACCESS_TOKEN');
        putenv('SHAREPOINT_TENANT_ID');
        putenv('SHAREPOINT_CLIENT_ID');
        putenv('SHAREPOINT_CLIENT_SECRET');
        putenv('SHAREPOINT_TOKEN_SCOPE');
        putenv('SHAREPOINT_TOKEN_URL');
    }

    public function testAppUrlBuildsPathAndQuery(): void
    {
        $result = appUrl('index.php', ['page' => 'summaries', 'summary_id' => 'abc']);
        $this->assertSame('index.php?page=summaries&summary_id=abc', $result);
    }

    public function testCsrfTokenGenerationAndValidation(): void
    {
        $token = getCsrfToken();

        $this->assertSame(64, strlen($token));
        $this->assertTrue(isValidCsrf($token));
        $this->assertFalse(isValidCsrf('invalid-token'));
    }

    public function testSanitizeTitleToFilenameRemovesUnsafeCharacters(): void
    {
        $sanitized = sanitizeTitleToFilename('  Team / Meeting:* Q2 2026  ');

        $this->assertSame('Team  Meeting Q2 2026', $sanitized);
    }

    public function testParseTxtFileExtractsTitleAndBody(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'clio_txt_');
        file_put_contents($tmpFile, "Weekly meeting\nLine 2\nLine 3");

        $parsed = parseTxtFile($tmpFile);

        unlink($tmpFile);

        $this->assertSame('Weekly meeting', $parsed['title']);
        $this->assertSame("Weekly meeting\nLine 2\nLine 3", $parsed['content']);
    }

    public function testParseTxtFileThrowsForEmptyInput(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'clio_txt_');
        file_put_contents($tmpFile, "   \n\r\n\t  ");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(LOC('upload.error.empty_content'));

        try {
            parseTxtFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseActionPointsByPersonGroupsEntries(): void
    {
        $text = implode("\n", [
            'Milan: Werk planning uit',
            '- Milan - Plan review met team',
            'Tom: Stuur samenvatting rond',
            'Random regel zonder actie',
        ]);

        $result = parseActionPointsByPerson($text);

        $this->assertSame([
            'Milan' => ['Werk planning uit', 'Plan review met team'],
            'Tom' => ['Stuur samenvatting rond'],
        ], $result);
    }

    public function testValidateApiKeyUsesAuthKeys(): void
    {
        $GLOBALS['apiKeys'] = [
            'service-a' => '1234-5678',
            'service-b' => 'ABCD-EFGH',
        ];

        $this->assertTrue(validateApiKey('1234-5678'));
        $this->assertTrue(validateApiKey('ABCD-EFGH'));
        $this->assertFalse(validateApiKey('WRONG-KEY'));
        $this->assertFalse(validateApiKey(''));
    }

    public function testGetSharePointConfigUsesGlobalSettingsThenDefaults(): void
    {
        $GLOBALS['sharepointSettings'] = [
            'site_id' => 'site-1',
            'drive_id' => 'drive-1',
            'list_id' => 'list-1',
            'upload_folder' => 'Uploads',
            'status_field' => 'TranscriptType',
            'access_token' => 'token-1',
            'tenant_id' => 'tenant-1',
            'client_id' => 'client-1',
            'client_secret' => 'secret-1',
            'token_scope' => 'scope-1',
            'token_url' => 'https://example.test/token',
        ];

        $config = getSharePointConfig();

        $this->assertSame('site-1', $config['site_id']);
        $this->assertSame('drive-1', $config['drive_id']);
        $this->assertSame('list-1', $config['list_id']);
        $this->assertSame('Uploads', $config['upload_folder']);
        $this->assertSame('TranscriptType', $config['status_field']);
        $this->assertSame('token-1', $config['access_token']);
        $this->assertSame('tenant-1', $config['tenant_id']);
        $this->assertSame('client-1', $config['client_id']);
        $this->assertSame('secret-1', $config['client_secret']);
        $this->assertSame('scope-1', $config['token_scope']);
        $this->assertSame('https://example.test/token', $config['token_url']);
    }

    public function testGetSharePointConfigKeepsEmptyUploadFolderForRoot(): void
    {
        $GLOBALS['sharepointSettings'] = [
            'site_id' => 'site-1',
            'drive_id' => 'drive-1',
            'list_id' => 'list-1',
            'upload_folder' => '',
            'status_field' => 'TranscriptType',
            'access_token' => 'token-1',
        ];

        $config = getSharePointConfig();

        $this->assertSame('', $config['upload_folder']);
    }

    public function testGetSharePointAccessTokenPrefersStaticToken(): void
    {
        $config = [
            'access_token' => 'static-token',
            'tenant_id' => '',
            'client_id' => '',
            'client_secret' => '',
        ];

        $this->assertSame('static-token', getSharePointAccessToken($config));
    }

    public function testGetSharePointAccessTokenUsesTokenProviderWhenConfigured(): void
    {
        $GLOBALS['sharepointTokenProvider'] = function (array $config): array {
            return [
                'access_token' => 'provider-token-' . $config['client_id'],
                'expires_at' => time() + 3600,
            ];
        };

        $config = [
            'access_token' => '',
            'tenant_id' => 'tenant-provider',
            'client_id' => 'client-provider',
            'client_secret' => 'secret-provider',
            'token_scope' => SHAREPOINT_DEFAULT_TOKEN_SCOPE,
            'token_url' => '',
        ];

        $this->assertSame('provider-token-client-provider', getSharePointAccessToken($config));
    }

    public function testGetGraphErrorMessageExtractsMessageFromGraphBody(): void
    {
        $body = '{"error":{"code":"generalException","message":"General exception while processing"}}';

        $this->assertSame('General exception while processing', getGraphErrorMessage($body));
    }

    public function testGetGraphErrorMessageFallsBackForNonJsonBody(): void
    {
        $this->assertSame(LOC('error.unexpected'), getGraphErrorMessage(''));
    }

    public function testHasMeetingSummaryStatusForStringAndArray(): void
    {
        $this->assertTrue(hasMeetingSummaryStatus('Meeting Samenvatting'));
        $this->assertTrue(hasMeetingSummaryStatus(['Onverwerkt Transcript', 'Meeting Samenvatting']));
        $this->assertTrue(hasMeetingSummaryStatus(['Value' => 'Meeting Samenvatting']));
        $this->assertFalse(hasMeetingSummaryStatus('Onverwerkt Transcript'));
        $this->assertFalse(hasMeetingSummaryStatus(null));
    }

    public function testHasUnprocessedTranscriptStatusForStringAndArray(): void
    {
        $this->assertTrue(hasUnprocessedTranscriptStatus('Onverwerkt Transcript'));
        $this->assertTrue(hasUnprocessedTranscriptStatus(['Onverwerkt Transcript', 'Meeting Samenvatting']));
        $this->assertTrue(hasUnprocessedTranscriptStatus(['Value' => 'Onverwerkt Transcript']));
        $this->assertFalse(hasUnprocessedTranscriptStatus('Meeting Samenvatting'));
        $this->assertFalse(hasUnprocessedTranscriptStatus(null));
    }

    public function testToBoolParsesCommonValues(): void
    {
        $this->assertTrue(toBool('true', false));
        $this->assertFalse(toBool('false', true));
        $this->assertTrue(toBool(1, false));
        $this->assertFalse(toBool(0, true));
        $this->assertTrue(toBool('unexpected', true));
    }

    public function testValidateGraphTokenClaimsThrowsWhenNoScopesOrRoles(): void
    {
        $payload = base64_encode(json_encode([
            'aud' => 'https://graph.microsoft.com',
            'roles' => [],
            'scp' => '',
        ]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $token = 'aaa.' . $payload . '.bbb';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(LOC('error.sharepoint_permissions'));

        validateGraphTokenClaims($token);
    }

    public function testValidateGraphTokenClaimsAcceptsApplicationRole(): void
    {
        $payload = base64_encode(json_encode([
            'aud' => 'https://graph.microsoft.com',
            'roles' => ['Sites.ReadWrite.All'],
            'scp' => '',
        ]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $token = 'aaa.' . $payload . '.bbb';

        $this->assertNull(validateGraphTokenClaims($token));
    }

    public function testSortSummariesByCreatedDescOrdersNewestFirst(): void
    {
        $items = [
            ['name' => 'old', 'created_at' => '2025-01-01T10:00:00Z'],
            ['name' => 'newest', 'created_at' => '2026-03-01T09:00:00Z'],
            ['name' => 'middle', 'created_at' => '2025-12-15T08:00:00Z'],
        ];

        $sorted = sortSummariesByCreatedDesc($items);

        $this->assertSame('newest', $sorted[0]['name']);
        $this->assertSame('middle', $sorted[1]['name']);
        $this->assertSame('old', $sorted[2]['name']);
    }

    public function testIsCacheFileFreshChecksTtl(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'clio_cache_');
        file_put_contents($tmp, 'cached');

        $this->assertTrue(isCacheFileFresh($tmp, 3600));
        $this->assertFalse(isCacheFileFresh($tmp, -1));

        unlink($tmp);
    }

    public function testSummaryDownloadFilenameReturnsMarkdownExtension(): void
    {
        $this->assertSame('Samenvatting_test.md', summaryDownloadFilename('Samenvatting_test.txt', 'abc'));
        $this->assertSame('summary-abc.md', summaryDownloadFilename('', 'abc'));
    }

    public function testSummaryCacheWebPathUsesSanitizedId(): void
    {
        $this->assertSame('cache/summaries/a_b_c.md', getSummaryCacheWebPath('a/b:c'));
    }

    public function testSummaryCacheTtlDefaultsToOneWeek(): void
    {
        $this->assertSame(604800, getSummaryCacheTtlSeconds());
    }

    public function testPruneSummaryCacheFilesRemovesOldEntries(): void
    {
        $cacheDir = getSummaryCacheDir();
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        $oldFile = $cacheDir . '/__old_test__.md';
        $newFile = $cacheDir . '/__new_test__.md';
        file_put_contents($oldFile, 'old');
        file_put_contents($newFile, 'new');
        touch($oldFile, time() - 7200);
        touch($newFile, time());

        pruneSummaryCacheFiles(3600);

        $this->assertFalse(is_file($oldFile));
        $this->assertTrue(is_file($newFile));

        if (is_file($newFile)) {
            unlink($newFile);
        }
    }

    public function testEstimateUnprocessedSummaryEtaReturnsMinutesUntilNextCycle(): void
    {
        $_SESSION['lang'] = 'nl';

        $items = [
            [
                'is_openable' => true,
                'created_at' => '2026-04-23T11:20:00Z',
            ],
        ];

        $eta = estimateUnprocessedSummaryEta($items, '2026-04-23T12:05:00Z', strtotime('2026-04-23T12:10:00Z'));

        $this->assertSame('ongeveer 10 minuten', $eta);
    }

    public function testEstimateUnprocessedSummaryEtaReturnsLessThanMinuteWhenCyclePassed(): void
    {
        $_SESSION['lang'] = 'nl';

        $items = [
            [
                'is_openable' => true,
                'created_at' => '2026-04-23T11:20:00Z',
            ],
        ];

        $eta = estimateUnprocessedSummaryEta($items, '2026-04-23T12:05:00Z', strtotime('2026-04-23T12:21:00Z'));

        $this->assertSame('minder dan een minuut', $eta);
    }

    public function testAddEtaToUnprocessedSummariesAddsEtaOnlyToPendingItems(): void
    {
        $_SESSION['lang'] = 'nl';

        $items = [
            [
                'is_openable' => true,
                'created_at' => '2026-04-23T11:20:00Z',
            ],
            [
                'is_openable' => false,
                'created_at' => '2026-04-23T12:05:00Z',
            ],
        ];

        $withEta = addEtaToUnprocessedSummaries($items, strtotime('2026-04-23T12:10:00Z'));

        $this->assertSame('', $withEta[0]['eta_text']);
        $this->assertSame('ongeveer 10 minuten', $withEta[1]['eta_text']);
    }

    public function testBuildUploadFilenameForUserPrefixesEmailAndKeepsOriginalName(): void
    {
        $result = buildUploadFilenameForUser('Teamoverleg april.docx', 'Iemand.Test@KVT.NL');

        $this->assertSame('iemand.test@kvt.nl_Teamoverleg april.docx', $result);
    }

    public function testExtractFirstEmailFromTextFindsOwnerInSummaryName(): void
    {
        $name = 'Samenvatting _iemand.test@kvt.nl_teamoverleg april.docx';

        $this->assertSame('iemand.test@kvt.nl', extractFirstEmailFromText($name));
    }

    public function testSortSummariesByFavoriteThenCreatedDescPrioritizesFavorites(): void
    {
        $items = [
            [
                'name' => 'nieuw niet-favoriet',
                'is_favorite' => false,
                'created_at' => '2026-04-23T12:00:00Z',
            ],
            [
                'name' => 'oud favoriet',
                'is_favorite' => true,
                'created_at' => '2026-04-20T10:00:00Z',
            ],
            [
                'name' => 'nieuw favoriet',
                'is_favorite' => true,
                'created_at' => '2026-04-23T11:00:00Z',
            ],
        ];

        $sorted = sortSummariesByFavoriteThenCreatedDesc($items);

        $this->assertSame('nieuw favoriet', $sorted[0]['name']);
        $this->assertSame('oud favoriet', $sorted[1]['name']);
        $this->assertSame('nieuw niet-favoriet', $sorted[2]['name']);
    }

    public function testIsSummaryFavoritedForUserDefaultsToOwnSummary(): void
    {
        $item = [
            'drive_item_id' => 'abc',
            'name' => 'Samenvatting _iemand.test@kvt.nl_teamoverleg april.docx',
            'is_openable' => true,
        ];

        $this->assertTrue(isSummaryFavoritedForUser($item, [], 'iemand.test@kvt.nl'));
    }
}
