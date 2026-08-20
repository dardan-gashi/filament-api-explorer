<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Data\SchemaField;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Services\EndpointNavigator;

// ----------------------------------------------------------------------------------
// EndpointNavigator Test Suite
// Sections: filter, groups, resolveSelected
// ----------------------------------------------------------------------------------

function navigable(): ApiSpec
{
	$documented = [new ResponseDefinition(
		status: '200',
		mediaType: 'application/json',
		fields: [new SchemaField(name: 'data', type: 'array<object>')],
	)];

	return new ApiSpec(
		name: 'v2',
		title: 'Bookshop API',
		endpoints: [
			endpoint(path: '/vouchers', summary: 'Lists vouchers', group: 'Vouchers', responses: $documented),
			endpoint(method: HttpMethod::Post, path: '/vouchers', summary: 'Creates a voucher', group: 'Vouchers', responses: $documented, requestBody: requestBody()),
			endpoint(path: '/participants', summary: null, group: 'Participants'),
			endpoint(path: '/courses', summary: 'Lists courses', group: 'Courses', responses: $documented),
		],
	);
}

// ------------------------------------------------------------
// EndpointNavigator - filter
// ------------------------------------------------------------

describe('EndpointNavigator - filter', function () {

	test('returns every endpoint without a filter', function () {
		expect((new EndpointNavigator)->filter(navigable()))->toHaveCount(4);
	});

	test('keeps the endpoints that match the term', function () {
		$filtered = (new EndpointNavigator)->filter(navigable(), 'courses');

		expect($filtered)->toHaveCount(1)
			->and($filtered[0]->path)->toBe('/courses');
	});

	test('keeps only the incomplete endpoints when asked for gaps', function () {
		$filtered = (new EndpointNavigator)->filter(navigable(), onlyGaps: true);

		expect($filtered)->toHaveCount(1)
			->and($filtered[0]->path)->toBe('/participants');
	});

	test('applies the term and the gap filter together', function () {
		expect((new EndpointNavigator)->filter(navigable(), 'vouchers', onlyGaps: true))->toBe([]);
	});
});

// ------------------------------------------------------------
// EndpointNavigator - groups
// ------------------------------------------------------------

describe('EndpointNavigator - groups', function () {

	test('groups the endpoints by tag in the order they appear', function () {
		$groups = (new EndpointNavigator)->groups(navigable());

		expect(array_keys($groups))->toBe(['Vouchers', 'Participants', 'Courses'])
			->and($groups['Vouchers'])->toHaveCount(2);
	});

	test('leaves behind no group whose endpoints were all filtered out', function () {
		$groups = (new EndpointNavigator)->groups(navigable(), 'courses');

		expect(array_keys($groups))->toBe(['Courses']);
	});
});

// ------------------------------------------------------------
// EndpointNavigator - resolveSelected
// ------------------------------------------------------------

describe('EndpointNavigator - resolveSelected', function () {

	test('keeps the current endpoint while it still matches', function () {
		$key = Endpoint::keyFor(HttpMethod::Get, '/courses');

		expect((new EndpointNavigator)->resolveSelected(navigable(), $key)?->key)->toBe($key);
	});

	test('moves to the first match when the current endpoint is filtered out', function () {
		$key = Endpoint::keyFor(HttpMethod::Get, '/courses');

		expect((new EndpointNavigator)->resolveSelected(navigable(), $key, 'vouchers')?->path)->toBe('/vouchers');
	});

	test('falls back to the first endpoint when nothing is selected', function () {
		expect((new EndpointNavigator)->resolveSelected(navigable(), null)?->path)->toBe('/vouchers');
	});

	test('keeps showing the selected endpoint when the filter matches nothing', function () {
		$key = Endpoint::keyFor(HttpMethod::Get, '/courses');

		expect((new EndpointNavigator)->resolveSelected(navigable(), $key, 'zzzz')?->key)->toBe($key);
	});

	test('returns null for a specification with no endpoints', function () {
		expect((new EndpointNavigator)->resolveSelected(ApiSpec::empty(), null))->toBeNull();
	});
});
