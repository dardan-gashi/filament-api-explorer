<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

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
 */
final class SpecRepository
{
    /**
     * @var array<string, ApiSpec>
     */
    private array $memoized = [];

    public function __construct(
        private readonly SpecSourceManager $sources,
        private readonly SpecParser $parser,
        private readonly CacheFactory $cache,
        private readonly bool $cacheEnabled = false,
        private readonly ?string $cacheStore = null,
        private readonly int $cacheTtl = 300,
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

        /** @var ApiSpec $spec */
        $spec = $this->cache->store($this->cacheStore)->remember(
            "filament-api-explorer.spec.{$key}.{$generatedAt->getTimestamp()}",
            $this->cacheTtl,
            fn (): ApiSpec => $this->parser->parse($source),
        );

        return $this->memoized[$key] = $spec;
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
