<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EmailArchiveTest extends TestCase
{
    private string $archiveRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];

        $this->archiveRoot = sys_get_temp_dir() . '/clio_email_archive_' . bin2hex(random_bytes(6));
        mkdir($this->archiveRoot, 0750, true);
        $GLOBALS['emailArchiveRoot'] = $this->archiveRoot;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->archiveRoot);
        unset($GLOBALS['emailArchiveRoot']);
    }

    public function testSanitizeEmailArchiveSubjectReplacesSeparator(): void
    {
        $this->assertSame('Vraag - offerte', sanitizeEmailArchiveSubject(' Vraag ~ offerte '));
    }

    public function testNormalizeEmailArchiveChainIdRemovesUnsafeCharacters(): void
    {
        $this->assertSame('abc@example.test', normalizeEmailArchiveChainId('<ABC@example.test>'));
        $this->assertSame('abc-def', normalizeEmailArchiveChainId('abc~def'));
    }

    public function testFormatEmailArchiveDateUsesDutchReadableFormat(): void
    {
        $this->assertSame('15 januari 2026, 16:34', formatEmailArchiveDate('2026-01-15 16:34:00'));
    }

    public function testLoadEmailArchiveThreadsAndSelectedThread(): void
    {
        $folder = 'Project update~chain@example.test';
        $threadPath = $this->archiveRoot . DIRECTORY_SEPARATOR . $folder;
        mkdir($threadPath, 0750, true);

        file_put_contents($threadPath . DIRECTORY_SEPARATOR . '0001-project-update.txt', 'Body text');
        file_put_contents($threadPath . DIRECTORY_SEPARATOR . '0001-project-update.html', '<p>Body html</p>');
        file_put_contents($threadPath . DIRECTORY_SEPARATOR . 'meta.json', json_encode([
            'updated_at' => '2026-06-11T10:00:00.000Z',
            'contacts' => [
                ['email' => 'sanne@example.test', 'name' => 'Sanne Jansen'],
            ],
            'emails' => [
                [
                    'subject' => 'Project update',
                    'from' => 'Sanne Jansen <sanne@example.test>',
                    'to' => ['Clio <clio@example.test>'],
                    'date' => '2026-06-11T09:55:00.000Z',
                    'text_file' => '0001-project-update.txt',
                    'html_file' => '0001-project-update.html',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $threads = loadEmailArchiveThreads();
        $thread = loadEmailArchiveThread($folder);

        $this->assertCount(1, $threads);
        $this->assertSame('Project update', $threads[0]['subject']);
        $this->assertSame(1, $threads[0]['email_count']);
        $this->assertIsArray($thread);
        $this->assertSame('Body text', $thread['emails'][0]['body_text']);
        $this->assertSame('<p>Body html</p>', $thread['emails'][0]['body_html']);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $this->removeDirectory($entryPath);
                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }
}
