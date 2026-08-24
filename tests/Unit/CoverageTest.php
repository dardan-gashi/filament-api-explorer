<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\Coverage;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Data\SchemaField;

// ----------------------------------------------------------------------------------
// Coverage Test Suite
// Sections: forEndpoints, percentage, gapCount, color
// ----------------------------------------------------------------------------------

function completeEndpoint(string $path = '/books'): Endpoint
{
	return endpoint(path: $path, responses: [
		new ResponseDefinition(
			status: '200',
			mediaType: 'application/json',
			fields: [new SchemaField(name: 'data', type: 'array<object>')],
		),
	]);
}

// ------------------------------------------------------------
// Coverage - forEndpoints
// ------------------------------------------------------------

describe('Coverage - forEndpoints', function () {

	test('counts the documented endpoints against the total', function () {
		$coverage = Coverage::forEndpoints([
			completeEndpoint('/books'),
			completeEndpoint('/authors'),
			endpoint(path: '/editions'),
		]);

		expect($coverage->total)->toBe(3)
			->and($coverage->documented)->toBe(2);
	});

	test('handles an api with no endpoints', function () {
		expect(Coverage::forEndpoints([])->total)->toBe(0);
	});
});

// ------------------------------------------------------------
// Coverage - percentage
// ------------------------------------------------------------

describe('Coverage - percentage', function () {

	test('rounds to a whole percent', function () {
		expect((new Coverage(documented: 5, total: 7))->percentage())->toBe(71);
	});

	test('reads an empty api as complete rather than as a failure', function () {
		expect((new Coverage(documented: 0, total: 0))->percentage())->toBe(100);
	});

	test('reports a fully documented api', function () {
		$coverage = new Coverage(documented: 4, total: 4);

		expect($coverage->percentage())->toBe(100)
			->and($coverage->isComplete())->toBeTrue();
	});
});

// ------------------------------------------------------------
// Coverage - gapCount
// ------------------------------------------------------------

describe('Coverage - gapCount', function () {

	test('counts the endpoints that are still incomplete', function () {
		expect((new Coverage(documented: 5, total: 7))->gapCount())->toBe(2);
	});
});

// ------------------------------------------------------------
// Coverage - color
// ------------------------------------------------------------

describe('Coverage - color', function () {

	test('escalates as the coverage falls', function (int $documented, string $color) {
		expect((new Coverage(documented: $documented, total: 100))->color())->toBe($color);
	})->with([
		[95, 'success'],
		[90, 'success'],
		[75, 'warning'],
		[60, 'warning'],
		[59, 'danger'],
		[0, 'danger'],
	]);
});
