<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Services\SpecRepository;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;

// ----------------------------------------------------------------------------------
// SpecRepository Test Suite
// Sections: names, get, tryGet, flush
// ----------------------------------------------------------------------------------

/**
 * Rebuild the container bindings after the source configuration changed, since
 * the manager reads the configuration once when it is resolved.
 */
function repository(): SpecRepository
{
    app()->forgetInstance(SpecSourceManager::class);
    app()->forgetInstance(SpecRepository::class);

    return app(SpecRepository::class);
}

// ------------------------------------------------------------
// SpecRepository - names
// ------------------------------------------------------------

describe('SpecRepository - names', function () {

    test('reports the configured sources', function () {
        config()->set('filament-api-explorer.sources', [
            'v2' => ['driver' => 'file', 'path' => __DIR__.'/../Fixtures/openapi.json'],
            'v1' => ['driver' => 'array', 'document' => []],
        ]);

        expect(repository()->names())->toBe(['v2', 'v1'])
            ->and(repository()->defaultName())->toBe('v2')
            ->and(repository()->has('v1'))->toBeTrue();
    });
});

// ------------------------------------------------------------
// SpecRepository - get
// ------------------------------------------------------------

describe('SpecRepository - get', function () {

    test('parses the configured document', function () {
        $spec = repository()->get();

        expect($spec->title)->toBe('Bookshop API')
            ->and($spec->endpointCount())->toBe(7);
    });

    test('parses each document only once per request', function () {
        $repository = repository();

        expect($repository->get('v2'))->toBe($repository->get('v2'));
    });

    test('reads the source that was asked for', function () {
        config()->set('filament-api-explorer.sources', [
            'v2' => ['driver' => 'file', 'path' => __DIR__.'/../Fixtures/openapi.json'],
            'v1' => ['driver' => 'array', 'document' => ['info' => ['title' => 'legacy api']]],
        ]);

        expect(repository()->get('v1')->title)->toBe('legacy api');
    });

    test('raises when the document cannot be loaded', function () {
        config()->set('filament-api-explorer.sources', [
            'v2' => ['driver' => 'file', 'path' => '/does/not/exist.json'],
        ]);

        repository()->get();
    })->throws(SpecUnavailable::class);

    test('caches the parsed specification when caching is on', function () {
        config()->set('filament-api-explorer.cache.enabled', true);
        Cache::flush();

        repository()->get('v2');

        // The key carries the document timestamp, so a regenerated document is
        // picked up without anybody clearing a cache.
        $timestamp = filemtime(__DIR__.'/../Fixtures/openapi.json');

        expect(Cache::has("filament-api-explorer.spec.v2.{$timestamp}"))->toBeTrue();
    });

    test('serves a cached specification on the next request', function () {
        config()->set('filament-api-explorer.cache.enabled', true);
        Cache::flush();

        $first = repository()->get('v2');
        $second = repository()->get('v2');

        expect($second)->toEqual($first);
    });

    test('does not cache a source that cannot say when it changed', function () {
        config()->set('filament-api-explorer.cache.enabled', true);
        config()->set('filament-api-explorer.sources', [
            'memory' => ['driver' => 'array', 'document' => ['info' => ['title' => 'in memory']]],
        ]);
        Cache::flush();

        expect(repository()->get()->title)->toBe('in memory');
    });
});

// ------------------------------------------------------------
// SpecRepository - tryGet
// ------------------------------------------------------------

describe('SpecRepository - tryGet', function () {

    test('returns the specification when it loads', function () {
        expect(repository()->tryGet()?->title)->toBe('Bookshop API');
    });

    test('returns null instead of raising when it does not', function () {
        config()->set('filament-api-explorer.sources', [
            'v2' => ['driver' => 'file', 'path' => '/does/not/exist.json'],
        ]);

        expect(repository()->tryGet())->toBeNull();
    });
});

// ------------------------------------------------------------
// SpecRepository - flush
// ------------------------------------------------------------

describe('SpecRepository - flush', function () {

    test('drops what it parsed so far', function () {
        $repository = repository();
        $first = $repository->get('v2');

        $repository->flush();

        expect($repository->get('v2'))->not->toBe($first)
            ->and($repository->get('v2'))->toEqual($first);
    });
});
