<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;

/**
 * Builds the example payload shown beside a response.
 *
 * A specification's own examples always win; only when none is given does the
 * factory synthesise one from the schema. Synthesised values are fixed rather
 * than random, so the same document always renders the same example.
 */
final class ExampleFactory
{
    private const PLACEHOLDERS = [
        'uuid' => '00000000-0000-0000-0000-000000000000',
        'date' => '2026-01-01',
        'date-time' => '2026-01-01T00:00:00+00:00',
        'email' => 'user@example.com',
        'uri' => 'https://example.com',
        'url' => 'https://example.com',
        'binary' => '<binary>',
        'password' => '********',
    ];

    public function __construct(private readonly int $maxDepth = 6) {}

    /**
     * The example for one media type entry of a request or response body.
     *
     * @param  array<string, mixed>  $mediaType
     */
    public function forMediaType(array $mediaType, ReferenceResolver $references): ?string
    {
        if (array_key_exists('example', $mediaType)) {
            return $this->encode($mediaType['example']);
        }

        $first = Documents::first(Documents::map($mediaType, 'examples'));

        if (is_array($first) && array_key_exists('value', $first)) {
            return $this->encode($first['value']);
        }

        $schema = Documents::map($mediaType, 'schema');

        if ($schema === []) {
            return null;
        }

        return $this->encode($this->forSchema($schema, $references));
    }

    /**
     * A value that satisfies the schema.
     *
     * @param  array<string, mixed>  $schema
     */
    public function forSchema(array $schema, ReferenceResolver $references, int $depth = 1): mixed
    {
        $schema = $references->resolve($schema);

        foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
            $branch = Documents::first(Documents::list($schema, $keyword));

            if ($branch !== null) {
                return $this->forSchema(Documents::toMap($branch), $references, $depth);
            }
        }

        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (array_key_exists('default', $schema)) {
            return $schema['default'];
        }

        $enum = Documents::list($schema, 'enum');

        if ($enum !== []) {
            return $enum[0];
        }

        return $this->forType($schema, $references, $depth);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function forType(array $schema, ReferenceResolver $references, int $depth): mixed
    {
        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $named = array_values(array_filter($type, fn (mixed $candidate): bool => $candidate !== 'null'));
            $type = $named[0] ?? 'null';
        }

        if (! is_string($type)) {
            $type = match (true) {
                Documents::map($schema, 'properties') !== [] => 'object',
                array_key_exists('items', $schema) => 'array',
                default => 'string',
            };
        }

        return match ($type) {
            'object' => $this->forObject($schema, $references, $depth),
            'array' => $this->forArray($schema, $references, $depth),
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            'null' => null,
            default => $this->forString($schema),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function forObject(array $schema, ReferenceResolver $references, int $depth): array
    {
        $properties = Documents::map($schema, 'properties');

        if ($depth >= $this->maxDepth || $properties === []) {
            return [];
        }

        $example = [];

        foreach (Documents::entries($properties) as [$name, $property]) {
            $example[$name] = $this->forSchema(Documents::toMap($property), $references, $depth + 1);
        }

        return $example;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<mixed>
     */
    private function forArray(array $schema, ReferenceResolver $references, int $depth): array
    {
        $items = Documents::map($schema, 'items');

        if ($depth >= $this->maxDepth || $items === []) {
            return [];
        }

        return [$this->forSchema($items, $references, $depth + 1)];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function forString(array $schema): string
    {
        $format = $schema['format'] ?? null;

        if (is_string($format) && isset(self::PLACEHOLDERS[$format])) {
            return self::PLACEHOLDERS[$format];
        }

        return 'string';
    }

    private function encode(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ?: '';
    }
}
