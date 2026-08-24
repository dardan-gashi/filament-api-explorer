<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;
use DardanGashi\FilamentApiExplorer\Services\SpecRepository;

use function Pest\Livewire\livewire;

// ----------------------------------------------------------------------------------
// Example Documents Test Suite
// Sections: Parsing, Render
// ----------------------------------------------------------------------------------

/**
 * The demo API shipped in `examples/`, registered the way the README tells a
 * reader to register it.
 */
function useExampleDocuments(): void
{
	config()->set('filament-api-explorer.sources', [
		'v2' => ['driver' => 'file', 'path' => __DIR__.'/../../examples/bookshop-v2.json'],
		'v1' => ['driver' => 'file', 'path' => __DIR__.'/../../examples/bookshop-v1.json'],
	]);
}

// ------------------------------------------------------------
// Example Documents - Parsing
// ------------------------------------------------------------

describe('Example Documents - Parsing', function () {

	beforeEach(function () {
		useExampleDocuments();
	});

	test('describes an api whose documentation is deliberately incomplete', function () {
		// The demo exists to be photographed, and a screenshot of the gap filter
		// needs gaps: one endpoint of each kind the explorer can report.
		$spec = app(SpecRepository::class)->get('v2');

		$gaps = [];

		foreach ($spec->endpoints as $endpoint) {
			foreach ($endpoint->gaps() as $gap) {
				$gaps[$gap->value] = ($gaps[$gap->value] ?? 0) + 1;
			}
		}

		expect($spec->endpoints)->toHaveCount(14)
			->and($spec->version)->toBe('2.4.0')
			->and($spec->coverage()->percentage())->toBe(71)
			->and(array_keys($gaps))->toEqualCanonicalizing([
				'description',
				'parameters',
				'request_body',
				'response_schema',
			]);
	});

	test('keys every endpoint by the id its operation carries', function () {
		// The address of an endpoint is what a link holds and what another tool
		// reading the same document already uses.
		$keys = array_map(
			fn ($endpoint): string => $endpoint->key,
			app(SpecRepository::class)->get('v2')->endpoints,
		);

		expect($keys)->toContain('v2.books.index', 'v2.books.show', 'v2.exports.orders-csv')
			->and(array_filter($keys, fn (string $key): bool => str_starts_with($key, 'get-')))->toBe([]);
	});

	test('describes a previous major with nothing missing, so switching shows a difference', function () {
		$spec = app(SpecRepository::class)->get('v1');

		expect($spec->endpoints)->toHaveCount(5)
			->and($spec->version)->toBe('1.9.0')
			->and($spec->coverage()->percentage())->toBe(100);
	});

	test('offers a body in two media types, and one in a format of its own', function () {
		$spec = app(SpecRepository::class)->get('v2');

		$books = $spec->find('v2.books.index');
		$export = $spec->find('v2.exports.orders');
		$csv = $spec->find('v2.exports.orders-csv');

		expect($books?->responses[0]->mediaTypes())->toBe(['application/json', 'application/xml'])
			->and($books?->requestBody)->toBeNull()
			->and($spec->find('v2.books.store')?->requestBody?->mediaTypes())
			->toBe(['application/json', 'application/xml'])
			->and($export?->responses[0]->mediaType)->toBe('application/xml')
			->and($csv?->responses[0]->mediaType)->toBe('text/csv');
	});
});

// ------------------------------------------------------------
// Example Documents - Render
// ------------------------------------------------------------

describe('Example Documents - Render', function () {

	beforeEach(function () {
		useExampleDocuments();
	});

	test('renders the demo api without a document error', function () {
		livewire(ApiExplorerPage::class)
			->assertOk()
			->assertSee('Bookshop API')
			->assertSee('/v2/books')
			->assertDontSee('could not be read');
	});

	test('renders every endpoint of it', function () {
		// One page for each, because a document written to show everything is also
		// the one most likely to reach a branch nothing else does: an
		// `additionalProperties` map, a CSV body, a 204 with no content at all.
		$spec = app(SpecRepository::class)->get('v2');

		foreach ($spec->endpoints as $endpoint) {
			livewire(ApiExplorerPage::class, ['endpointKey' => $endpoint->key])
				->assertOk()
				->assertSee($endpoint->path);
		}
	});
});
