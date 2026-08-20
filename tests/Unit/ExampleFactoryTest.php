<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Services\ExampleFactory;

// ----------------------------------------------------------------------------------
// ExampleFactory Test Suite
// Sections: forMediaType, forSchema
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

    test('returns null for a media type with no schema and no example', function () {
        expect((new ExampleFactory)->forMediaType([], references()))->toBeNull();
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
