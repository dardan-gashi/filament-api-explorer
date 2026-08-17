<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\SchemaField;
use DardanGashi\FilamentApiExplorer\Services\SchemaFieldFactory;

// ----------------------------------------------------------------------------------
// SchemaFieldFactory Test Suite
// Sections: rootFields, field
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// SchemaFieldFactory - rootFields
// ------------------------------------------------------------

describe('SchemaFieldFactory - rootFields', function () {

    test('turns the properties of an object into the top-level rows', function () {
        $fields = (new SchemaFieldFactory)->rootFields([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'total' => ['type' => 'integer'],
            ],
        ], references());

        expect(array_map(fn (SchemaField $field): string => $field->name, $fields))->toBe(['id', 'total']);
    });

    test('names the single row of a body that is not an object after its schema', function () {
        $document = ['components' => ['schemas' => ['VoucherResource' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]];

        $fields = (new SchemaFieldFactory)->rootFields([
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/VoucherResource'],
        ], references($document));

        expect($fields)->toHaveCount(1)
            ->and($fields[0]->name)->toBe(SchemaFieldFactory::ROOT_FIELD_NAME)
            ->and($fields[0]->type)->toBe('array<object>')
            ->and(array_map(fn (SchemaField $field): string => $field->name, $fields[0]->children))->toBe(['id']);
    });

    test('returns nothing for a body with no schema', function () {
        expect((new SchemaFieldFactory)->rootFields([], references()))->toBe([]);
    });
});

// ------------------------------------------------------------
// SchemaFieldFactory - field
// ------------------------------------------------------------

describe('SchemaFieldFactory - field', function () {

    test('reads the type, format, description and deprecation of a property', function () {
        $field = (new SchemaFieldFactory)->field('discount', [
            'type' => 'number',
            'format' => 'float',
            'description' => 'Alias of discount_value.',
            'deprecated' => true,
        ], references());

        expect($field->type)->toBe('number')
            ->and($field->format)->toBe('float')
            ->and($field->description)->toBe('Alias of discount_value.')
            ->and($field->deprecated)->toBeTrue();
    });

    test('reads a nullable property written the 3.0 way', function () {
        $field = (new SchemaFieldFactory)->field('valid_until', [
            'type' => 'string',
            'nullable' => true,
        ], references());

        expect($field->type)->toBe('string')
            ->and($field->nullable)->toBeTrue();
    });

    test('reads a nullable property written the 3.1 way', function () {
        $field = (new SchemaFieldFactory)->field('valid_until', [
            'type' => ['string', 'null'],
        ], references());

        expect($field->type)->toBe('string')
            ->and($field->nullable)->toBeTrue();
    });

    test('spells out the item type of an array', function () {
        $field = (new SchemaFieldFactory)->field('tags', [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ], references());

        expect($field->type)->toBe('array<string>')
            ->and($field->children)->toBe([]);
    });

    test('shows the shape of one entry of a collection', function () {
        $field = (new SchemaFieldFactory)->field('data', [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'string'], 'code' => ['type' => 'string']],
                'required' => ['id'],
            ],
        ], references());

        expect($field->type)->toBe('array<object>')
            ->and(array_map(fn (SchemaField $child): string => $child->name, $field->children))->toBe(['id', 'code'])
            ->and($field->children[0]->required)->toBeTrue()
            ->and($field->children[1]->required)->toBeFalse();
    });

    test('records the name of a referenced schema', function () {
        $document = ['components' => ['schemas' => ['CourseResource' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]];

        $field = (new SchemaFieldFactory)->field('course', ['$ref' => '#/components/schemas/CourseResource'], references($document));

        expect($field->reference)->toBe('CourseResource')
            ->and($field->type)->toBe('object')
            ->and($field->children)->toHaveCount(1);
    });

    test('merges the branches of an allOf', function () {
        $document = ['components' => ['schemas' => ['CourseResource' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string']],
            'required' => ['id'],
        ]]]];

        $field = (new SchemaFieldFactory)->field('course', [
            'allOf' => [
                ['$ref' => '#/components/schemas/CourseResource'],
                ['type' => 'object', 'properties' => ['seats' => ['type' => 'integer']]],
            ],
            'nullable' => true,
        ], references($document));

        expect($field->nullable)->toBeTrue()
            ->and(array_map(fn (SchemaField $child): string => $child->name, $field->children))->toBe(['id', 'seats'])
            ->and($field->children[0]->required)->toBeTrue();
    });

    test('shows the first branch of a oneOf', function () {
        $field = (new SchemaFieldFactory)->field('payload', [
            'oneOf' => [
                ['type' => 'object', 'properties' => ['card' => ['type' => 'string']]],
                ['type' => 'object', 'properties' => ['iban' => ['type' => 'string']]],
            ],
        ], references());

        expect(array_map(fn (SchemaField $child): string => $child->name, $field->children))->toBe(['card']);
    });

    test('reads the allowed values of an enum as strings', function () {
        $field = (new SchemaFieldFactory)->field('flag', ['enum' => ['fixed', 10, true, null]], references());

        expect($field->enum)->toBe(['fixed', '10', 'true', 'null']);
    });

    test('stops a schema that refers back to itself', function () {
        $document = ['components' => ['schemas' => ['Node' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'parent' => ['$ref' => '#/components/schemas/Node'],
            ],
        ]]]];

        $field = (new SchemaFieldFactory)->field('node', ['$ref' => '#/components/schemas/Node'], references($document));
        $parent = $field->children[1];

        expect($parent->name)->toBe('parent')
            ->and($parent->reference)->toBe('Node')
            ->and($parent->children)->toBe([]);
    });

    test('stops expanding at the configured depth', function () {
        $schema = [
            'type' => 'object',
            'properties' => ['a' => ['type' => 'object', 'properties' => ['b' => ['type' => 'object', 'properties' => ['c' => ['type' => 'string']]]]]],
        ];

        $field = (new SchemaFieldFactory(maxDepth: 2))->field('root', $schema, references());

        expect($field->children[0]->name)->toBe('a')
            ->and($field->children[0]->children)->toBe([]);
    });

    test('falls back to a nameless type when the schema says nothing', function () {
        expect((new SchemaFieldFactory)->field('anything', [], references())->type)->toBe('mixed');
    });
});
