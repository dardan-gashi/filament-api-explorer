<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;

// ----------------------------------------------------------------------------------
// ReferenceResolver Test Suite
// Sections: resolve, pointer, nameOf, shortName
// ----------------------------------------------------------------------------------

function documentWithSchemas(): array
{
	return [
		'components' => [
			'schemas' => [
				'AuthorResource' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
				'Alias' => ['$ref' => '#/components/schemas/AuthorResource'],
				'Loop' => ['$ref' => '#/components/schemas/Loop'],
			],
			'responses' => [
				'not/found' => ['description' => 'Missing'],
			],
		],
	];
}

// ------------------------------------------------------------
// ReferenceResolver - resolve
// ------------------------------------------------------------

describe('ReferenceResolver - resolve', function () {

	test('replaces a reference with the schema it points at', function () {
		$resolved = references(documentWithSchemas())->resolve(['$ref' => '#/components/schemas/AuthorResource']);

		expect($resolved['type'])->toBe('object')
			->and($resolved)->not->toHaveKey('$ref');
	});

	test('follows a chain of references', function () {
		$resolved = references(documentWithSchemas())->resolve(['$ref' => '#/components/schemas/Alias']);

		expect($resolved['type'])->toBe('object');
	});

	test('lets keys written beside the reference win', function () {
		$resolved = references(documentWithSchemas())->resolve([
			'$ref' => '#/components/schemas/AuthorResource',
			'description' => 'The author of this book.',
			'nullable' => true,
		]);

		expect($resolved['description'])->toBe('The author of this book.')
			->and($resolved['nullable'])->toBeTrue()
			->and($resolved['type'])->toBe('object');
	});

	test('stops on a reference that points at itself', function () {
		$resolved = references(documentWithSchemas())->resolve(['$ref' => '#/components/schemas/Loop']);

		expect($resolved)->toBe(['$ref' => '#/components/schemas/Loop']);
	});

	test('leaves a reference that does not resolve', function () {
		$schema = ['$ref' => '#/components/schemas/Missing'];

		expect(references(documentWithSchemas())->resolve($schema))->toBe($schema);
	});

	test('leaves a schema without a reference alone', function () {
		$schema = ['type' => 'string'];

		expect(references()->resolve($schema))->toBe($schema);
	});
});

// ------------------------------------------------------------
// ReferenceResolver - pointer
// ------------------------------------------------------------

describe('ReferenceResolver - pointer', function () {

	test('walks the document to the addressed schema', function () {
		expect(references(documentWithSchemas())->pointer('#/components/schemas/AuthorResource'))
			->toHaveKey('properties');
	});

	test('decodes the escapes of a pointer segment', function () {
		expect(references(documentWithSchemas())->pointer('#/components/responses/not~1found'))
			->toBe(['description' => 'Missing']);
	});

	test('returns null for an external or unknown pointer', function (string $pointer) {
		expect(references(documentWithSchemas())->pointer($pointer))->toBeNull();
	})->with([
		['https://example.com/openapi.json#/Foo'],
		['#/components/schemas/Missing'],
		['#/components/schemas/AuthorResource/type'],
	]);
});

// ------------------------------------------------------------
// ReferenceResolver - nameOf
// ------------------------------------------------------------

describe('ReferenceResolver - nameOf', function () {

	test('reads the display name of a referenced schema', function () {
		expect(ReferenceResolver::nameOf(['$ref' => '#/components/schemas/BookResource']))
			->toBe('BookResource');
	});

	test('returns null for a schema written inline', function () {
		expect(ReferenceResolver::nameOf(['type' => 'object']))->toBeNull();
	});
});

// ------------------------------------------------------------
// ReferenceResolver - shortName
// ------------------------------------------------------------

describe('ReferenceResolver - shortName', function () {

	test('takes the last segment of a pointer', function () {
		expect(ReferenceResolver::shortName('#/components/schemas/BookResource'))->toBe('BookResource');
	});
});
