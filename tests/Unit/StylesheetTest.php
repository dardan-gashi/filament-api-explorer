<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Highlighting\HttpHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\JavaScriptHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\JsonHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\PhpHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\PythonHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\ShellHighlighter;
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
		fn (string $class): bool => !str_ends_with($class, '-'),
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

/**
 * Every token class the highlighters actually emit, taken from a sample of each
 * language written to exercise all of them. The classes are composed at runtime
 * from the names of the capture groups, so the only honest way to collect them is
 * to run a highlighter and read what came out.
 *
 * @return list<string>
 */
function tokenClassesEmitted(): array
{
	$samples = [
		JsonHighlighter::highlight('{"code": "SUMMER10", "total": 42, "active": true}'),
		ShellHighlighter::highlight('curl -H "Authorization: Bearer $TOKEN" # the token is yours'),
		PhpHighlighter::highlight("// the token is yours\nuse Illuminate\\Support\\Facades\\Http;\n\n\$data = Http::get('https://example.test')->json();"),
		JavaScriptHighlighter::highlight('const response = await fetch(url, { headers: { Authorization: `Bearer ${token}` } })'),
		HttpHighlighter::highlight("GET /api/v1/orders HTTP/1.1\nAuthorization: Bearer {{token}}"),
		PythonHighlighter::highlight("# the token is yours\nimport requests\n\nrequests.get('https://example.test', headers={'Authorization': f'Bearer {token}'})"),
	];

	preg_match_all('/class="(fae-code-[a-z-]+)"/', implode('', $samples), $matches);

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

	test('sets the row the keyboard is on apart from the surface it sits on', function () {
		// It used to be one token away from the panel colour, which is a highlight
		// nobody can see — and then the arrow keys look broken while they work.
		preg_match('/\.fae-palette-result-active \{([^}]*)\}/', stylesheet(), $rule);

		expect($rule[1] ?? '')->toContain('var(--fae-select)')
			->and($rule[1] ?? '')->not->toContain('var(--fae-panel')
			->and(stylesheet())->toContain('--fae-select: rgb(15 23 42 / 0.08);')
			->toContain('--fae-select: rgb(255 255 255 / 0.08);');
	});

	test('takes the field metrics from Filament and not from taste', function () {
		// The canary: these numbers are Filament's, and the day it changes them a
		// field of ours that is two pixels off is exactly the kind of thing nobody
		// reports and everybody notices.
		$filament = __DIR__.'/../../vendor/filament/support/resources/css/components/input/index.css';

		$theirs = (string) file_get_contents($filament);

		expect($theirs)->toContain('px-3 py-1.5')
			->and($theirs)->toContain('text-sm leading-6');

		preg_match('/\\.fae-select,\\s*\\.fae-input \\{([^}]*)\\}/', stylesheet(), $rule);

		// The palette trigger stands in for a search field, so it is measured with it.
		preg_match('/\\.fae-palette-trigger \\{([^}]*)\\}/', stylesheet(), $trigger);

		expect($trigger[1] ?? '')->toContain('padding: 0.375rem 0.75rem')
			->and($trigger[1] ?? '')->toContain('border-radius: 0.5rem');

		// px-3, py-1.5, leading-6 and the wrapper's rounded-lg, in rem.
		expect($rule[1] ?? '')->toContain('padding: 0.375rem 0.75rem')
			->and($rule[1] ?? '')->toContain('line-height: 1.5rem')
			->and($rule[1] ?? '')->toContain('border-radius: 0.5rem');
	});

	test('divides a prefix off the field and keeps the list readable', function () {
		// Both are Filament's own doing and both are invisible in a screenshot of a
		// light panel: a prefix is a segment with a rule down its edge, and an option
		// list is drawn by the platform in white unless every option is given a
		// surface of its own.
		preg_match('/\\.fae-input-prefix \\{([^}]*)\\}/', stylesheet(), $prefix);
		preg_match('/\\.fae-select option \\{([^}]*)\\}/', stylesheet(), $option);

		expect($prefix[1] ?? '')->toContain('border-inline-end')
			->and($option[1] ?? '')->toContain('background: var(--fae-panel)')
			->and(stylesheet())->toContain('--fae-field-divider: rgb(255 255 255 / 0.1)');
	});

	test('measures the room a column has and never the window', function () {
		// Every layout here sits in a column of its own width: the panel's sidebar and
		// the page width Filament hands out are invisible to a viewport query, which
		// then reads a wide window as room the column has not got.
		expect(stylesheet())->not->toMatch('/@media \\(min-width/')
			->and(stylesheet())->toContain('@container (min-width: 64rem)')
			->and(stylesheet())->toContain('container-type: inline-size');
	});

	test('keeps a response inside its own scroll area', function () {
		// The page has one scroll, and a response has no length limit: without a
		// scroll of its own, a long payload pushes the sender out of the viewport and
		// every second attempt costs a trip up and back down.
		preg_match('/\\.fae-response-body \\{([^}]*)\\}/', stylesheet(), $rule);

		expect($rule[1] ?? '')->toContain('max-height')
			->and($rule[1] ?? '')->toContain('overflow');
	});

	test('colours every token the highlighters emit', function () {
		// A token class with no rule behind it is drawn in the body colour, which
		// reads as a highlighter that failed to recognise it. The count pins the
		// sample: if a language learns a token, the sample has to exercise it.
		$emitted = tokenClassesEmitted();

		expect($emitted)->toHaveCount(9)
			->and(array_values(array_diff($emitted, classesWithOwnRule())))->toBe([]);
	});
});
