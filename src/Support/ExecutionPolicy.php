<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;
use Illuminate\Support\Str;

/**
 * Decides whether the explorer may send a request.
 *
 * The rules are deliberately narrow, because the sender runs server-side with
 * whatever network the application can reach: only safe methods, only allowed
 * schemes, and only hosts an operator listed. Hosts are matched against
 * patterns, so `*.example.com` covers a set of environments.
 */
final class ExecutionPolicy
{
    /**
     * @param  list<string>  $allowedHosts
     * @param  list<string>  $allowedSchemes
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly array $allowedHosts,
        private readonly array $allowedSchemes = ['http', 'https'],
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        return $this->allowedHosts;
    }

    public function allows(RequestBlueprint $blueprint): bool
    {
        try {
            $this->authorize($blueprint);
        } catch (RequestNotAllowed) {
            return false;
        }

        return true;
    }

    /**
     * @throws RequestNotAllowed
     */
    public function authorize(RequestBlueprint $blueprint): void
    {
        if (! $this->enabled) {
            throw RequestNotAllowed::disabled();
        }

        if (! $blueprint->method->isSafe()) {
            throw RequestNotAllowed::unsafeMethod($blueprint->method);
        }

        $scheme = strtolower((string) $blueprint->scheme());

        if (! in_array($scheme, $this->allowedSchemes, true)) {
            throw RequestNotAllowed::insecureScheme($blueprint->scheme());
        }

        if (! $this->allowsHost($blueprint->host())) {
            throw RequestNotAllowed::hostNotAllowed($blueprint->host());
        }

        $unresolved = $blueprint->unresolvedPlaceholders();

        if ($unresolved !== []) {
            throw RequestNotAllowed::unresolvedPath($unresolved);
        }

        $placeholders = $blueprint->placeholderHeaders();

        if ($placeholders !== []) {
            throw RequestNotAllowed::placeholderHeader($placeholders);
        }
    }

    private function allowsHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        foreach ($this->allowedHosts as $pattern) {
            if (Str::is(strtolower($pattern), strtolower($host))) {
                return true;
            }
        }

        return false;
    }
}
