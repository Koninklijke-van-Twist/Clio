<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    public function testCurrentLanguageDefaultsToDutch(): void
    {
        $this->assertSame('nl', getCurrentLanguage());
    }

    public function testCurrentLanguageFallsBackToDutchWhenUnknown(): void
    {
        $_SESSION['lang'] = 'es';
        $this->assertSame('nl', getCurrentLanguage());
    }

    public function testLocUsesCurrentLanguage(): void
    {
        $_SESSION['lang'] = 'en';
        $this->assertSame('Upload transcript', LOC('upload.title'));
    }

    public function testLocFallsBackToDutchWhenKeyMissingInLanguage(): void
    {
        $_SESSION['lang'] = 'en';
        $this->assertSame('non-existing.key', LOC('non-existing.key'));
    }

    public function testAllLanguagesContainAllDutchTranslationKeys(): void
    {
        $dutchKeys = array_keys(TRANSLATIONS['nl']);

        foreach (['en', 'de', 'fr'] as $language) {
            $languageKeys = array_keys(TRANSLATIONS[$language]);
            $missing = array_diff($dutchKeys, $languageKeys);

            $this->assertSame(
                [],
                array_values($missing),
                'Missing translation keys in ' . $language
            );
        }
    }

    public function testLanguageFlagSvgReturnsMarkupForKnownLanguage(): void
    {
        $svg = getLanguageFlagSvg('en');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function testLanguageFlagSvgFallsBackToDutch(): void
    {
        $fallbackSvg = getLanguageFlagSvg('unknown');

        $this->assertSame(getLanguageFlagSvg('nl'), $fallbackSvg);
    }
}
