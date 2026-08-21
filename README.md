# Filament API Explorer

An OpenAPI-driven API reference inside a Filament panel: find any endpoint from a
`⌘K` palette, read request and response schemas as a searchable tree, copy a
request sample in five languages — and send a `GET` request to see the live
response next to the documented one.

Responses you fetch are kept and shown as the endpoint's example, because a real
payload documents an API and a skeleton built from its schema does not.

The page also tells you what is *not* documented: every endpoint is checked for
a missing explanation, a missing response, a response without a schema, a body
it takes without documenting, and parameters without a description. The header
shows the documented share of the API, and one toggle narrows the whole page to
those gaps.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require dardangashi/filament-api-explorer
php artisan filament:assets
```

Register the plugin on a panel:

```php
use DardanGashi\FilamentApiExplorer\ApiExplorerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ApiExplorerPlugin::make());
}
```

Publish the configuration if you want to change it in the repository rather than
per panel:

```bash
php artisan vendor:publish --tag=filament-api-explorer-config
```

## Pointing it at a document

Each entry of `sources` is one OpenAPI document, and the key is the name shown in
the version picker. The first entry is the one the page opens with.

```php
// config/filament-api-explorer.php
'sources' => [
    'v2' => ['driver' => 'file', 'path' => storage_path('api-docs/v2.json')],
    'v1' => ['driver' => 'file', 'path' => storage_path('api-docs/v1.yaml')],
],
```

The `file` driver reads JSON and YAML. If you generate your specification with
[Scramble](https://scramble.dedoc.co), write it to disk first and point the
driver at it:

```bash
php artisan scramble:export --path=storage/api-docs/v2.json
```

Or skip the export step and read the document straight out of the generator — see
the driver below.

### Reading a document from somewhere else

Anything that writes a document to disk needs no code at all: the `file` driver
reads JSON and YAML, so an l5-swagger or swagger-php setup is a path.

```php
'sources' => [
    'v1' => ['driver' => 'file', 'path' => storage_path('api-docs/api-docs.json')],
],
```

Anything that has no file — a generator, an object store, an HTTP endpoint —
is a driver you register from a service provider. The whole configuration entry
reaches the resolver, so a driver takes the options it needs:

```php
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;

$this->app->resolving(SpecSourceManager::class, function (SpecSourceManager $manager): void {
    $manager->extend('gateway', fn (string $name, array $config): SpecSource => new GatewaySpecSource(
        name: $name,
        stage: $config['stage'] ?? 'prod',
    ));
});
```

```php
'sources' => [
    'gateway' => ['driver' => 'gateway', 'stage' => 'staging'],
],
```

A driver may replace a built-in one — register `file` again and files are read
your way from then on.

Two parts of `SpecSource` are worth more than they look. `generatedAt()` is what
allows the parsed document to be cached across requests, and it is the snapshot
time in the page header; a source that cannot date its document is parsed again on
every render, which is the safe way round. And `SpecUnavailable` is the way to
fail: the page renders a state for it that names the source and says why, while
anything else bubbles up, so a document that cannot be built never takes the panel
down with it.

### Scramble

Scramble is the one generator this package integrates with, and the only one it
follows through their releases. Everything else is a driver you register, using
the contract above.

Where [dedoc/scramble](https://github.com/dedoc/scramble) is installed, a driver
for it ships with this package. Name it and the reference describes the routes
that are registered, rather than an export somebody forgot to re-run:

```php
'sources' => [
    'api' => [
        'driver' => 'scramble',
        'api' => 'default',
        'watch' => [app_path(), base_path('routes'), config_path()],
    ],
],
```

Generating a document costs about a second, far too much for a page that
re-renders on every click, so the parsed specification is cached — and a cache
needs to know when what it describes last changed. `watch` is the answer: the
newest modification time among those paths dates the document. Editing a
controller invalidates the cache by itself, a deployment that changes nothing
keeps serving from it, and scanning a few hundred files costs about three
milliseconds. It doubles as the snapshot time in the page header, because that is
what it is.

The same integration adds the facts an OpenAPI schema has no field for to every
operation Scramble generates:

| Extension       | What it says                                        |
| --------------- | --------------------------------------------------- |
| `x-handler`     | the action that answers the endpoint                |
| `x-rate-limit`  | its throttle, as `600/min` or `100/h`               |
| `x-abilities`   | the token abilities the route insists on            |

They are the questions a reader asks straight away and would otherwise look up in
`routes/api.php`, and the page reads them back as the captions under an endpoint
title. Scramble also replaces an operation's description with the text of its
`@deprecated` tag, which costs an endpoint its documentation the moment somebody
marks it as going away; the integration puts both back, the description first and
the notice after it. Set `scramble.facts` to `false` to leave the generated
document exactly as Scramble wrote it.

Two things are worth knowing before you point a navigation badge at a generated
document. The badge is rendered on *every* page of the panel, and `count` and
`coverage` both need the whole document to compute — with a generator behind the
source that means a full analysis per page view, so leave the badge off (the
documented share is on the explorer's own page anyway). And prime the generator's
cache in production — `php artisan scramble:cache` — so neither the page nor the
badge pays for the analysis at request time.

## Configuring the plugin

Every option falls back to the configuration file, so a panel only states what
it wants to differ:

```php
ApiExplorerPlugin::make()
    ->slug('developer/api')
    ->navigationLabel('API')
    ->navigationGroup('Developer')
    ->navigationIcon('heroicon-o-code-bracket-square')
    ->navigationSort(100)
    ->navigationBadge('coverage')          // count | coverage | version | null
    ->title('API Documentation')
    ->description('Browse the endpoints and try them out.')
    ->source('v2')                         // which source the page opens with
    ->fullWidth()                          // off by default: the panel decides
    ->requestSending()                     // allow live GET requests
    ->enabledInProduction()                // off by default
    ->authorizeUsing(fn (): bool => auth()->user()?->isDeveloper() ?? false);
```

The page takes the width your panel gives every other page. `fullWidth()` hands
it the window instead, and nothing else changes: the two columns are laid out
from the room they are actually given — a container query, not the window size —
so an open sidebar or a panel of its own width does not leave them squeezed at
the moment a viewport breakpoint says there is space.

## Documentation gaps

Coverage here is not "does the endpoint appear in the document" — every route
does that by itself. It is whether the document answers the five questions a
reader actually arrives with, and `Gaps` in the toolbar narrows the palette to
the endpoints that leave one open. The share in the header is the same check
counted the other way round: endpoints with *no* gap at all. At 100 % there is
nothing to filter, so the button is disabled and says so.

| Badge                             | Raised when                                                                     | What closes it                                                        |
| --------------------------------- | ------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| No summary or description         | the operation has neither                                                       | one sentence of PHPDoc                                                |
| No response documented            | the operation documents no response at all                                      | a return type the generator can read, or `@response`                  |
| Success response without a schema | a `2xx` names a media type but nothing describes its body                        | return a resource or a typed value instead of an untyped array        |
| Parameters without a description   | a documented parameter carries no `description`                                 | describe it where it is declared                                      |
| No request body documented        | the method carries a body and none is documented, or it has no schema           | validate in a `FormRequest`, or in the action                         |

Two of those are worth a note. A parameter the explorer *inferred* — the
authentication header it reads off a security scheme, for instance — is never
counted: it is not in the document, so a missing description on it says nothing
about the document. And the request-body check is why a `POST` cannot reach full
coverage on its responses alone; without it the figure would call an endpoint
documented while saying nothing about what it expects to be sent.

The gaps are the document's, so they close wherever the document comes from. With
Scramble that is the controller, and this is an endpoint with four of the five:

```php
public function index(Request $request)
{
    return Order::query()
        ->when($request->string('status')->toString(), /* ... */)
        ->paginate()
        ->toArray();
}
```

No sentence, no described parameter, and an array whose shape nothing knows —
`paginate()->toArray()` is documented as an object with no properties. The same
endpoint with nothing left open:

```php
/**
 * Return a paginated list of orders.
 *
 * Supports filtering by status, source, payment status, and date range.
 *
 * @param  string  $status  Only orders in this status.
 */
public function index(OrderIndexRequest $request): AnonymousResourceCollection
{
    return OrderResource::collection(
        Order::query()->filter($request->validated())->paginate(),
    );
}
```

The first line is the summary, the paragraph under it the description, the
`@param` tag the parameter description, and the return type is what lets the
generator describe the body — one resource class documents every endpoint that
returns it.

There is nothing to configure: the five checks are the same for every document,
because a coverage figure you can widen until it reads 100 % measures nothing.

## Code samples

A sample is never written down. Every one is generated from the same blueprint
the live sender uses, so what you copy is what the explorer would send, and no
sample can fall behind a document that changed.

Samples are highlighted on the server — one pattern per language, one set of
token colours for all of them, and no syntax-highlighting library in the browser.
A credential is drawn as the variable it is, so the one thing you have to replace
is the one thing that stands out.

Five samples ship:

| Tab      | What it writes                                                        |
| -------- | --------------------------------------------------------------------- |
| `curl`   | one option per line, `-G` where a `GET` carries parameters            |
| `HTTP`   | the request as it goes over the wire — runs in the HTTP client of PhpStorm and the REST Client of VS Code, imports into Postman |
| `PHP`    | Laravel's HTTP client                                                 |
| `JS`     | the browser's `fetch`                                                 |
| `Python` | `requests`                                                            |

Which library a language reaches for is yours to change — implement
`RequestSnippet` and register it over the one that ships:

```php
app(SnippetRenderer::class)->register(new GuzzleSnippet);
```

A language of its own takes three things, and only the first is outside your
reach: a case on `SnippetLanguage`, which is what the tab and the query string
are keyed on, so that one is a change to this package. The other two are a
`RequestSnippet` that writes the sample and a highlighter for it — and a
highlighter is one regular expression whose named groups are the token classes,
which `Highlighter` turns into the same colours every other language uses. See
`src/Highlighting/SnippetHighlighter.php`, where the three meet.

## Sending requests

The request panel prefills each documented query parameter with its example,
default or first allowed value. Header inputs stay empty on purpose: a documented
header example is a placeholder, not a credential, so nothing is sent until you
type it. Once typed, a header follows you to the next endpoint that asks for the
same one — a token is typed to try an API, not one endpoint of it. It is held in
the page and stored nowhere, so a reload asks again.

Sending runs server-side, and it is restricted on purpose:

- only safe methods — `GET`, `HEAD` and `OPTIONS`; every other method is
  documented but never sent
- only the schemes in `execution.allowed_schemes`
- only the hosts in `execution.allowed_hosts`, which defaults to the
  application's own host — which is the one host an explorer needs in order to try
  its own API, and the reason a document offering a production server refuses to
  be called from a laptop. Patterns such as `*.staging.example.com` are supported,
  and a refusal names the setting it came from, so there is nothing to look up
- redirects are not followed, so a `30x` cannot lead somewhere the policy would
  have refused
- credentials never reach the code samples: a header that carries one is rendered
  as the placeholder of its language — `$TOKEN`, `{{token}}`, `$token`,
  `${token}`, `f'…{token}'` — in the sample you copy

```php
'execution' => [
    'enabled' => true,
    'allowed_hosts' => ['api.example.com', '*.staging.example.com'],
    'allowed_schemes' => ['https'],
    'timeout' => 10,
],
```

Set `enabled` to `false`, or call `->requestSending(false)`, to make the page a
pure reference.

## Recorded examples

An example built from a schema is correct and worthless: it says `"status":
"string"` where the API says `"status": "paid"`. So every response the explorer
receives is kept and shown in place of that skeleton, one sample per status —
the `200` of an endpoint and its `422` describe different shapes and both are
worth reading. A sample can be discarded from the page, and the next live
request replaces it.

Samples live in the cache, so a lost one costs nothing. What they hold is real
response data, shown to everyone who can open the page, so switch capturing off
where that is not acceptable:

```php
'examples' => [
    'capture' => true,
    'store' => null,      // cache store, null for the default
    'ttl' => 86400,
    'max_bytes' => 65536, // larger payloads are not kept
],
```

An example the document declares itself is used when nothing has been recorded;
a skeleton built from the schema comes last and arrives collapsed, labelled as
the structure it is.

## Vendor extensions

Any scalar `x-*` field on an operation is shown as a caption under the endpoint
title, which is how a specification can surface details this package knows
nothing about:

```yaml
paths:
  /vouchers:
    get:
      summary: Lists vouchers with cursor pagination.
      x-handler: VoucherController@index
      x-rate-limit: 60/min
      x-since: v2.0
```

## Caching

Parsing is repeated on every page load unless you turn the cache on. The cache
key carries the document's last-modified time, so a regenerated document is
picked up without anybody clearing a cache:

```php
'cache' => ['enabled' => true, 'store' => null, 'ttl' => 300],
```

## Not yet included

- **Cookies** are documented but get no input in the request panel.
- **Sending a body.** A request body is documented and rendered for every
  method, but only safe methods are ever sent, so there is nothing to fill in.

## Development

```bash
composer install
composer check     # pint --test, phpstan level 8, pest
```

Filament copies the stylesheet into the host application's `public/` directory, so
after editing `resources/css/api-explorer.css` the copy has to be refreshed —
otherwise the panel keeps serving the old one:

```bash
php artisan filament:assets
```

## License

MIT. See [LICENSE.md](LICENSE.md).
