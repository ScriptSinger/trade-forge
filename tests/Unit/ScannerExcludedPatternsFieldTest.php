<?php

namespace Tests\Unit;

use App\Models\StrategyRiskSettings;
use App\MoonShine\Resources\Trading\StrategyRiskSettingsResource;
use App\Services\Bot\Market\ScannerSymbolFilter;
use MoonShine\UI\Fields\Text;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerExcludedPatternsFieldTest extends TestCase
{
    #[Test]
    public function change_fill_converts_json_array_to_tags_string(): void
    {
        $resource = app(StrategyRiskSettingsResource::class);
        $fields = iterator_to_array($resource->getFormFields());

        $field = collect($fields)->first(
            static fn ($field): bool => $field instanceof Text
                && $field->getColumn() === 'scanner_excluded_patterns',
        );

        $this->assertInstanceOf(Text::class, $field);

        $item = new StrategyRiskSettings([
            'scanner_excluded_patterns' => ['USD1', 'USDE'],
        ]);

        $field->fillData($item);

        $this->assertSame('USD1,USDE', $field->toValue());
    }

    #[Test]
    public function change_fill_handles_array_payload(): void
    {
        $resource = app(StrategyRiskSettingsResource::class);
        $fields = iterator_to_array($resource->getFormFields());

        $field = collect($fields)->first(
            static fn ($field): bool => $field instanceof Text
                && $field->getColumn() === 'scanner_excluded_patterns',
        );

        $this->assertInstanceOf(Text::class, $field);

        $field->fillData([
            'scanner_excluded_patterns' => ['USDC', 'DAI'],
        ]);

        $this->assertSame('USDC,DAI', $field->toValue());
    }

    #[Test]
    public function filled_tags_string_is_safe_for_html_escape(): void
    {
        $value = ScannerSymbolFilter::toTagsValue(['USD1', 'USDE']);

        $this->assertIsString($value);
        $this->assertSame('USD1,USDE', htmlspecialchars($value));
    }
}