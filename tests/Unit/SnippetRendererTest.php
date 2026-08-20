<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;
use DardanGashi\FilamentApiExplorer\Services\SnippetRenderer;
use DardanGashi\FilamentApiExplorer\Snippets\CurlSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\JavaScriptSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\PhpSnippet;

// ----------------------------------------------------------------------------------
// SnippetRenderer Test Suite
// Sections: languages, supports, render, renderAll
// ----------------------------------------------------------------------------------

function renderer(): SnippetRenderer
{
	return new SnippetRenderer([new CurlSnippet, new PhpSnippet, new JavaScriptSnippet]);
}

function blueprint(): RequestBlueprint
{
	return new RequestBlueprint(
		method: HttpMethod::Get,
		url: 'https://api.bookshop.test/api/v2/vouchers',
	);
}

// ------------------------------------------------------------
// SnippetRenderer - languages
// ------------------------------------------------------------

describe('SnippetRenderer - languages', function () {

	test('lists the registered languages in registration order', function () {
		expect(array_map(fn (SnippetLanguage $language): string => $language->value, renderer()->languages()))
			->toBe(['curl', 'php', 'js']);
	});

	test('lists nothing when none is registered', function () {
		expect((new SnippetRenderer)->languages())->toBe([]);
	});
});

// ------------------------------------------------------------
// SnippetRenderer - supports
// ------------------------------------------------------------

describe('SnippetRenderer - supports', function () {

	test('reports a language it can render', function () {
		expect(renderer()->supports(SnippetLanguage::Php))->toBeTrue()
			->and((new SnippetRenderer)->supports(SnippetLanguage::Php))->toBeFalse();
	});
});

// ------------------------------------------------------------
// SnippetRenderer - render
// ------------------------------------------------------------

describe('SnippetRenderer - render', function () {

	test('renders with the generator of the chosen language', function () {
		expect(renderer()->render(SnippetLanguage::Curl, blueprint()))->toStartWith('curl')
			->and(renderer()->render(SnippetLanguage::Php, blueprint()))->toStartWith('use Illuminate');
	});

	test('renders nothing for a language it does not know', function () {
		expect((new SnippetRenderer)->render(SnippetLanguage::Curl, blueprint()))->toBe('');
	});

	test('lets a later registration replace an earlier one', function () {
		$renderer = renderer()->register(new CurlSnippet);

		expect($renderer->languages())->toHaveCount(3);
	});
});

// ------------------------------------------------------------
// SnippetRenderer - renderAll
// ------------------------------------------------------------

describe('SnippetRenderer - renderAll', function () {

	test('renders every language keyed by its value', function () {
		$rendered = renderer()->renderAll(blueprint());

		expect(array_keys($rendered))->toBe(['curl', 'php', 'js'])
			->and($rendered['js'])->toContain('fetch');
	});
});
