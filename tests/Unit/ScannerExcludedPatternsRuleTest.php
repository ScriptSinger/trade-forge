<?php

namespace Tests\Unit;

use App\Rules\ScannerExcludedPatternsRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerExcludedPatternsRuleTest extends TestCase
{
    #[Test]
    public function it_allows_null_and_empty_values(): void
    {
        $rule = new ScannerExcludedPatternsRule;

        foreach ([null, '', []] as $value) {
            $validator = Validator::make(
                ['scanner_excluded_patterns' => $value],
                ['scanner_excluded_patterns' => [$rule]],
            );

            $this->assertFalse($validator->fails(), 'Expected empty value to pass validation');
        }
    }

    #[Test]
    public function it_rejects_usdt_pattern(): void
    {
        $validator = Validator::make(
            ['scanner_excluded_patterns' => 'USDT'],
            ['scanner_excluded_patterns' => [new ScannerExcludedPatternsRule]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('USDT', $validator->errors()->first('scanner_excluded_patterns'));
    }

    #[Test]
    public function it_accepts_valid_patterns(): void
    {
        $validator = Validator::make(
            ['scanner_excluded_patterns' => ['USD1', 'USDE']],
            ['scanner_excluded_patterns' => [new ScannerExcludedPatternsRule]],
        );

        $this->assertFalse($validator->fails());
    }
}