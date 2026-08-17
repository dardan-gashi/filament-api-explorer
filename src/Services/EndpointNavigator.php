<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;

/**
 * Builds the endpoint sidebar: the search and gap filters, and the grouping by
 * tag. Groups keep the order the endpoints appear in, and empty groups are
 * dropped so a search never leaves behind a bare heading.
 */
final class EndpointNavigator
{
    /**
     * @return list<Endpoint>
     */
    public function filter(ApiSpec $spec, ?string $term = null, bool $onlyGaps = false): array
    {
        return array_values(array_filter(
            $spec->endpoints,
            fn (Endpoint $endpoint): bool => $endpoint->matches($term)
                && (! $onlyGaps || ! $endpoint->isDocumented()),
        ));
    }

    /**
     * @return array<string, list<Endpoint>>
     */
    public function groups(ApiSpec $spec, ?string $term = null, bool $onlyGaps = false): array
    {
        $groups = [];

        foreach ($this->filter($spec, $term, $onlyGaps) as $endpoint) {
            $groups[$endpoint->group][] = $endpoint;
        }

        return $groups;
    }

    /**
     * The endpoint a page should show: the current one when it is still in the
     * filtered list, otherwise the first match.
     */
    public function resolveSelected(ApiSpec $spec, ?string $key, ?string $term = null, bool $onlyGaps = false): ?Endpoint
    {
        $endpoints = $this->filter($spec, $term, $onlyGaps);

        foreach ($endpoints as $endpoint) {
            if ($endpoint->key === $key) {
                return $endpoint;
            }
        }

        return $endpoints[0] ?? $spec->find($key) ?? $spec->firstEndpoint();
    }
}
