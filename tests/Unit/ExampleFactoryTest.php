<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Services\ExampleFactory;

// ----------------------------------------------------------------------------------
// ExampleFactory Test Suite
// Sections: forMediaType, forSchema, hasDocumentedExample
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// ExampleFactory - forMediaType
// ------------------------------------------------------------

describe('ExampleFactory - forMediaType', function () {

	test('prefers the example the document wrote', function () {
		$example = (new ExampleFactory)->forMediaType([
			'schema' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string']]],
			'example' => ['code' => 'SUMMER10'],
		], references());

		expect($example)->toBe(implode("\n", [
			'{',
			'    "code": "SUMMER10"',
			'}',
		]));
	});

	test('takes the first of several named examples', function () {
		$example = (new ExampleFactory)->forMediaType([
			'examples' => [
				'first' => ['value' => ['code' => 'A']],
				'second' => ['value' => ['code' => 'B']],
			],
		], references());

		expect($example)->toContain('"A"')
			->and($example)->not->toContain('"B"');
	});

	test('builds an example from the schema when none is given', function () {
		$example = (new ExampleFactory)->forMediaType([
			'schema' => [
				'type' => 'object',
				'properties' => [
					'id' => ['type' => 'string', 'format' => 'uuid'],
					'total' => ['type' => 'integer'],
					'active' => ['type' => 'boolean'],
				],
			],
		], references());

		expect($example)->toContain('00000000-0000-0000-0000-000000000000')
			->and($example)->toContain('"total": 0')
			->and($example)->toContain('"active": true');
	});

	test('takes the values a 3.1 schema carries leaf by leaf', function () {
		// OpenAPI 3.1 replaced a schema's `example` with an `examples` array, and a
		// generator that writes 3.1 puts its values there. Reading only the older
		// spelling is how a document full of real values renders as "string".
		$example = (new ExampleFactory)->forMediaType([
			'schema' => [
				'type' => 'object',
				'properties' => [
					'sku' => ['type' => 'string', 'examples' => ['1005444106']],
					'price' => ['type' => 'integer', 'examples' => [199]],
				],
			],
		], references());

		expect($example)->toContain('"sku": "1005444106"')
			->and($example)->toContain('"price": 199')
			->and($example)->not->toContain('"string"');
	});

	test('writes the example in the format the media type was declared under', function () {
		$example = (new ExampleFactory)->forMediaType([
			'schema' => [
				'title' => 'Thing',
				'type' => 'object',
				'properties' => [
					'sku' => ['type' => 'string', 'examples' => ['1005444106']],
					'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'examples' => ['neu']]],
				],
			],
		], references(), 'application/xml');

		expect($example)->toBe(implode("\n", [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<Thing>',
			'  <sku>1005444106</sku>',
			'  <tags>neu</tags>',
			'</Thing>',
		]));
	});

	test('leaves an example the document wrote itself alone', function () {
		// A document that declares its own example wrote it in its own format, and
		// re-encoding it would be us overruling the document.
		$example = (new ExampleFactory)->forMediaType([
			'schema' => ['type' => 'object'],
			'example' => '<Thing><sku>1005444106</sku></Thing>',
		], references(), 'application/xml');

		expect($example)->toBe('<Thing><sku>1005444106</sku></Thing>');
	});

	test('returns null for a media type with no schema and no example', function () {
		expect((new ExampleFactory)->forMediaType([], references()))->toBeNull();
	});
});

// ------------------------------------------------------------
// ExampleFactory - hasDocumentedExample
// ------------------------------------------------------------

describe('ExampleFactory - hasDocumentedExample', function () {

	test('counts values the schema carries as the document\'s own', function () {
		// The shape is ours, every value in it is the document's — and the page
		// labels this as an example from the specification, because "no real
		// values" printed beside real values is the worse of the two mistakes.
		$documented = (new ExampleFactory)->hasDocumentedExample([
			'schema' => [
				'type' => 'object',
				'properties' => ['sku' => ['type' => 'string', 'examples' => ['1005444106']]],
			],
		], references());

		expect($documented)->toBeTrue();
	});

	test('reaches a value nested behind a property, an array and a branch', function () {
		$documented = (new ExampleFactory)->hasDocumentedExample([
			'schema' => [
				'type' => 'object',
				'properties' => [
					'data' => [
						'type' => 'array',
						'items' => ['allOf' => [['type' => 'string', 'examples' => ['WM01']]]],
					],
				],
			],
		], references());

		expect($documented)->toBeTrue();
	});

	test('says no when every value would have to be invented', function () {
		$documented = (new ExampleFactory)->hasDocumentedExample([
			'schema' => [
				'type' => 'object',
				'properties' => ['sku' => ['type' => 'string'], 'price' => ['type' => 'integer']],
			],
		], references());

		expect($documented)->toBeFalse();
	});
});

// ------------------------------------------------------------
// ExampleFactory - forSchema
// ------------------------------------------------------------

describe('ExampleFactory - forSchema', function () {

	test('prefers an example, then a default, then the first allowed value', function () {
		$factory = new ExampleFactory;

		expect($factory->forSchema(['type' => 'string', 'example' => 'SUMMER10', 'default' => 'X'], references()))->toBe('SUMMER10')
			->and($factory->forSchema(['type' => 'string', 'default' => 'de'], references()))->toBe('de')
			->and($factory->forSchema(['type' => 'string', 'enum' => ['percentage', 'fixed']], references()))->toBe('percentage');
	});

	test('uses a recognisable placeholder for a formatted string', function () {
		$factory = new ExampleFactory;

		expect($factory->forSchema(['type' => 'string', 'format' => 'date-time'], references()))->toBe('2026-01-01T00:00:00+00:00')
			->and($factory->forSchema(['type' => 'string', 'format' => 'email'], references()))->toBe('user@example.com')
			->and($factory->forSchema(['type' => 'string'], references()))->toBe('string');
	});

	test('builds one entry for an array', function () {
		$example = (new ExampleFactory)->forSchema([
			'type' => 'array',
			'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
		], references());

		expect($example)->toBe([['id' => 0]]);
	});

	test('resolves a referenced schema', function () {
		$document = ['components' => ['schemas' => ['CourseResource' => [
			'type' => 'object',
			'properties' => ['title' => ['type' => 'string', 'example' => 'Prophylaxe']],
		]]]];

		expect((new ExampleFactory)->forSchema(['$ref' => '#/components/schemas/CourseResource'], references($document)))
			->toBe(['title' => 'Prophylaxe']);
	});

	test('takes the first branch of a composed schema', function () {
		$example = (new ExampleFactory)->forSchema([
			'oneOf' => [
				['type' => 'string', 'example' => 'first'],
				['type' => 'integer', 'example' => 2],
			],
		], references());

		expect($example)->toBe('first');
	});

	test('stops at the configured depth instead of nesting for ever', function () {
		$document = ['components' => ['schemas' => ['Node' => [
			'type' => 'object',
			'properties' => ['child' => ['$ref' => '#/components/schemas/Node']],
		]]]];

		$example = (new ExampleFactory(maxDepth: 3))
			->forSchema(['$ref' => '#/components/schemas/Node'], references($document));

		expect($example)->toBe(['child' => ['child' => []]]);
	});
});
