<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;
use DardanGashi\FilamentApiExplorer\Services\ResponseSampleStore;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

// ----------------------------------------------------------------------------------
// ResponseSampleStore Test Suite
// Sections: remember, find, findMany, forget
// ----------------------------------------------------------------------------------

function sampleStore(bool $enabled = true, int $maxBytes = 65536): ResponseSampleStore
{
	return new ResponseSampleStore(
		cache: app(CacheFactory::class),
		enabled: $enabled,
		store: 'array',
		ttl: 600,
		maxBytes: $maxBytes,
	);
}

function executed(int $status = 200, string $body = '{"code":"LEGUIN-01"}'): ExecutedRequest
{
	return new ExecutedRequest(status: $status, body: $body, durationMs: 12);
}

// ------------------------------------------------------------
// ResponseSampleStore - remember
// ------------------------------------------------------------

describe('ResponseSampleStore - remember', function () {

	test('keeps a response as the endpoint example for its status', function () {
		CarbonImmutable::setTestNow('2026-08-18 09:30:00');

		$sample = sampleStore()->remember('v2', 'get-books', executed());

		expect($sample?->status)->toBe(200)
			->and($sample?->capturedAt->toDateTimeString())->toBe('2026-08-18 09:30:00')
			->and($sample?->body)->toContain('"code": "LEGUIN-01"');
	});

	test('indents the body it keeps, because that is what gets read', function () {
		expect(sampleStore()->remember('v2', 'get-books', executed())?->body)
			->toBe("{\n    \"code\": \"LEGUIN-01\"\n}");
	});

	test('keeps the media type the response arrived as', function () {
		// The endpoint may be read in another format tomorrow, and a body captured as
		// XML stays XML: without this the page would highlight it as whatever it is
		// currently reading.
		$sample = sampleStore()->remember('v2', 'get-books', new ExecutedRequest(
			status: 200,
			body: '<order><id>7</id></order>',
			durationMs: 12,
			headers: ['Content-Type' => 'application/xml; charset=UTF-8'],
		));

		expect($sample?->mediaType)->toBe('application/xml')
			->and(sampleStore()->find('v2', 'get-books', '200')?->mediaType)->toBe('application/xml');
	});

	test('keeps an error response too, since its shape is worth documenting', function () {
		sampleStore()->remember('v2', 'get-books', executed(status: 422, body: '{"message":"nope"}'));

		expect(sampleStore()->find('v2', 'get-books', '422')?->status)->toBe(422);
	});

	test('keeps one sample per status', function () {
		$store = sampleStore();
		$store->remember('v2', 'get-books', executed(status: 200, body: '{"a":1}'));
		$store->remember('v2', 'get-books', executed(status: 422, body: '{"b":2}'));

		expect($store->find('v2', 'get-books', '200')?->body)->toContain('"a"')
			->and($store->find('v2', 'get-books', '422')?->body)->toContain('"b"');
	});

	test('replaces the sample of a status it already has', function () {
		$store = sampleStore();
		$store->remember('v2', 'get-books', executed(body: '{"code":"OLD"}'));
		$store->remember('v2', 'get-books', executed(body: '{"code":"NEW"}'));

		expect($store->find('v2', 'get-books', '200')?->body)->toContain('NEW');
	});

	test('keeps nothing when a request never produced a response', function () {
		$sample = sampleStore()->remember('v2', 'get-books', ExecutedRequest::failed('Connection refused.'));

		expect($sample)->toBeNull();
	});

	test('keeps nothing for an empty body', function () {
		expect(sampleStore()->remember('v2', 'get-books', executed(body: '')))->toBeNull();
	});

	test('refuses a payload too large to sit on a page', function () {
		$store = sampleStore(maxBytes: 32);

		expect($store->remember('v2', 'get-books', executed(body: str_repeat('x', 64))))->toBeNull()
			->and($store->find('v2', 'get-books', '200'))->toBeNull();
	});

	test('records nothing while capturing is switched off', function () {
		$store = sampleStore(enabled: false);

		expect($store->isEnabled())->toBeFalse()
			->and($store->remember('v2', 'get-books', executed()))->toBeNull();
	});
});

// ------------------------------------------------------------
// ResponseSampleStore - find
// ------------------------------------------------------------

describe('ResponseSampleStore - find', function () {

	test('reports nothing for an endpoint it has not seen', function () {
		expect(sampleStore()->find('v2', 'get-authors', '200'))->toBeNull();
	});

	test('separates the sources it records for', function () {
		sampleStore()->remember('v2', 'get-books', executed());

		expect(sampleStore()->find('v1', 'get-books', '200'))->toBeNull();
	});

	test('reports nothing while capturing is switched off', function () {
		sampleStore()->remember('v2', 'get-books', executed());

		expect(sampleStore(enabled: false)->find('v2', 'get-books', '200'))->toBeNull();
	});

	test('survives a cache entry it cannot read', function () {
		// A sample outlives the release that wrote it, so a shape it no longer
		// understands has to read as "no sample" rather than as an error.
		app(CacheFactory::class)->store('array')->put(
			'filament-api-explorer.sample.v2.get-books.200',
			['status' => 200],
			600,
		);

		expect(sampleStore()->find('v2', 'get-books', '200'))->toBeNull();
	});
});

// ------------------------------------------------------------
// ResponseSampleStore - findMany
// ------------------------------------------------------------

describe('ResponseSampleStore - findMany', function () {

	test('returns the samples on hand, keyed by status', function () {
		$store = sampleStore();
		$store->remember('v2', 'get-books', executed(status: 200));
		$store->remember('v2', 'get-books', executed(status: 422, body: '{"message":"nope"}'));

		$samples = $store->findMany('v2', 'get-books', ['200', '304', '422']);

		// Looked up by status, the way the example panel does it. PHP stores the
		// numeric keys as integers, which the lookups normalise either way.
		expect($samples)->toHaveCount(2)
			->and($samples['200']->status)->toBe(200)
			->and($samples['422']->status)->toBe(422);
	});

	test('returns nothing for statuses it has never seen', function () {
		expect(sampleStore()->findMany('v2', 'get-books', ['200', '404']))->toBe([]);
	});
});

// ------------------------------------------------------------
// ResponseSampleStore - forget
// ------------------------------------------------------------

describe('ResponseSampleStore - forget', function () {

	test('drops a single sample and leaves the others', function () {
		$store = sampleStore();
		$store->remember('v2', 'get-books', executed(status: 200));
		$store->remember('v2', 'get-books', executed(status: 422, body: '{"message":"nope"}'));

		$store->forget('v2', 'get-books', '200');

		expect($store->find('v2', 'get-books', '200'))->toBeNull()
			->and($store->find('v2', 'get-books', '422'))->not->toBeNull();
	});
});
