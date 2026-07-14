<?php

namespace App\Services\Bot\Market;

class ScannerSymbolFilter
{
    public const int MAX_PATTERNS = 20;

    public const int MIN_PATTERN_LENGTH = 2;

    public const int MAX_PATTERN_LENGTH = 10;

    public const string PATTERN_FORMAT = '/^[A-Z0-9]{2,10}$/';

    /**
     * @var list<string>
     */
    public const array FORBIDDEN_PATTERNS = [
        'USDT',
    ];

    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            'USDC',
            'USD1',
            'USDE',
            'DAI',
            'BUSD',
            'EUR',
            'TUSD',
            'FDUSD',
        ];
    }

    /**
     * @param  array<int, string>|null  $patterns
     * @return list<string>
     */
    public static function resolve(?array $patterns): array
    {
        $normalized = self::normalizeList($patterns ?? []);

        return $normalized !== [] ? $normalized : self::defaults();
    }

    /**
     * @return list<string>
     */
    public static function parse(string $input): array
    {
        $parts = preg_split('/[\s,;]+/', trim($input)) ?: [];

        return self::normalizeList($parts);
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function format(array $patterns): string
    {
        return implode(', ', self::normalizeList($patterns));
    }

    /**
     * @param  array<int, string>|string|null  $value
     * @return list<string>
     */
    public static function fromRequestValue(mixed $value): array
    {
        if (is_array($value)) {
            return self::normalizeList($value);
        }

        return self::parse((string) $value);
    }

    /**
     * @param  array<int, string>|null  $patterns
     */
    public static function toTagsValue(?array $patterns): string
    {
        return implode(',', self::resolve($patterns));
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function validationErrors(array $patterns): array
    {
        $errors = [];

        if (count($patterns) > self::MAX_PATTERNS) {
            $errors[] = 'Можно указать не более '.self::MAX_PATTERNS.' паттернов.';
        }

        foreach ($patterns as $pattern) {
            if (preg_match(self::PATTERN_FORMAT, $pattern) !== 1) {
                $errors[] = "Паттерн «{$pattern}» недопустим: только латиница и цифры, от "
                    .self::MIN_PATTERN_LENGTH.' до '.self::MAX_PATTERN_LENGTH.' символов.';
            }

            if (in_array($pattern, self::FORBIDDEN_PATTERNS, true)) {
                $errors[] = "Паттерн «{$pattern}» исключит все USDT-пары из сканера.";
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function isExcluded(string $symbol, array $patterns): bool
    {
        $symbol = strtoupper($symbol);

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_contains($symbol, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $patterns
     * @return list<string>
     */
    private static function normalizeList(array $patterns): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $pattern): string => strtoupper(trim($pattern)),
            $patterns,
        ))));
    }
}