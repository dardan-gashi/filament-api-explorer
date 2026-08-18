<?php

declare(strict_types=1);

// ----------------------------------------------------------------------------------
// Stylesheet Test Suite
// Sections: Type Scale
// ----------------------------------------------------------------------------------

function stylesheet(): string
{
    return (string) file_get_contents(__DIR__.'/../../resources/css/api-explorer.css');
}

// ------------------------------------------------------------
// Stylesheet - Type Scale
// ------------------------------------------------------------

describe('Stylesheet - Type Scale', function () {

    test('sizes every text through the panel scale', function () {
        // A hard-coded size is a size that stops following the panel. The tokens are
        // wired to Filament's own --text-* variables, so the explorer reads at the
        // same size as everything around it.
        preg_match_all('/font-size:\s*([^;]+);/', stylesheet(), $matches);

        expect($matches[1])->not->toBeEmpty()
            ->and(array_unique($matches[1]))
            ->each->toMatch('/^var\(--fae-font-(xs|sm|base)\)$/');
    });

    test('takes the panel value and falls back to the same step', function () {
        expect(stylesheet())
            ->toContain('--fae-font-xs: var(--text-xs, 0.75rem);')
            ->toContain('--fae-font-sm: var(--text-sm, 0.875rem);')
            ->toContain('--fae-font-base: var(--text-base, 1rem);');
    });

    test('reads at the size the panel reads at', function () {
        // Filament's body is --text-sm; anything else would make the page look like
        // a different application.
        expect(stylesheet())->toMatch('/\.fae \{[^}]*font-size: var\(--fae-font-sm\);/s');
    });
});
