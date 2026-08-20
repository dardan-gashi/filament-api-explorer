<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Snippets\PhpSnippet;

// ----------------------------------------------------------------------------------
// PhpSnippet Test Suite
// Sections: language, render
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// PhpSnippet - language
// ------------------------------------------------------------

describe('PhpSnippet - language', function () {

	test('renders the php tab', function () {
		expect((new PhpSnippet)->language()->value)->toBe('php');
	});
});

// ------------------------------------------------------------
// PhpSnippet - render
// ------------------------------------------------------------

describe('PhpSnippet - render', function () {

	test('builds a call with headers and a query', function () {
		$snippet = (new PhpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/vouchers',
			query: ['sort' => '-created_at'],
			headers: ['Accept-Language' => 'de'],
		));

		expect($snippet)->toBe(implode("\n", [
			'use Illuminate\Support\Facades\Http;',
			'',
			'$response = Http::withHeaders([',
			'    \'Accept-Language\' => \'de\',',
			'])->get(',
			'    \'https://api.bookshop.test/api/v2/vouchers\', [',
			'        \'sort\' => \'-created_at\',',
			'    ],',
			');',
			'',
			'$data = $response->json();',
		]));
	});

	test('builds a bare call when there is nothing to pass', function () {
		$snippet = (new PhpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/vouchers',
		));

		expect($snippet)->toContain('$response = Http::get(')
			->and($snippet)->not->toContain('withHeaders');
	});

	test('interpolates a credential placeholder instead of printing the value', function () {
		$snippet = (new PhpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/vouchers',
			headers: ['Authorization' => 'Bearer real-token'],
		));

		expect($snippet)->toContain('\'Authorization\' => "Bearer $token"')
			->and($snippet)->not->toContain('real-token');
	});

	test('escapes a quote in a value', function () {
		$snippet = (new PhpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/vouchers',
			query: ['filter[code]' => "it's"],
		));

		expect($snippet)->toContain("'it\\'s'");
	});
});
