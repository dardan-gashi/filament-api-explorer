<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;
use DardanGashi\FilamentApiExplorer\Support\InputKey;

use function Pest\Livewire\livewire;

/**
 * How often a badge with exactly this label is rendered. The markup wraps a badge
 * over three lines, so the whitespace has to go before it can be counted.
 */
function badges(string $html, string $label): int
{
    return substr_count((string) preg_replace('/\s+/', '', $html), ">{$label}<");
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

    test('lists every endpoint grouped by its tag', function () {
        livewire(ApiExplorerPage::class)
            ->assertSee('Vouchers')
            ->assertSee('Participants')
            ->assertSee('Courses')
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

        livewire(ApiExplorerPage::class)
            ->assertSee('Course')
            ->assertDontSee('CourseApi');
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
        // What is worth saying there is its mirror image: five fields of the fixture
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
            ->and(badges($reading, 'optional'))->toBe(5)
            ->and(badges($writing, 'required'))->toBe(4);
    });

    test('strikes an endpoint on its way out through in the list', function () {
        // Written on the row, not only in the header of the selected endpoint: the
        // question "which of these should I not build against" is asked of the list.
        livewire(ApiExplorerPage::class)->assertSee('fae-endpoint-path-deprecated', escape: false);
    });

    test('says a response field can be missing, not that it is required', function () {
        // A relation the endpoint does not eager load is documented by leaving it out
        // of the required list, and that is the one thing a reader has to know before
        // reaching into the payload for it.
        livewire(ApiExplorerPage::class)
            ->assertSee('optional')
            ->assertSee('may be absent');
    });

    test('says what the badges of the schema mean where they are used', function () {
        // The badge keeps the word the document uses; the legend translates it once.
        livewire(ApiExplorerPage::class)
            ->assertSee('nullable')
            ->assertSee('can be null')
            ->assertSee('will be removed');
    });

    test('leaves out a legend for badges the schema does not use', function () {
        livewire(ApiExplorerPage::class)
            ->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Delete, '/participants/{participant}'))
            ->assertDontSee('can be null');
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
            ->assertSee('No OpenAPI document');
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

    test('keeps only the endpoints with an incomplete documentation', function () {
        livewire(ApiExplorerPage::class)
            ->call('filterGaps', true)
            ->assertSet('onlyGaps', true)
            ->assertSee('Participants')
            ->assertDontSee('Lists vouchers with cursor pagination.');
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

    test('restores every endpoint when the filter is cleared', function () {
        livewire(ApiExplorerPage::class)
            ->call('filterGaps', true)
            ->assertDontSee('Vouchers')
            ->call('filterGaps', false)
            ->assertSet('onlyGaps', false)
            ->assertSee('Vouchers');
    });
});

// ------------------------------------------------------------
// ApiExplorerPage - Snippets
// ------------------------------------------------------------

describe('ApiExplorerPage - Snippets', function () {

    test('renders a curl sample for the selected endpoint', function () {
        livewire(ApiExplorerPage::class)
            ->assertSee('curl -G')
            ->assertSee('https://api.bookshop.test/api/v2/vouchers')
            ->assertSee('sort=-created_at');
    });

    test('puts the Accept header in the sample too, so copying it is enough', function () {
        livewire(ApiExplorerPage::class)->assertSee('Accept: application/json');
    });

    test('renders the credential as a shell variable rather than a value', function () {
        livewire(ApiExplorerPage::class)
            ->set('headerValues.'.InputKey::for('Authorization'), 'Bearer super-secret')
            ->assertSee('Bearer $TOKEN')
            ->assertDontSee('super-secret');
    });

    test('switches to another language', function () {
        livewire(ApiExplorerPage::class)
            ->call('setSnippetLanguage', 'php')
            ->assertSet('snippetLanguage', 'php')
            ->assertSee('Http::withHeaders');
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
