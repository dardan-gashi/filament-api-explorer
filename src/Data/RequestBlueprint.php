<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;

/**
 * Everything needed to reproduce one request. Both the code samples and the
 * live sender are built from this, so what a user copies is exactly what the
 * explorer would send.
 */
final readonly class RequestBlueprint
{
    /**
     * @param  string  $url  Absolute URL without a query string.
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public HttpMethod $method,
        public string $url,
        public array $query = [],
        public array $headers = [],
    ) {}

    public function fullUrl(): string
    {
        if ($this->query === []) {
            return $this->url;
        }

        return $this->url.'?'.http_build_query($this->query);
    }

    public function host(): ?string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: null;
    }

    public function scheme(): ?string
    {
        return parse_url($this->url, PHP_URL_SCHEME) ?: null;
    }

    /**
     * The same request without the entries the user left blank.
     */
    public function withoutBlanks(): self
    {
        return new self(
            method: $this->method,
            url: $this->url,
            query: array_filter($this->query, fn (string $value): bool => $value !== ''),
            headers: array_filter($this->headers, fn (string $value): bool => $value !== ''),
        );
    }
}
