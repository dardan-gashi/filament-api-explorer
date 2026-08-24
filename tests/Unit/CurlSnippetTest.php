<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Snippets\CurlSnippet;

// ----------------------------------------------------------------------------------
// CurlSnippet Test Suite
// Sections: language, render
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// CurlSnippet - language
// ------------------------------------------------------------

describe('CurlSnippet - language', function () {

	test('renders the curl tab', function () {
		expect((new CurlSnippet)->language()->value)->toBe('curl');
	});
});

// ------------------------------------------------------------
// CurlSnippet - render
// ------------------------------------------------------------

describe('CurlSnippet - render', function () {

	test('sends the parameters as a query string on a get request', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			query: ['sort' => '-created_at', 'per_page' => '25'],
		));

		expect($snippet)->toBe(implode("\n", [
			'curl -G \\',
			'  "https://api.bookshop.test/api/v2/books" \\',
			'  -d "sort=-created_at" \\',
			'  -d "per_page=25"',
		]));
	});

	test('leaves out the query flag when there is no query', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
		));

		expect($snippet)->toBe(implode("\n", [
			'curl \\',
			'  "https://api.bookshop.test/api/v2/books"',
		]));
	});

	test('names the method when it is not a get', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Delete,
			url: 'https://api.bookshop.test/api/v2/editions/1',
		));

		expect($snippet)->toStartWith('curl -X DELETE');
	});

	test('replaces a credential with a shell variable', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			headers: ['Authorization' => 'Bearer real-token', 'Accept-Language' => 'de'],
		));

		expect($snippet)->toContain('-H "Authorization: Bearer $TOKEN"')
			->and($snippet)->toContain('-H "Accept-Language: de"')
			->and($snippet)->not->toContain('real-token');
	});

	test('drops the entries the user left blank', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			query: ['sort' => '', 'per_page' => '25'],
			headers: ['If-None-Match' => ''],
		));

		expect($snippet)->not->toContain('sort')
			->and($snippet)->not->toContain('If-None-Match')
			->and($snippet)->toContain('per_page=25');
	});

	test('neutralises a value that would otherwise run as a command', function () {
		$snippet = (new CurlSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			query: ['filter[code]' => '$(whoami)"; rm -rf /'],
		));

		expect($snippet)->toContain('\\$(whoami)')
			->and($snippet)->toContain('\\"; rm -rf /');
	});
});
