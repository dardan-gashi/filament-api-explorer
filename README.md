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

Register your own driver from a service provider. Anything that can hand over a
decoded document works — a generator, an object store, an HTTP endpoint:

```php
use DardanGashi\FilamentApiExplorer\Sources\ArraySpecSource;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;

$this->app->resolving(SpecSourceManager::class, function (SpecSourceManager $manager): void {
    $manager->extend('generator', fn (string $name, array $config) => new ArraySpecSource(
        $name,
        app(YourGenerator::class)->document(),
    ));
});
```

```php
'sources' => [
    'v2' => ['driver' => 'generator'],
],
```

Implement `DardanGashi\FilamentApiExplorer\Contracts\SpecSource` directly if your
source can also report when the document last changed — the explorer shows that
as the snapshot time and uses it to key its cache.

### Example: a Scramble driver

A generator builds its document from the code on every call, so it has no
snapshot time — return `null` and let the generator do its own caching:

```php
final class ScrambleSpecSource implements SpecSource
{
    public function __construct(
        private readonly string $name,
        private readonly CacheableGenerator $generator,
        private readonly string $api = Scramble::DEFAULT_API,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function document(): array
    {
        try {
            $document = ($this->generator)(Scramble::getGeneratorConfig($this->api));
        } catch (Throwable $exception) {
            throw new SpecUnavailable("Scramble failed: {$exception->getMessage()}", previous: $exception);
        }

        return Documents::toMap($document);
    }

    public function generatedAt(): ?CarbonImmutable
    {
        return null;
    }
}
```

Throwing `SpecUnavailable` matters: the page renders an empty state for it and
lets anything else bubble up, so a document that cannot be built never takes the
panel down with it.

Two things are worth knowing before you point a navigation badge at a generated
document. The badge is rendered on *every* page of the panel, and `count` and
`coverage` both need the whole document to compute — with a generator behind the
source that means a full analysis per page view, so leave the badge off (the
documented share is on the explorer's own page anyway). And prime the generator's
cache in production — `php artisan scramble:cache` for Scramble — so neither the
page nor the badge pays for the analysis at request time.

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
    ->source('v2')                         // which source the page opens with
    ->fullWidth()
    ->requestSending()                     // allow live GET requests
    ->enabledInProduction()                // off by default
    ->authorizeUsing(fn (): bool => auth()->user()?->isDeveloper() ?? false);
```

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
type it.

Sending runs server-side, and it is restricted on purpose:

- only safe methods — `GET`, `HEAD` and `OPTIONS`; every other method is
  documented but never sent
- only the schemes in `execution.allowed_schemes`
- only the hosts in `execution.allowed_hosts`, which defaults to the
  application's own host. Patterns such as `*.staging.example.com` are supported
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
