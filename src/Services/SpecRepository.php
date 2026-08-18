<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;
use Throwable;

/**
 * The single way the rest of the package reads specifications.
 *
 * Results are memoised per request, and optionally cached across requests. The
 * cache key carries the document's own timestamp, so a regenerated document is
 * picked up without anyone having to clear a cache.
 *
 * What is cached is the document, not the parsed specification. Generating a
 * document costs about a second and parsing it about fifteen milliseconds, so
 * caching the parsed objects buys a percent and pays for it with the objects
 * themselves: a cache entry written before a data class gained a property is
 * restored without that property, and reading it fails on a typed property that
 * was never initialised. A cached array cannot go out of shape with the code.
 */
final class SpecRepository
{
    /**
     * @var array<string, ApiSpec>
     */
    private array $memoized = [];

    /**
     * @param  string  $context  What the document was built for, usually the
     *                           application's root URL. See {@see key()}.
     */
    public function __construct(
        private readonly SpecSourceManager $sources,
        private readonly SpecParser $parser,
        private readonly CacheFactory $cache,
        private readonly bool $cacheEnabled = false,
        private readonly ?string $cacheStore = null,
        private readonly int $cacheTtl = 300,
        private readonly string $context = '',
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->sources->names();
    }

    public function has(string $name): bool
    {
        return $this->sources->has($name);
    }

    public function defaultName(): ?string
    {
        return $this->sources->defaultName();
    }

    /**
     * @throws SpecUnavailable
     */
    public function get(?string $name = null): ApiSpec
    {
        $source = $this->sources->source($name);
        $key = $source->name();

        if (isset($this->memoized[$key])) {
            return $this->memoized[$key];
        }

        $generatedAt = $source->generatedAt();

        if (! $this->cacheEnabled || $generatedAt === null) {
            return $this->memoized[$key] = $this->parser->parse($source);
        }

        /** @var array<string, mixed> $document */
        $document = $this->cache->store($this->cacheStore)->remember(
            $this->key($key, $generatedAt),
            $this->cacheTtl,
            fn (): array => $source->document(),
        );

        return $this->memoized[$key] = $this->parser->parseDocument($key, $document, $generatedAt);
    }

    /**
     * The cache key of one parsed document.
     *
     * Besides the source and the document's own timestamp it carries the context
     * it was built for. A generated document can name the URL of the request that
     * produced it — the servers of an OpenAPI document usually do — and a console
     * run has no request to take a host from. Without the context in the key, a
     * document built by an artisan command would be served to a browser with the
     * wrong host and port in it.
     */
    private function key(string $name, CarbonImmutable $generatedAt): string
    {
        $context = substr(sha1($this->context), 0, 8);

        return "filament-api-explorer.document.{$name}.{$generatedAt->getTimestamp()}.{$context}";
    }

    /**
     * The specification, or `null` when it cannot be loaded. Used where a
     * missing document should degrade to an empty state rather than an error —
     * the navigation badge, for instance.
     */
    public function tryGet(?string $name = null): ?ApiSpec
    {
        try {
            return $this->get($name);
        } catch (Throwable) {
            return null;
        }
    }

    public function flush(): void
    {
        $this->memoized = [];
    }
}
