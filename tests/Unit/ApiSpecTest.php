<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;

// ----------------------------------------------------------------------------------
// ApiSpec Test Suite
// Sections: empty, find, versionLabel, defaultServer, commonPathPrefix
// ----------------------------------------------------------------------------------

function spec(array $endpoints = [], array $servers = [], ?string $version = null): ApiSpec
{
	return new ApiSpec(
		name: 'v2',
		title: 'Bookshop API',
		version: $version,
		servers: $servers,
		endpoints: $endpoints,
	);
}

// ------------------------------------------------------------
// ApiSpec - empty
// ------------------------------------------------------------

describe('ApiSpec - empty', function () {

	test('stands in for a specification that could not be loaded', function () {
		$spec = ApiSpec::empty('v1');

		expect($spec->name)->toBe('v1')
			->and($spec->title)->toBe('v1')
			->and($spec->endpoints)->toBe([])
			->and($spec->firstEndpoint())->toBeNull();
	});
});

// ------------------------------------------------------------
// ApiSpec - find
// ------------------------------------------------------------

describe('ApiSpec - find', function () {

	test('finds an endpoint by its key', function () {
		$wanted = endpoint(path: '/authors');
		$subject = spec([endpoint(path: '/books'), $wanted]);

		expect($subject->find($wanted->key)?->path)->toBe('/authors');
	});

	test('finds an endpoint by the method and path a link used to carry', function () {
		// The address of an endpoint became its operationId where the document gives
		// one, and a link written before that must not break.
		$subject = spec([new Endpoint(
			key: 'v2.authors.index',
			method: HttpMethod::Get,
			path: '/authors',
		)]);

		expect($subject->find('get-authors')?->key)->toBe('v2.authors.index');
	});

	test('returns null for an unknown or blank key', function () {
		$subject = spec([endpoint()]);

		expect($subject->find('get-nope'))->toBeNull()
			->and($subject->find(null))->toBeNull()
			->and($subject->find(''))->toBeNull();
	});
});

// ------------------------------------------------------------
// ApiSpec - versionLabel
// ------------------------------------------------------------

describe('ApiSpec - versionLabel', function () {

	test('prefixes a bare version number', function () {
		expect(spec(version: '2.4.1')->versionLabel())->toBe('v2.4.1');
	});

	test('leaves a version that is already prefixed', function () {
		expect(spec(version: 'v2')->versionLabel())->toBe('v2');
	});

	test('returns null when no version is documented', function () {
		expect(spec()->versionLabel())->toBeNull();
	});
});

// ------------------------------------------------------------
// ApiSpec - defaultServer
// ------------------------------------------------------------

describe('ApiSpec - defaultServer', function () {

	test('takes the first documented server', function () {
		expect(spec(servers: ['https://a.test', 'https://b.test'])->defaultServer())->toBe('https://a.test');
	});

	test('returns null when no server is documented', function () {
		expect(spec()->defaultServer())->toBeNull();
	});
});

// ------------------------------------------------------------
// ApiSpec - commonPathPrefix
// ------------------------------------------------------------

describe('ApiSpec - commonPathPrefix', function () {

	test('returns the segments every path shares', function () {
		$subject = spec([
			endpoint(path: '/api/v2/books'),
			endpoint(path: '/api/v2/books/{code}'),
			endpoint(path: '/api/v2/authors'),
		]);

		expect($subject->commonPathPrefix())->toBe('/api/v2');
	});

	test('never consumes the last segment of a path', function () {
		$subject = spec([endpoint(path: '/api/v2/books')]);

		expect($subject->commonPathPrefix())->toBe('/api/v2');
	});

	test('returns nothing when the paths share no prefix', function () {
		$subject = spec([endpoint(path: '/books'), endpoint(path: '/authors')]);

		expect($subject->commonPathPrefix())->toBe('');
	});

	test('returns nothing for an api with no endpoints', function () {
		expect(spec()->commonPathPrefix())->toBe('');
	});
});
