<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\SchemaField;

// ----------------------------------------------------------------------------------
// SchemaField Test Suite
// Sections: matches, filter, filterAll, countRows
// ----------------------------------------------------------------------------------

function schemaTree(): SchemaField
{
	return new SchemaField(
		name: 'data',
		type: 'array<object>',
		children: [
			new SchemaField(name: 'id', type: 'string', format: 'uuid'),
			new SchemaField(name: 'code', type: 'string', description: 'Always upper case.'),
			new SchemaField(
				name: 'restrictions',
				type: 'object',
				children: [
					new SchemaField(name: 'max_uses', type: 'integer'),
					new SchemaField(name: 'used_count', type: 'integer'),
				],
			),
		],
	);
}

// ------------------------------------------------------------
// SchemaField - matches
// ------------------------------------------------------------

describe('SchemaField - matches', function () {

	test('matches on its own name whatever the casing', function () {
		expect(schemaTree()->matches('DATA'))->toBeTrue();
	});

	test('matches on a description or a type', function () {
		expect(schemaTree()->children[1]->matches('upper case'))->toBeTrue()
			->and(schemaTree()->children[0]->matches('uuid'))->toBeTrue();
	});

	test('matches when a descendant matches', function () {
		expect(schemaTree()->matches('used_count'))->toBeTrue();
	});

	test('does not match a term that occurs nowhere', function () {
		expect(schemaTree()->matches('edition'))->toBeFalse();
	});

	test('matches everything for an empty term', function () {
		expect(schemaTree()->matches(''))->toBeTrue()
			->and(schemaTree()->matches('   '))->toBeTrue();
	});
});

// ------------------------------------------------------------
// SchemaField - filter
// ------------------------------------------------------------

describe('SchemaField - filter', function () {

	test('keeps the whole subtree of a field that matches itself', function () {
		$filtered = schemaTree()->children[2]->filter('restrictions');

		expect($filtered?->children)->toHaveCount(2);
	});

	test('keeps only the branch that leads to a deep match', function () {
		$filtered = schemaTree()->filter('used_count');

		expect($filtered?->children)->toHaveCount(1)
			->and($filtered?->children[0]->name)->toBe('restrictions')
			->and($filtered?->children[0]->children)->toHaveCount(1)
			->and($filtered?->children[0]->children[0]->name)->toBe('used_count');
	});

	test('drops a field with no match anywhere below it', function () {
		expect(schemaTree()->children[0]->filter('edition'))->toBeNull();
	});

	test('leaves the field it filters untouched', function () {
		$tree = schemaTree();
		$tree->filter('used_count');

		expect($tree->children)->toHaveCount(3);
	});
});

// ------------------------------------------------------------
// SchemaField - filterAll
// ------------------------------------------------------------

describe('SchemaField - filterAll', function () {

	test('returns the fields unchanged for a blank term', function () {
		$fields = [schemaTree()];

		expect(SchemaField::filterAll($fields, null))->toBe($fields)
			->and(SchemaField::filterAll($fields, ''))->toBe($fields);
	});

	test('drops the fields that do not match', function () {
		$fields = schemaTree()->children;

		expect(SchemaField::filterAll($fields, 'code'))->toHaveCount(1);
	});

	test('returns an empty list when nothing matches', function () {
		expect(SchemaField::filterAll(schemaTree()->children, 'edition'))->toBe([]);
	});
});

// ------------------------------------------------------------
// SchemaField - countRows
// ------------------------------------------------------------

describe('SchemaField - countRows', function () {

	test('counts itself and every descendant', function () {
		expect(schemaTree()->countRows())->toBe(6);
	});

	test('counts a leaf as one row', function () {
		expect(schemaTree()->children[0]->countRows())->toBe(1);
	});
});
