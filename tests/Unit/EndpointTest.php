<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\BodyRendering;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBodyDefinition;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Data\SchemaField;
use DardanGashi\FilamentApiExplorer\Enums\DocumentationGap;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

// ----------------------------------------------------------------------------------
// Endpoint Test Suite
// Sections: keyFor, label, parametersIn, response, primaryResponse, mediaTypes, gaps,
//           isExecutable, matches
// ----------------------------------------------------------------------------------

function documentedResponse(string $status = '200'): ResponseDefinition
{
	return new ResponseDefinition(
		status: $status,
		description: 'A page of books.',
		mediaType: 'application/json',
		fields: [new SchemaField(name: 'data', type: 'array<object>')],
	);
}

// ------------------------------------------------------------
// Endpoint - keyFor
// ------------------------------------------------------------

describe('Endpoint - keyFor', function () {

	test('builds a key that is safe to put in a url', function () {
		expect(Endpoint::keyFor(HttpMethod::Get, '/api/v2/books/{code}'))
			->toBe('get-api-v2-books-code');
	});

	test('separates the methods of one path', function () {
		expect(Endpoint::keyFor(HttpMethod::Get, '/books'))
			->not->toBe(Endpoint::keyFor(HttpMethod::Post, '/books'));
	});
});

// ------------------------------------------------------------
// Endpoint - label
// ------------------------------------------------------------

describe('Endpoint - label', function () {

	test('prefers the summary', function () {
		expect(endpoint(summary: 'Lists books')->label())->toBe('Lists books');
	});

	test('falls back to the path', function () {
		expect(endpoint(summary: null, path: '/books')->label())->toBe('/books');
	});
});

// ------------------------------------------------------------
// Endpoint - parametersIn
// ------------------------------------------------------------

describe('Endpoint - parametersIn', function () {

	test('returns only the parameters of the given location', function () {
		$subject = endpoint(parameters: [
			new Parameter(name: 'Authorization', in: ParameterLocation::Header),
			new Parameter(name: 'sort', in: ParameterLocation::Query),
			new Parameter(name: 'cursor', in: ParameterLocation::Query),
		]);

		expect($subject->parametersIn(ParameterLocation::Query))->toHaveCount(2)
			->and($subject->parametersIn(ParameterLocation::Header))->toHaveCount(1)
			->and($subject->parametersIn(ParameterLocation::Path))->toBe([]);
	});

	test('reports whether a location is used at all', function () {
		$subject = endpoint(parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query)]);

		expect($subject->hasParametersIn(ParameterLocation::Query))->toBeTrue()
			->and($subject->hasParametersIn(ParameterLocation::Cookie))->toBeFalse();
	});
});

// ------------------------------------------------------------
// Endpoint - response
// ------------------------------------------------------------

describe('Endpoint - response', function () {

	test('finds a response by its status', function () {
		$subject = endpoint(responses: [documentedResponse('200'), documentedResponse('422')]);

		expect($subject->response('422')?->status)->toBe('422');
	});

	test('returns null for a status that is not documented', function () {
		expect(endpoint(responses: [documentedResponse()])->response('500'))->toBeNull();
	});
});

// ------------------------------------------------------------
// Endpoint - primaryResponse
// ------------------------------------------------------------

describe('Endpoint - primaryResponse', function () {

	test('prefers the first successful response', function () {
		$subject = endpoint(responses: [
			new ResponseDefinition(status: '401'),
			documentedResponse('200'),
		]);

		expect($subject->primaryResponse()?->status)->toBe('200');
	});

	test('falls back to the first documented response', function () {
		$subject = endpoint(responses: [new ResponseDefinition(status: '401'), new ResponseDefinition(status: '422')]);

		expect($subject->primaryResponse()?->status)->toBe('401');
	});

	test('returns null when nothing is documented', function () {
		expect(endpoint()->primaryResponse())->toBeNull();
	});
});

// ------------------------------------------------------------
// Endpoint - mediaTypes
// ------------------------------------------------------------

describe('Endpoint - mediaTypes', function () {

	test('names what the request body and the responses are written in, once each', function () {
		$endpoint = endpoint(
			requestBody: new RequestBodyDefinition(mediaType: 'application/json'),
			responses: [
				new ResponseDefinition(
					status: '200',
					mediaType: 'application/json',
					alternates: [new BodyRendering(mediaType: 'application/xml')],
				),
				new ResponseDefinition(status: '401', mediaType: 'application/json'),
			],
		);

		expect($endpoint->mediaTypes())->toBe(['application/json', 'application/xml']);
	});
});

// ------------------------------------------------------------
// Endpoint - gaps
// ------------------------------------------------------------

describe('Endpoint - gaps', function () {

	test('reports nothing for a fully documented endpoint', function () {
		$subject = endpoint(
			parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query, description: 'Sort key.')],
			responses: [documentedResponse()],
		);

		expect($subject->gaps())->toBe([])
			->and($subject->isDocumented())->toBeTrue();
	});

	test('reports a missing explanation', function () {
		$subject = new Endpoint(
			key: 'get-books',
			method: HttpMethod::Get,
			path: '/books',
			responses: [documentedResponse()],
		);

		expect($subject->gaps())->toBe([DocumentationGap::Description]);
	});

	test('accepts a description in place of a summary', function () {
		$subject = new Endpoint(
			key: 'get-books',
			method: HttpMethod::Get,
			path: '/books',
			description: 'Lists books.',
			responses: [documentedResponse()],
		);

		expect($subject->gaps())->toBe([]);
	});

	test('reports an endpoint with no documented response', function () {
		expect(endpoint()->gaps())->toBe([DocumentationGap::Responses]);
	});

	test('reports a successful response whose body has no schema', function () {
		$subject = endpoint(responses: [
			new ResponseDefinition(status: '200', mediaType: 'application/json'),
		]);

		expect($subject->gaps())->toBe([DocumentationGap::ResponseSchema]);
	});

	test('accepts a successful response that carries no body', function () {
		$subject = endpoint(responses: [new ResponseDefinition(status: '204', description: 'Removed.')]);

		expect($subject->gaps())->toBe([]);
	});

	test('reports a parameter without a description', function () {
		$subject = endpoint(
			parameters: [new Parameter(name: 'page', in: ParameterLocation::Query)],
			responses: [documentedResponse()],
		);

		expect($subject->gaps())->toBe([DocumentationGap::Parameters]);
	});

	test('ignores a parameter the explorer inferred itself', function () {
		// The authentication header is read off a security scheme rather than
		// documented, so a missing description on it is not the API's fault.
		$subject = endpoint(
			parameters: [new Parameter(
				name: 'Authorization',
				in: ParameterLocation::Header,
				required: true,
				inferred: true,
			)],
			responses: [documentedResponse()],
		);

		expect($subject->gaps())->toBe([]);
	});
});

// ------------------------------------------------------------
// Endpoint - isExecutable
// ------------------------------------------------------------

describe('Endpoint - isExecutable', function () {

	test('allows a read-only endpoint to be sent', function () {
		expect(endpoint(method: HttpMethod::Get)->isExecutable())->toBeTrue();
	});

	test('refuses an endpoint with side effects', function () {
		expect(endpoint(method: HttpMethod::Delete)->isExecutable())->toBeFalse();
	});
});

// ------------------------------------------------------------
// Endpoint - matches
// ------------------------------------------------------------

describe('Endpoint - matches', function () {

	test('matches on the path, the summary, the group or the method', function (string $term) {
		expect(endpoint()->matches($term))->toBeTrue();
	})->with([
		['books'],
		['Lists'],
		['Books'],
		['GET'],
	]);

	test('ignores casing', function () {
		expect(endpoint()->matches('BOOKS'))->toBeTrue();
	});

	test('matches everything for a blank term', function () {
		expect(endpoint()->matches(null))->toBeTrue()
			->and(endpoint()->matches('  '))->toBeTrue();
	});

	test('does not match an unrelated term', function () {
		expect(endpoint()->matches('editions'))->toBeFalse();
	});
});
