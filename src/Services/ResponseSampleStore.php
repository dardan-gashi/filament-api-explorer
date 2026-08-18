<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;
use DardanGashi\FilamentApiExplorer\Data\ResponseSample;
use DardanGashi\FilamentApiExplorer\Support\Documents;

/**
 * Keeps the responses the explorer has actually seen, so a real payload can
 * stand in for a synthesised example.
 *
 * Samples are kept per status, which is what makes them useful as documentation:
 * the 200 of an endpoint and its 422 describe different shapes, and both are
 * worth reading. They live in the cache rather than in a table because a stale
 * sample is no loss — the next live request replaces it.
 *
 * Note that a sample is real response data, shared with everyone who can open
 * the page. Turn capturing off where that is not acceptable.
 */
final class ResponseSampleStore
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly bool $enabled = true,
        private readonly ?string $store = null,
        private readonly int $ttl = 86400,
        private readonly int $maxBytes = 65536,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Record a response, unless it is empty, oversized or never arrived.
     */
    public function remember(string $source, string $endpointKey, ExecutedRequest $result): ?ResponseSample
    {
        if (! $this->enabled || $result->hasFailed() || $result->body === '') {
            return null;
        }

        if (strlen($result->body) > $this->maxBytes) {
            return null;
        }

        $sample = new ResponseSample(
            status: $result->status,
            body: $result->prettyBody(),
            capturedAt: CarbonImmutable::now(),
        );

        $this->cache->store($this->store)->put(
            $this->key($source, $endpointKey, (string) $result->status),
            $sample->toArray(),
            $this->ttl,
        );

        return $sample;
    }

    public function find(string $source, string $endpointKey, string $status): ?ResponseSample
    {
        if (! $this->enabled) {
            return null;
        }

        $state = $this->cache->store($this->store)->get($this->key($source, $endpointKey, $status));

        return is_array($state) ? ResponseSample::fromArray(Documents::toMap($state)) : null;
    }

    /**
     * The samples on hand for a set of documented statuses, keyed by status.
     *
     * @param  list<string>  $statuses
     * @return array<string, ResponseSample>
     */
    public function findMany(string $source, string $endpointKey, array $statuses): array
    {
        $samples = [];

        foreach ($statuses as $status) {
            $sample = $this->find($source, $endpointKey, $status);

            if ($sample !== null) {
                $samples[$status] = $sample;
            }
        }

        return $samples;
    }

    public function forget(string $source, string $endpointKey, string $status): void
    {
        $this->cache->store($this->store)->forget($this->key($source, $endpointKey, $status));
    }

    private function key(string $source, string $endpointKey, string $status): string
    {
        return "filament-api-explorer.sample.{$source}.{$endpointKey}.{$status}";
    }
}
