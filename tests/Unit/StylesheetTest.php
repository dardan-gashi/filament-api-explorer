<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

// ----------------------------------------------------------------------------------
// Stylesheet Test Suite
// Sections: Type Scale, Selectors
// ----------------------------------------------------------------------------------

function stylesheet(): string
{
    return (string) file_get_contents(__DIR__.'/../../resources/css/api-explorer.css');
}

/**
 * Every `fae-` class the views put into the markup, from both ways of writing one:
 * a `class` attribute and an `@class` array. The prefix also names element ids,
 * which is why the whole file cannot simply be scanned for it.
 *
 * A class completed at render time — `fae-badge-{{ $color }}` — ends in a hyphen
 * and is no class anybody styles.
 *
 * @return list<string>
 */
function classesInViews(): array
{
    $markup = collect(File::allFiles(__DIR__.'/../../resources/views'))
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    preg_match_all('/class="([^"]*)"|@class\(\[([^\]]*)\]\)/', $markup, $attributes);

    preg_match_all(
        '/(?<![\w-])fae-[a-z0-9-]+/',
        implode(' ', [...$attributes[1], ...$attributes[2]]),
        $matches,
    );

    return array_values(array_unique(array_filter(
        $matches[0],
        fn (string $class): bool => ! str_ends_with($class, '-'),
    )));
}

/**
 * The classes the stylesheet gives a rule of their own, as opposed to the ones it
 * only ever mentions inside a longer selector.
 *
 * @return list<string>
 */
function classesWithOwnRule(): array
{
    preg_match_all('/(?m)^\.(fae-[a-z0-9-]+)[\s,]*[{,]/', stylesheet(), $matches);

    return array_values(array_unique($matches[1]));
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

// ------------------------------------------------------------
// Stylesheet - Selectors
// ------------------------------------------------------------

describe('Stylesheet - Selectors', function () {

    test('styles every class the views use', function () {
        $mentioned = [];
        preg_match_all('/\.(fae-[a-z0-9-]+)/', stylesheet(), $mentioned);

        // A class in the markup with no rule behind it is either dead markup or a
        // rule an edit dropped, and both are invisible until someone looks.
        expect(array_values(array_diff(classesInViews(), $mentioned[1])))->toBe([]);
    });

    test('gives every class a rule of its own', function () {
        // `.fae-section-head .fae-meta-item` looks like a rule and is not one: the
        // meta item never appears inside a section head, so the class ends up
        // unstyled while the stylesheet still mentions it. Spacing between two
        // siblings is the one thing that can only be said through the context.
        expect(array_values(array_diff(classesInViews(), classesWithOwnRule())))
            ->toBe(['fae-response']);
    });
});
