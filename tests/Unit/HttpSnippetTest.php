<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Snippets\HttpSnippet;

// ----------------------------------------------------------------------------------
// HttpSnippet Test Suite
// Sections: language, render
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// HttpSnippet - language
// ------------------------------------------------------------

describe('HttpSnippet - language', function () {

	test('renders the http tab', function () {
		expect((new HttpSnippet)->language()->value)->toBe('http');
	});
});

// ------------------------------------------------------------
// HttpSnippet - render
// ------------------------------------------------------------

describe('HttpSnippet - render', function () {

	test('writes the request the way it goes over the wire', function () {
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			query: ['sort' => '-created_at', 'per_page' => '25'],
			headers: ['Accept' => 'application/json'],
		));

		expect($snippet)->toBe(implode("\n", [
			'GET /api/v2/books?sort=-created_at&per_page=25 HTTP/1.1',
			'Host: api.bookshop.test',
			'Accept: application/json',
		]));
	});

	test('names the method it is given', function () {
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Delete,
			url: 'https://api.bookshop.test/api/v2/editions/1',
		));

		expect($snippet)->toStartWith('DELETE /api/v2/editions/1 HTTP/1.1');
	});

	test('keeps the port with the host', function () {
		// The authority is what the request is addressed to, and a port is part
		// of it — a sample against a local server has to say so.
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'http://localhost:8000/api/v2/books',
		));

		expect($snippet)->toContain('Host: localhost:8000');
	});

	test('keeps the placeholders of the path it documents', function () {
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/orders/{order}/subscriptions',
		));

		expect($snippet)->toStartWith('GET /api/v2/orders/{order}/subscriptions');
	});

	test('replaces a credential with a variable the editors resolve', function () {
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			headers: ['Authorization' => 'Bearer real-token'],
		));

		expect($snippet)->toContain('Authorization: Bearer {{token}}')
			->and($snippet)->not->toContain('real-token');
	});

	test('drops the entries the user left blank', function () {
		$snippet = (new HttpSnippet)->render(new RequestBlueprint(
			method: HttpMethod::Get,
			url: 'https://api.bookshop.test/api/v2/books',
			query: ['sort' => '', 'per_page' => '25'],
			headers: ['If-None-Match' => ''],
		));

		expect($snippet)->not->toContain('sort')
			->and($snippet)->not->toContain('If-None-Match')
			->and($snippet)->toContain('per_page=25');
	});
});
