<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use Illuminate\Support\Str;
use DardanGashi\FilamentApiExplorer\Enums\DocumentationGap;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

/**
 * A single operation of the API: one HTTP method on one path.
 */
final readonly class Endpoint
{
    /**
     * @param  list<string>  $security  Names of the security schemes that apply.
     * @param  list<Parameter>  $parameters
     * @param  list<ResponseDefinition>  $responses
     * @param  array<string, string>  $meta  Extra captions shown under the title, e.g. the handler or rate limit.
     */
    public function __construct(
        public string $key,
        public HttpMethod $method,
        public string $path,
        public ?string $summary = null,
        public ?string $description = null,
        public string $group = 'General',
        public array $security = [],
        public array $parameters = [],
        public array $responses = [],
        public bool $deprecated = false,
        public array $meta = [],
    ) {}

    /**
     * A stable identifier, safe to put in a URL query string. Every run of
     * punctuation becomes a single dash, so the separators of a path survive
     * into the key and `/a/bc` cannot collide with `/ab/c`.
     */
    public static function keyFor(HttpMethod $method, string $path): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $path), '-'));

        return $method->value.'-'.$slug;
    }

    /**
     * The caption for the endpoint in navigation and headings.
     */
    public function label(): string
    {
        return $this->summary ?? $this->path;
    }

    /**
     * @return list<Parameter>
     */
    public function parametersIn(ParameterLocation $in): array
    {
        return array_values(array_filter(
            $this->parameters,
            fn (Parameter $parameter): bool => $parameter->in === $in,
        ));
    }

    public function hasParametersIn(ParameterLocation $in): bool
    {
        return $this->parametersIn($in) !== [];
    }

    public function response(string $status): ?ResponseDefinition
    {
        foreach ($this->responses as $response) {
            if ($response->status === $status) {
                return $response;
            }
        }

        return null;
    }

    /**
     * The response the example panel opens with: the first successful one,
     * else the first documented one.
     */
    public function primaryResponse(): ?ResponseDefinition
    {
        foreach ($this->responses as $response) {
            if ($response->isSuccessful()) {
                return $response;
            }
        }

        return $this->responses[0] ?? null;
    }

    /**
     * @return list<DocumentationGap>
     */
    public function gaps(): array
    {
        $gaps = [];

        if (blank($this->summary) && blank($this->description)) {
            $gaps[] = DocumentationGap::Description;
        }

        if ($this->responses === []) {
            $gaps[] = DocumentationGap::Responses;
        } elseif ($this->hasUndocumentedSuccessBody()) {
            $gaps[] = DocumentationGap::ResponseSchema;
        }

        if ($this->hasUndescribedParameter()) {
            $gaps[] = DocumentationGap::Parameters;
        }

        return $gaps;
    }

    public function isDocumented(): bool
    {
        return $this->gaps() === [];
    }

    /**
     * Whether the explorer may send this endpoint for the user.
     */
    public function isExecutable(): bool
    {
        return $this->method->isSafe();
    }

    /**
     * Free-text match used by the endpoint search in the sidebar.
     */
    public function matches(?string $term): bool
    {
        $term = trim((string) $term);

        if ($term === '') {
            return true;
        }

        $haystacks = [$this->path, $this->summary, $this->group, $this->method->label()];

        foreach ($haystacks as $haystack) {
            if (filled($haystack) && Str::contains($haystack, $term, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }

    private function hasUndocumentedSuccessBody(): bool
    {
        foreach ($this->responses as $response) {
            if (! $response->isSuccessful()) {
                continue;
            }

            if ($response->mediaType !== null && ! $response->hasFields()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parameters the explorer inferred are skipped: an authentication header
     * read off a security scheme is not part of the document, so a missing
     * description on it says nothing about how well the API is documented.
     */
    private function hasUndescribedParameter(): bool
    {
        foreach ($this->parameters as $parameter) {
            if (! $parameter->inferred && blank($parameter->description)) {
                return true;
            }
        }

        return false;
    }
}
