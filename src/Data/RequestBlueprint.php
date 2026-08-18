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

    /**
     * The path placeholders still standing in the URL — `order` for
     * `/orders/{order}/subscriptions`.
     *
     * A sample is meant to keep them, so it reads as the template it is. A
     * request must never carry one: Laravel's HTTP client expands `{...}` as an
     * URI template, and an unknown placeholder expands to nothing at all, which
     * turns `/orders/{order}/subscriptions` into `/orders//subscriptions` and
     * asks the API a question nobody meant to ask.
     *
     * @return list<string>
     */
    public function unresolvedPlaceholders(): array
    {
        preg_match_all('/\{([^{}]*)\}/', $this->url, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Headers whose value still reads like the documentation's placeholder —
     * `Bearer <token>`, `<key>`. The example beside a header is a description of
     * what belongs there, and sending it verbatim can only ever fail.
     *
     * @return list<string>
     */
    public function placeholderHeaders(): array
    {
        $names = [];

        foreach ($this->headers as $name => $value) {
            if (preg_match('/<[^<>]+>/', $value) === 1) {
                $names[] = $name;
            }
        }

        return $names;
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
