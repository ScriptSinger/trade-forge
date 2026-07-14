<?php

namespace Tests\Unit;

use App\Services\Bot\Market\ScannerSymbolFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerSymbolFilterTest extends TestCase
{
    #[Test]
    public function it_uses_defaults_when_patterns_are_empty(): void
    {
        $patterns = ScannerSymbolFilter::resolve(null);

        $this->assertContains('USD1', $patterns);
        $this->assertContains('USDE', $patterns);
        $this->assertTrue(ScannerSymbolFilter::isExcluded('USD1USDT', $patterns));
        $this->assertTrue(ScannerSymbolFilter::isExcluded('USDEUSDT', $patterns));
        $this->assertFalse(ScannerSymbolFilter::isExcluded('BTCUSDT', $patterns));
    }

    #[Test]
    public function it_parses_comma_and_whitespace_separated_patterns(): void
    {
        $patterns = ScannerSymbolFilter::parse("usd1, usde\nfdusd");

        $this->assertSame(['USD1', 'USDE', 'FDUSD'], $patterns);
    }

    #[Test]
    public function it_formats_patterns_for_display(): void
    {
        $formatted = ScannerSymbolFilter::format(['USD1', 'USDE']);

        $this->assertSame('USD1, USDE', $formatted);
    }

    #[Test]
    public function it_converts_patterns_to_tags_value(): void
    {
        $tagsValue = ScannerSymbolFilter::toTagsValue(['USD1', 'USDE']);

        $this->assertSame('USD1,USDE', $tagsValue);
    }

    #[Test]
    public function it_parses_request_value_from_array(): void
    {
        $patterns = ScannerSymbolFilter::fromRequestValue(['usd1', 'USDE']);

        $this->assertSame(['USD1', 'USDE'], $patterns);
    }

    #[Test]
    public function it_rejects_invalid_and_forbidden_patterns(): void
    {
        $errors = ScannerSymbolFilter::validationErrors(['USDT', 'A', 'usd1!']);

        $this->assertCount(3, $errors);
        $this->assertStringContainsString('USDT', $errors[0]);
        $this->assertStringContainsString('«A»', $errors[1]);
    }

    #[Test]
    public function it_rejects_too_many_patterns(): void
    {
        $patterns = array_map(static fn (int $i): string => 'P'.$i, range(1, 21));

        $errors = ScannerSymbolFilter::validationErrors($patterns);

        $this->assertContains('Можно указать не более 20 паттернов.', $errors);
    }
}