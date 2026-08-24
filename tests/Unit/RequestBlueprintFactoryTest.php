<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\BodyRendering;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBodyDefinition;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;
use DardanGashi\FilamentApiExplorer\Services\RequestBlueprintFactory;

/**
 * The authentication header the parser synthesises from a bearer security scheme.
 */
function authorizationHeader(): Parameter
{
	return new Parameter(
		name: 'Authorization',
		in: ParameterLocation::Header,
		required: true,
		example: 'Bearer <token>',
		inferred: true,
	);
}

// ----------------------------------------------------------------------------------
// RequestBlueprintFactory Test Suite
// Sections: make, header scheme, accept header, suggestions
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// RequestBlueprintFactory - make
// ------------------------------------------------------------

describe('RequestBlueprintFactory - make', function () {

	test('joins the server and the path into an absolute url', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(path: '/books'),
			server: 'https://api.bookshop.test/api/v2/',
		);

		expect($blueprint->url)->toBe('https://api.bookshop.test/api/v2/books');
	});

	test('substitutes a path parameter that has a value', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(path: '/books/{code}', parameters: [
				new Parameter(name: 'code', in: ParameterLocation::Path, required: true),
			]),
			server: 'https://api.bookshop.test',
			pathParameters: ['code' => 'LEGUIN 01'],
		);

		expect($blueprint->url)->toBe('https://api.bookshop.test/books/LEGUIN%2001');
	});

	test('leaves a path placeholder that has no value', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(path: '/books/{code}', parameters: [
				new Parameter(name: 'code', in: ParameterLocation::Path, required: true),
			]),
			server: 'https://api.bookshop.test',
			pathParameters: ['code' => '   '],
		);

		expect($blueprint->url)->toBe('https://api.bookshop.test/books/{code}');
	});

	test('keeps the query values of documented parameters', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [
				new Parameter(name: 'sort', in: ParameterLocation::Query),
				new Parameter(name: 'cursor', in: ParameterLocation::Query),
			]),
			server: 'https://api.bookshop.test',
			queryParameters: ['sort' => '-created_at', 'cursor' => ''],
		);

		expect($blueprint->query)->toBe(['sort' => '-created_at']);
	});

	test('drops a value that belongs to no documented parameter', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query)]),
			server: 'https://api.bookshop.test',
			queryParameters: ['sort' => 'code', 'injected' => 'value'],
			headers: ['X-Injected' => 'value'],
		);

		// The Accept header is the explorer's own; nothing a stale form left behind
		// travels with the request.
		expect($blueprint->query)->toBe(['sort' => 'code'])
			->and($blueprint->headers)->not->toHaveKey('X-Injected');
	});

	test('trims what the user typed', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query)]),
			server: 'https://api.bookshop.test',
			queryParameters: ['sort' => '  code  '],
		);

		expect($blueprint->query)->toBe(['sort' => 'code']);
	});

	test('carries the endpoint method', function () {
		$blueprint = (new RequestBlueprintFactory)->make(endpoint(), 'https://api.bookshop.test');

		expect($blueprint->method)->toBe(endpoint()->method);
	});
});

// ------------------------------------------------------------
// RequestBlueprintFactory - header scheme
// ------------------------------------------------------------

describe('RequestBlueprintFactory - header scheme', function () {

	test('puts the documented scheme in front of a pasted credential', function () {
		// Users paste `8|mBjl…`, not `Bearer 8|mBjl…`. Sanctum needs the scheme, and
		// without it the API can only answer 401.
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [authorizationHeader()]),
			server: 'https://api.bookshop.test/api',
			headers: ['Authorization' => '8|mBjlFcdRlSGG'],
		);

		expect($blueprint->headers['Authorization'])->toBe('Bearer 8|mBjlFcdRlSGG');
	});

	test('does not repeat a scheme the user typed', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [authorizationHeader()]),
			server: 'https://api.bookshop.test/api',
			headers: ['Authorization' => 'Bearer 8|mBjlFcdRlSGG'],
		);

		expect($blueprint->headers['Authorization'])->toBe('Bearer 8|mBjlFcdRlSGG');
	});

	test('ignores the casing of a scheme the user typed', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [authorizationHeader()]),
			server: 'https://api.bookshop.test/api',
			headers: ['Authorization' => 'bearer 8|mBjlFcdRlSGG'],
		);

		expect($blueprint->headers['Authorization'])->toBe('bearer 8|mBjlFcdRlSGG');
	});

	test('adds no scheme to a header that documents none', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [
				new Parameter(name: 'If-None-Match', in: ParameterLocation::Header, example: '"a3f1"'),
			]),
			server: 'https://api.bookshop.test/api',
			headers: ['If-None-Match' => '"b7c2"'],
		);

		expect($blueprint->headers['If-None-Match'])->toBe('"b7c2"');
	});

	test('sends nothing for a credential left empty', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [authorizationHeader()]),
			server: 'https://api.bookshop.test/api',
			headers: ['Authorization' => '   '],
		);

		expect($blueprint->headers)->not->toHaveKey('Authorization');
	});
});

// ------------------------------------------------------------
// RequestBlueprintFactory - accept header
// ------------------------------------------------------------

describe('RequestBlueprintFactory - accept header', function () {

	test('asks for the media type the endpoint documents', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(responses: [new ResponseDefinition(status: '200', mediaType: 'application/vnd.api+json')]),
			server: 'https://api.bookshop.test/api',
		);

		expect($blueprint->headers)->toBe(['Accept' => 'application/vnd.api+json']);
	});

	test('asks for json when the endpoint documents no media type', function () {
		// Without an Accept header a Laravel API answers an unauthenticated request
		// with a redirect to a login page instead of a 401, so asking is not optional.
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(),
			server: 'https://api.bookshop.test/api',
		);

		expect($blueprint->headers)->toBe(['Accept' => 'application/json']);
	});

	test('asks for the format the reader chose', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(responses: [new ResponseDefinition(
				status: '200',
				mediaType: 'application/json',
				alternates: [new BodyRendering(mediaType: 'application/xml')],
			)]),
			server: 'https://api.bookshop.test/api',
			accept: 'application/xml',
		);

		expect($blueprint->headers)->toBe(['Accept' => 'application/xml']);
	});

	test('ignores a chosen format no response is documented in', function () {
		// The formats of an endpoint include the ones its request body is sent as,
		// and `multipart/form-data` is a way to send and no way to be answered — a
		// correct server replies 406 to anybody who asks for it.
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(
				requestBody: new RequestBodyDefinition(mediaType: 'multipart/form-data'),
				responses: [new ResponseDefinition(status: '200', mediaType: 'application/json')],
			),
			server: 'https://api.bookshop.test/api',
			accept: 'multipart/form-data',
		);

		expect($blueprint->headers)->toBe(['Accept' => 'application/json']);
	});

	test('leaves an Accept header the document declares itself', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [new Parameter(name: 'accept', in: ParameterLocation::Header)]),
			server: 'https://api.bookshop.test/api',
			headers: ['accept' => 'text/csv'],
		);

		expect($blueprint->headers)->toBe(['accept' => 'text/csv']);
	});

	test('keeps the headers the user typed beside it', function () {
		$blueprint = (new RequestBlueprintFactory)->make(
			endpoint: endpoint(parameters: [new Parameter(name: 'Authorization', in: ParameterLocation::Header)]),
			server: 'https://api.bookshop.test/api',
			headers: ['Authorization' => 'Bearer live-token'],
		);

		expect($blueprint->headers)->toBe([
			'Accept' => 'application/json',
			'Authorization' => 'Bearer live-token',
		]);
	});
});

// ------------------------------------------------------------
// RequestBlueprintFactory - suggestions
// ------------------------------------------------------------

describe('RequestBlueprintFactory - suggestions', function () {

	test('suggests the example, then the default, then the first allowed value', function () {
		$suggestions = (new RequestBlueprintFactory)->suggestions(
			endpoint(parameters: [
				new Parameter(name: 'code', in: ParameterLocation::Query, example: 'LEGUIN-01', default: 'X'),
				new Parameter(name: 'per_page', in: ParameterLocation::Query, default: 25),
				new Parameter(name: 'sort', in: ParameterLocation::Query, enum: ['code', '-code']),
				new Parameter(name: 'cursor', in: ParameterLocation::Query),
			]),
			ParameterLocation::Query,
		);

		expect($suggestions)->toBe([
			'code' => 'LEGUIN-01',
			'per_page' => '25',
			'sort' => 'code',
			'cursor' => '',
		]);
	});

	test('renders a boolean default the way a reader expects', function () {
		$suggestions = (new RequestBlueprintFactory)->suggestions(
			endpoint(parameters: [new Parameter(name: 'filter[is_active]', in: ParameterLocation::Query, default: true)]),
			ParameterLocation::Query,
		);

		expect($suggestions)->toBe(['filter[is_active]' => 'true']);
	});

	test('returns nothing for a location with no parameters', function () {
		expect((new RequestBlueprintFactory)->suggestions(endpoint(), ParameterLocation::Header))->toBe([]);
	});
});
