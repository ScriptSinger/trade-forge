<?php

namespace App\Rules;

use App\Services\Bot\Market\ScannerSymbolFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ScannerExcludedPatternsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $patterns = ScannerSymbolFilter::fromRequestValue($value);

        foreach (ScannerSymbolFilter::validationErrors($patterns) as $message) {
            $fail($message);
        }
    }
}