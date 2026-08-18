<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

/**
 * A single request parameter — a query string entry, a header, a path segment
 * or a cookie. All four locations share this shape so the documentation
 * sections and the request builder can treat them alike.
 */
final readonly class Parameter
{
    /**
     * @param  list<string>  $enum
     * @param  bool  $inferred  Whether the explorer added this parameter itself — an
     *                          authentication header read off a security scheme, say —
     *                          rather than finding it in the document.
     */
    public function __construct(
        public string $name,
        public ParameterLocation $in,
        public string $type = 'string',
        public bool $required = false,
        public ?string $description = null,
        public array $enum = [],
        public string|int|float|bool|null $default = null,
        public bool $deprecated = false,
        public string|int|float|bool|null $example = null,
        public bool $inferred = false,
    ) {}

    public function hasDefault(): bool
    {
        return $this->default !== null;
    }

    /**
     * The default rendered for humans, e.g. `true` rather than `1`.
     */
    public function defaultLabel(): ?string
    {
        return $this->default === null ? null : self::scalarToString($this->default);
    }

    /**
     * The value the request builder and the code samples start from: an
     * explicit example, else the default, else the first allowed value.
     */
    public function suggestedValue(): ?string
    {
        foreach ([$this->example, $this->default, $this->enum[0] ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return self::scalarToString($candidate);
            }
        }

        return null;
    }

    private static function scalarToString(string|int|float|bool $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
