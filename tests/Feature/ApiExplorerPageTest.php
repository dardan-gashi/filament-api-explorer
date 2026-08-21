<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;
use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;
use DardanGashi\FilamentApiExplorer\Support\InputKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

/**
 * The resource of a name, as the palette receives it.
 *
 * @param  list<array<string, mixed>>  $resources
 * @return array<string, mixed>
 */
function resource(array $resources, string $group): array
{
	foreach ($resources as $resource) {
		if ($resource['group'] === $group) {
			return $resource;
		}
	}

	return [];
}

/**
 * How often a badge with exactly this label is rendered. The markup wraps a badge
 * over three lines, so the whitespace has to go before it can be counted.
 */
function badges(string $html, string $label): int
{
	return substr_count((string) preg_replace('/\s+/', '', $html), ">{$label}<");
}

/**
 * A document with no gaps at all: an endpoint that is summarised, answers with a
 * documented status, and describes the body of it.
 *
 * @return array<string, mixed>
 */
function documentedDocument(): array
{
	return [
		'openapi' => '3.1.0',
		'info' => ['title' => 'complete api'],
		'paths' => [
			'/things' => [
				'get' => [
					'summary' => 'Lists things.',
					'responses' => [
						'200' => [
							'description' => 'OK',
							'content' => [
								'application/json' => [
									'schema' => [
										'type' => 'object',
										'properties' => ['id' => ['type' => 'string']],
									],
								],
							],
						],
					],
				],
			],
		],
	];
}

// ----------------------------------------------------------------------------------
// ApiExplorerPage Test Suite
// Sections: Render, Endpoint Selection, Search, Gap Filter, Snippets, Sending, Examples
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// ApiExplorerPage - Render
// ------------------------------------------------------------

describe('ApiExplorerPage - Render', function () {

	test('opens on the first endpoint of the specification', function () {
		livewire(ApiExplorerPage::class)
			->assertOk()
			->assertSet('endpointKey', Endpoint::keyFor(HttpMethod::Get, '/vouchers'));
	});

	test('states one version, and it is the API’s', function () {
		// The source key is a name from the configuration — `v2` next to the document's
		// own `v2.4.1` read as two versions of something. The picker is where a source
		// is named, and it is only there when there is a choice to make.
		$html = livewire(ApiExplorerPage::class)
			->assertSee('Version of the API')
			->html();

		expect($html)->toMatch('/fae-toolbar-meta">\s*7 endpoints/');
	});

	test('hands the whole navigation to the palette', function () {
		// Two levels and a search, all of it in the browser: no column of the page is
		// spent on navigation and no keystroke on a round trip.
		$page = livewire(ApiExplorerPage::class)->assertSee('Find endpoint');

		$resources = $page->viewData('resources');

		expect(array_column($resources, 'group'))->toBe(['Vouchers', 'Participants', 'Courses'])
			->and(resource($resources, 'Vouchers')['endpoints'])->toHaveCount(3)
			->and(resource($resources, 'Participants')['endpoints'][0]['haystack'])
			->toContain('participants');
	});

	test('matches a term against the method, the path and the summary at once', function () {
		// The haystack is what `ord sub` is matched against in the browser.
		$resources = livewire(ApiExplorerPage::class)->viewData('resources');

		expect(resource($resources, 'Vouchers')['endpoints'][0]['haystack'])
			->toBe('get /vouchers lists vouchers with cursor pagination. vouchers');
	});

	test('opens the palette on the resource of the endpoint on screen', function () {
		// Typing is for jumping somewhere else; opening the palette while reading an
		// endpoint shows what sits beside it.
		Livewire::withQueryParams(['endpoint' => Endpoint::keyFor(HttpMethod::Get, '/courses')]);

		expect(livewire(ApiExplorerPage::class)->viewData('openResource'))->toBe('Courses');
	});

	test('shortens the method of an endpoint the way a table would', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('DEL', escape: false)
			->assertDontSee('DELETE');
	});

	test('shows the documented share of the api', function () {
		// Four of the seven fixture endpoints are fully documented.
		livewire(ApiExplorerPage::class)->assertSee('57 %');
	});

	test('renders the response schema of the selected endpoint', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('VoucherListResource')
			->assertSee('next_cursor')
			->assertSee('array&lt;object&gt;', escape: false);
	});

	test('renders the body an endpoint expects', function () {
		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Post, '/vouchers'))
			->assertSee('Request body')
			->assertSee('The voucher to create.')
			->assertSee('The code customers redeem.');
	});

	test('reads a group heading as a heading, not as a class name', function () {
		// The fixture tags with plain nouns; a generator would hand over
		// `VoucherApi`, which must not reach the sidebar as it stands.
		$document = fixtureDocument();
		$document['paths']['/courses']['get']['tags'] = ['CourseApi'];

		config()->set('filament-api-explorer.sources', [
			'v2' => ['driver' => 'array', 'document' => $document],
		]);

		// The raw tag stays as the key a resource is found by; what a reader sees is
		// the caption.
		$resources = livewire(ApiExplorerPage::class)->viewData('resources');

		expect(resource($resources, 'CourseApi')['label'])->toBe('Course');
	});

	test('names the security scheme the endpoint needs', function () {
		livewire(ApiExplorerPage::class)->assertSee('sanctum');
	});

	test('offers a chevron on every field that has children', function () {
		livewire(ApiExplorerPage::class)->assertSee('fae-chevron', escape: false);
	});

	test('renders the documented request headers and query parameters', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('If-None-Match')
			->assertSee('filter[code]')
			->assertSee('per_page');
	});

	test('hands out the address of the endpoint on screen', function () {
		// The selection lives in the query string, so the address bar is the
		// permalink and copying it needs no round trip to the server.
		livewire(ApiExplorerPage::class)->assertSee('window.location.href', escape: false);
	});

	test('draws the captions of an endpoint with the icons of the panel', function () {
		// Sized in the markup, not only in the stylesheet: an unsized heroicon
		// grows to fill its container wherever the host CSS has gone stale.
		livewire(ApiExplorerPage::class)
			->assertSee('VoucherController@index')
			->assertSee('since v2.0')
			->assertSee('fae-meta-icon', escape: false)
			->assertSee('width="14"', escape: false);
	});

	test('marks a field required only where required changes the outcome', function () {
		// In a response, `required` would sit on nearly every row and say nothing.
		// What is worth saying there is its mirror image: four fields of the fixture
		// are left out of a required list and can therefore be missing from the
		// payload. In a body the word earns its row — it separates an accepted call
		// from a 422 — and in the reading endpoint the one badge left is the
		// Authorization header the security scheme asks for.
		$reading = livewire(ApiExplorerPage::class)->html();

		$writing = livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Post, '/vouchers'))
			->html();

		// The header, the body itself, and the two fields the body requires.
		expect(badges($reading, 'required'))->toBe(1)
			->and(badges($reading, 'optional'))->toBe(4)
			->and(badges($writing, 'required'))->toBe(4);
	});

	test('sets the names inside a description as names', function () {
		// Generators write a name as a code span, and a reference full of stray
		// backticks reads worse than one with none.
		livewire(ApiExplorerPage::class)
			->assertSee('<code class="fae-inline-code">discount_value</code>', escape: false)
			->assertDontSee('`discount_value`');
	});

	test('writes an endpoint without the part its resource already says', function () {
		// Inside `/vouchers` the endpoint `/vouchers/{code}` is `/{code}`; a search
		// result carries the whole path, because there it stands on its own.
		$vouchers = resource(livewire(ApiExplorerPage::class)->viewData('resources'), 'Vouchers');

		expect($vouchers['prefix'])->toBe('/vouchers')
			->and(array_column($vouchers['endpoints'], 'label'))->toBe(['/', '/', '/{code}'])
			->and(array_column($vouchers['endpoints'], 'path'))
			->toBe(['/vouchers', '/vouchers', '/vouchers/{code}']);
	});

	test('offers every endpoint of the api to the palette', function () {
		// The whole list travels once and is searched in the browser, so a term is
		// matched without a round trip per keystroke.
		livewire(ApiExplorerPage::class)
			->assertSee('{participant}')
			->assertSee('lists vouchers with cursor pagination. vouchers');
	});

	test('drives the palette from the keyboard', function () {
		// What the keys do cannot be tested without a browser; that they are bound
		// at all can. They sit on the dialog rather than on the input, because
		// hovering a row moves the focus off the input and the arrows have to keep
		// working when it has.
		livewire(ApiExplorerPage::class)
			->assertSee('x-on:keydown.down.prevent="move(1)"', escape: false)
			->assertSee('x-on:keydown.up.prevent="move(-1)"', escape: false)
			->assertSee('x-on:keydown.enter.prevent="enter()"', escape: false)
			->assertSee('x-on:keydown.left="term === \'\' && out()"', escape: false)
			->assertSee('x-on:keydown.right="term === \'\' && deeper()"', escape: false);
	});

	test('says which keys those are', function () {
		// The keys are the whole navigation now, so the palette states them once
		// where they are used rather than nowhere at all.
		livewire(ApiExplorerPage::class)
			->assertSee('<kbd class="fae-kbd">↑</kbd>', escape: false)
			->assertSee('<kbd class="fae-kbd">↵</kbd>', escape: false)
			->assertSee('<kbd class="fae-kbd">esc</kbd>', escape: false);
	});

	test('marks an endpoint on its way out wherever it is listed', function () {
		// The question "which of these should I not build against" is asked of the
		// list, not of the endpoint somebody has already opened.
		$vouchers = resource(livewire(ApiExplorerPage::class)->viewData('resources'), 'Vouchers');

		expect(array_column($vouchers['endpoints'], 'deprecated'))->toBe([false, false, true])
			->and(array_column($vouchers['endpoints'], 'documented'))->toBe([true, true, true]);
	});

	test('names a schema fact with the word the document uses for it', function () {
		// `optional` is what a response says by leaving a field out of its required
		// list: the field can be missing altogether, which is how a relation the
		// endpoint does not eager load is documented. These are the words of the
		// specification and they are left in it — a reader of an API reference knows
		// them, and explaining them would only take room from the schema.
		livewire(ApiExplorerPage::class)
			->assertSee('optional')
			->assertSee('nullable')
			->assertSee('deprecated');
	});

	test('puts the documented response headers beside the payload they arrive with', function () {
		// The right column is everything that goes over the wire — the request, the
		// body that comes back, and the headers that come back with it. Anything
		// after the snippet is in that column.
		$html = livewire(ApiExplorerPage::class)->html();

		expect(strpos($html, 'X-RateLimit-Remaining'))->toBeGreaterThan((int) strpos($html, 'curl'));
	});

	test('reports a specification that cannot be loaded instead of failing', function () {
		config()->set('filament-api-explorer.sources', [
			'v2' => ['driver' => 'file', 'path' => '/does/not/exist.json'],
		]);

		livewire(ApiExplorerPage::class)
			->assertOk()
			->assertSee('No OpenAPI document')
			// The path is the one thing the reader has to go and look at.
			->assertSee('/does/not/exist.json')
			->assertSee('check the sources');
	});

	test('names a driver nobody registered', function () {
		// The first thing a driver of one's own runs into is its own name, so the
		// state says which driver was asked for rather than only that something
		// went wrong.
		config()->set('filament-api-explorer.sources', ['v2' => ['driver' => 'gateway']]);

		livewire(ApiExplorerPage::class)
			->assertOk()
			->assertSee('No OpenAPI document')
			->assertSee('No OpenAPI source driver named [gateway] is registered.');
	});

	test('offers nothing to do with a document it has not got', function () {
		// A share of a document that never loaded, a filter over nothing, a search
		// across nothing, and an invitation to pick an endpoint out of an empty
		// list: four things the page used to say next to the error that says why
		// none of them can work.
		config()->set('filament-api-explorer.sources', [
			'v2' => ['driver' => 'file', 'path' => '/does/not/exist.json'],
		]);

		livewire(ApiExplorerPage::class)
			->assertDontSee('% documented')
			->assertDontSee('Gaps')
			->assertDontSee('Find endpoint')
			->assertDontSee('Select an endpoint');
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Endpoint Selection
// ------------------------------------------------------------

describe('ApiExplorerPage - Endpoint Selection', function () {

	test('switches to the endpoint that was clicked', function () {
		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'))
			->assertSet('endpointKey', Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'))
			->assertSee('The voucher code.');
	});

	test('leaves the palette out of the diffing that would strip its rows', function () {
		// The rows are written by Alpine from the structure it was handed, so they
		// are in nobody's HTML. Livewire's diffing removes what the server did not
		// send, and a list whose rows were removed under it stops answering clicks
		// without anything failing anywhere. The dialog around them is left out for
		// the same reason: the style that hides it is Alpine's, not the server's.
		expect(livewire(ApiExplorerPage::class)->html())
			->toMatch('/<div[^>]*fae-palette-overlay[^>]*wire:ignore/');
	});

	test('hands the palette a new identity when the structure changes', function () {
		// An attribute Alpine has already read is never read again, so a filtered
		// structure only reaches the palette as a different element.
		$arrival = livewire(ApiExplorerPage::class)->viewData('paletteKey');

		$sameResource = livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'))
			->viewData('paletteKey');

		$otherResource = livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/courses'))
			->viewData('paletteKey');

		$filtered = livewire(ApiExplorerPage::class)
			->call('filterGaps', true)
			->viewData('paletteKey');

		expect($sameResource)->toBe($arrival)
			->and($otherResource)->not->toBe($arrival)
			->and($filtered)->not->toBe($arrival);
	});

	test('ignores an endpoint that is not in the specification', function () {
		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', 'get-nope')
			->assertSet('endpointKey', Endpoint::keyFor(HttpMethod::Get, '/vouchers'));
	});

	test('prefills the query inputs with the documented defaults', function () {
		livewire(ApiExplorerPage::class)
			->assertSet('queryValues.'.InputKey::for('sort'), '-created_at')
			->assertSet('queryValues.'.InputKey::for('per_page'), '25');
	});

	test('leaves header inputs empty so no placeholder credential is sent', function () {
		livewire(ApiExplorerPage::class)
			->assertSet('headerValues.'.InputKey::for('Authorization'), '');
	});

	test('carries a filled header to the next endpoint that asks for it', function () {
		// A token is typed to try an API, not one endpoint of it. Retyping it per
		// endpoint is the friction that ends with the token pasted somewhere it can
		// be found again — but a header the next endpoint never asks for is dropped.
		livewire(ApiExplorerPage::class)
			->set('headerValues.'.InputKey::for('Authorization'), 'Bearer carried')
			->set('headerValues.'.InputKey::for('Accept-Language'), 'de-DE')
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/courses'))
			->assertSet('headerValues.'.InputKey::for('Authorization'), 'Bearer carried')
			->assertSet('headerValues.'.InputKey::for('Accept-Language'), null);
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Search
// ------------------------------------------------------------

describe('ApiExplorerPage - Search', function () {

	test('narrows the sidebar to the matching endpoints', function () {
		livewire(ApiExplorerPage::class)
			->set('search', 'courses')
			->assertSee('Courses')
			->assertDontSee('Participants');
	});

	test('moves the selection to the first match', function () {
		livewire(ApiExplorerPage::class)
			->set('search', 'courses')
			->assertSet('endpointKey', Endpoint::keyFor(HttpMethod::Get, '/courses'));
	});

	test('keeps the current selection while it still matches', function () {
		$key = Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}');

		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', $key)
			->set('search', 'vouchers')
			->assertSet('endpointKey', $key);
	});

	test('reports when nothing matches', function () {
		livewire(ApiExplorerPage::class)
			->set('search', 'zzzz')
			->assertSee('No endpoint matches.');
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Gap Filter
// ------------------------------------------------------------

describe('ApiExplorerPage - Gap Filter', function () {

	test('keeps only the resources with an incomplete documentation', function () {
		$resources = livewire(ApiExplorerPage::class)
			->call('filterGaps', true)
			->assertSet('onlyGaps', true)
			->viewData('resources');

		expect(array_column($resources, 'group'))->toBe(['Participants', 'Courses']);
	});

	test('selects a gap endpoint when the current one is complete', function () {
		livewire(ApiExplorerPage::class)
			->call('filterGaps', true)
			->assertSet('endpointKey', Endpoint::keyFor(HttpMethod::Get, '/participants'));
	});

	test('names the gaps of the selected endpoint', function () {
		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/participants'))
			->assertSee('No summary or description')
			->assertSee('No response documented');
	});

	test('restores every resource when the filter is cleared', function () {
		$resources = livewire(ApiExplorerPage::class)
			->call('filterGaps', true)
			->call('filterGaps', false)
			->assertSet('onlyGaps', false)
			->viewData('resources');

		expect(array_column($resources, 'group'))->toContain('Vouchers');
	});

	test('offers the filter while there is something to filter', function () {
		$html = livewire(ApiExplorerPage::class)->html();

		expect($html)->toMatch('/<button[^>]*filterGaps/')
			->and($html)->not->toMatch('/<button[^>]*disabled[^>]*filterGaps/');
	});

	test('switches itself off when every endpoint is documented', function () {
		// A filter over nothing empties the page and reads as a broken button, so at
		// full coverage it is disabled and says why rather than offering the click.
		config()->set('filament-api-explorer.sources', [
			'v1' => ['driver' => 'array', 'document' => documentedDocument()],
		]);

		$html = livewire(ApiExplorerPage::class)
			->assertSee('100 % documented')
			->assertSee('No gaps to filter')
			->html();

		expect($html)->toMatch('/<button[^>]*disabled[^>]*filterGaps/');
	});

	test('keeps a filtered endpoint out of the palette rather than in it', function () {
		// What `?gaps=` leaves out never reaches the browser, so a filtered palette
		// cannot offer an endpoint the address has excluded.
		$resources = livewire(ApiExplorerPage::class)
			->call('filterGaps', true)
			->viewData('resources');

		expect(array_column($resources, 'group'))->not->toContain('Vouchers');
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Snippets
// ------------------------------------------------------------

describe('ApiExplorerPage - Snippets', function () {

	test('renders a curl sample for the selected endpoint', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('https://api.bookshop.test/api/v2/vouchers')
			->assertSee('sort=-created_at');
	});

	test('marks up the sample it renders', function () {
		// Every sample is also on the page unmarked, inside the clipboard button, so
		// an assertion on the plain text passes whether the reader sees highlighting
		// or not. The tokens are what says the sample was highlighted.
		livewire(ApiExplorerPage::class)
			->assertSee('<span class="fae-code-call">curl</span>', escape: false)
			->assertSee('<span class="fae-code-keyword">-G</span>', escape: false);
	});

	test('puts the Accept header in the sample too, so copying it is enough', function () {
		livewire(ApiExplorerPage::class)->assertSee('Accept: application/json');
	});

	test('renders the credential as a shell variable rather than a value', function () {
		livewire(ApiExplorerPage::class)
			->set('headerValues.'.InputKey::for('Authorization'), 'Bearer super-secret')
			->assertSee('Bearer <span class="fae-code-variable">$TOKEN</span>', escape: false)
			->assertDontSee('super-secret');
	});

	test('offers every registered language as a tab', function () {
		// The wire format first, then the languages: the order they are read in.
		expect(array_map(
			fn (SnippetLanguage $language): string => $language->value,
			livewire(ApiExplorerPage::class)->viewData('snippetLanguages'),
		))->toBe(['curl', 'http', 'php', 'js', 'python']);
	});

	test('renders the request in its wire format', function () {
		livewire(ApiExplorerPage::class)
			->call('setSnippetLanguage', 'http')
			->assertSee('<span class="fae-code-keyword">GET</span>', escape: false)
			->assertSee('Host: api.bookshop.test');
	});

	test('renders the Python sample', function () {
		livewire(ApiExplorerPage::class)
			->call('setSnippetLanguage', 'python')
			->assertSee('<span class="fae-code-keyword">import</span> requests', escape: false);
	});

	test('switches to another language', function () {
		livewire(ApiExplorerPage::class)
			->call('setSnippetLanguage', 'php')
			->assertSet('snippetLanguage', 'php')
			->assertSee('Http::<span class="fae-code-call">withHeaders</span>', escape: false);
	});

	test('ignores an unknown language', function () {
		livewire(ApiExplorerPage::class)
			->call('setSnippetLanguage', 'cobol')
			->assertSet('snippetLanguage', 'curl');
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Examples
// ------------------------------------------------------------

describe('ApiExplorerPage - Examples', function () {

	test('shows the example the document declares as such', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('Example from the specification')
			->assertSee('SUMMER10');
	});

	test('says when an example is only a shape it built itself', function () {
		// The fixture's 401 carries a schema and no example, so the explorer has
		// to admit that "message": "string" is not an example of anything.
		livewire(ApiExplorerPage::class)->assertSee('Structure only, no real values');
	});

	test('replaces the example of that status with the response it received', function () {
		Http::fake([
			'api.bookshop.test/*' => Http::response(['data' => [['code' => 'REALCODE']]], 200),
		]);

		// The 200 the fixture declares an example for gives way; the 422 keeps its
		// own, because nothing has been recorded for it.
		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSee('Real response')
			->assertSee('REALCODE')
			->assertDontSee('9b4e2c1f-0000-4000-8000-000000000000')
			->assertSee('Invalid sort key.');
	});

	test('offers to drop a recorded response again', function () {
		Http::fake(['api.bookshop.test/*' => Http::response(['data' => [['code' => 'REALCODE']]], 200)]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSee('Real response')
			->call('discardSample', '200')
			->assertDontSee('Real response')
			->assertSee('9b4e2c1f-0000-4000-8000-000000000000');
	});

	test('records nothing when a request never arrives', function () {
		Http::fake(['api.bookshop.test/*' => fn () => throw new ConnectionException('Connection refused.')]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertDontSee('Real response');
	});

	test('keeps recorded responses apart per endpoint', function () {
		Http::fake(['api.bookshop.test/*' => Http::response(['data' => [['code' => 'REALCODE']]], 200)]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/courses'))
			->assertDontSee('REALCODE');
	});

	test('records nothing while capturing is switched off', function () {
		config()->set('filament-api-explorer.examples.capture', false);
		Http::fake(['api.bookshop.test/*' => Http::response(['data' => [['code' => 'REALCODE']]], 200)]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertDontSee('Real response');
	});

	test('invites the first request while nothing has been recorded', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('Send it once')
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Delete, '/participants/{participant}'))
			->assertDontSee('Send it once');
	});
});

// ------------------------------------------------------------
// ApiExplorerPage - Sending
// ------------------------------------------------------------

describe('ApiExplorerPage - Sending', function () {

	test('sends the request that was built and keeps the response', function () {
		Http::fake([
			'api.bookshop.test/*' => Http::response(['data' => []], 200, ['ETag' => '"abc"']),
		]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSet('result.status', 200)
			->assertSee('ETag');

		Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sort=-created_at'));
	});

	test('asks the API for json, so an error comes back as an error', function () {
		Http::fake(['api.bookshop.test/*' => Http::response([], 200)]);

		livewire(ApiExplorerPage::class)->call('send');

		Http::assertSent(fn ($request): bool => $request->hasHeader('Accept', 'application/json'));
	});

	test('sends only the header values the user typed', function () {
		Http::fake(['api.bookshop.test/*' => Http::response([], 200)]);

		livewire(ApiExplorerPage::class)
			->set('headerValues.'.InputKey::for('Authorization'), 'Bearer live-token')
			->call('send');

		Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer live-token'));
	});

	test('sends a pasted token with the scheme its documentation prescribes', function () {
		Http::fake(['api.bookshop.test/*' => Http::response([], 200)]);

		livewire(ApiExplorerPage::class)
			// What a user has in the clipboard is the token, not the header value.
			->set('headerValues.'.InputKey::for('Authorization'), '8|mBjlFcdRlSGG')
			->call('send');

		Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer 8|mBjlFcdRlSGG'));
	});

	test('keeps the scheme visible beside the field, not inside it', function () {
		livewire(ApiExplorerPage::class)
			->assertSee('fae-input-prefix', escape: false)
			->assertSee('&lt;token&gt;', escape: false);
	});

	test('refuses to send the documented example as a credential', function () {
		Http::fake();

		livewire(ApiExplorerPage::class)
			->set('headerValues.'.InputKey::for('Authorization'), 'Bearer <token>')
			->call('send')
			->assertNotified()
			->assertSet('result', null);

		Http::assertNothingSent();
	});

	test('says which credential was empty when the api answers 401', function () {
		Http::fake(['api.bookshop.test/*' => Http::response(['message' => 'Unauthenticated'], 401)]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSee('The Authorization field is empty');
	});

	test('says nothing about credentials when the request succeeds', function () {
		Http::fake(['api.bookshop.test/*' => Http::response(['data' => []], 200)]);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertDontSee('The Authorization field is empty');
	});

	test('refuses a host that is not allowed', function () {
		Http::fake();
		config()->set('filament-api-explorer.execution.allowed_hosts', ['api.example.com']);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSet('result', null)
			->assertNotified('Request refused');

		Http::assertNothingSent();
	});

	test('refuses to send while a path segment is still empty', function () {
		Http::fake();

		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'))
			->set('pathValues.'.InputKey::for('code'), '')
			->call('send')
			->assertNotified()
			->assertSet('result', null);

		Http::assertNothingSent();
	});

	test('sends once the path segment is filled in', function () {
		Http::fake(['api.bookshop.test/*' => Http::response(['code' => 'SUMMER10'], 200)]);

		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'))
			->set('pathValues.'.InputKey::for('code'), 'WINTER20')
			->call('send')
			->assertSet('result.status', 200);

		Http::assertSent(fn ($request): bool => str_contains($request->url(), '/vouchers/WINTER20'));
	});

	test('offers no sender for a method it will not send', function () {
		livewire(ApiExplorerPage::class)
			->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Post, '/vouchers'))
			->assertSee('only sends GET requests');
	});

	test('does nothing when sending is disabled', function () {
		Http::fake();
		config()->set('filament-api-explorer.execution.enabled', false);

		livewire(ApiExplorerPage::class)
			->call('send')
			->assertSet('result', null)
			->assertSee('Sending requests is disabled');

		Http::assertNothingSent();
	});
});
