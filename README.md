# Filament API Explorer

An OpenAPI-driven API reference inside a Filament panel: browse endpoints grouped
by tag, read response schemas as a searchable tree, copy a request sample in
curl, PHP or JavaScript — and send a `GET` request to see the live response next
to the documented one.

The page also tells you what is *not* documented: every endpoint is checked for
a missing explanation, a missing response, a response without a schema and
parameters without a description. The header shows the documented share of the
API, and the sidebar can be narrowed to the gaps.

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
    ->description('Browse and try the available endpoints.')
    ->source('v2')                         // which source the page opens with
    ->fullWidth()
    ->requestSending()                     // allow live GET requests
    ->enabledInProduction()                // off by default
    ->authorizeUsing(fn (): bool => auth()->user()?->isDeveloper() ?? false);
```

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
  as `$TOKEN`, `$token` or `${token}` in the sample you copy

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

- **Request bodies.** `POST`, `PUT`, `PATCH` and `DELETE` endpoints are listed
  and their parameters and responses are documented, but a request body schema is
  not rendered yet, and those methods are never sent.
- **Cookies** are documented but get no input in the request panel.

## Development

```bash
composer install
composer check     # pint --test, phpstan level 8, pest
```

## License

MIT. See [LICENSE.md](LICENSE.md).
