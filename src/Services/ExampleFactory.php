<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\MediaType;
use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;
use DardanGashi\FilamentApiExplorer\Support\Xml;

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
	 * The example for one media type entry of a request or password body, written
	 * in the format that media type was declared under.
	 *
	 * @param  array<string, mixed>  $mediaType
	 * @param  string|null  $name  The media type itself, e.g. `application/xml`.
	 */
	public function forMediaType(array $mediaType, ReferenceResolver $references, ?string $name = null): ?string
	{
		if (array_key_exists('example', $mediaType)) {
			return $this->encode($mediaType['example'], $name);
		}

		$first = Documents::first(Documents::map($mediaType, 'examples'));

		if (is_array($first) && array_key_exists('value', $first)) {
			return $this->encode($first['value'], $name);
		}

		$schema = Documents::map($mediaType, 'schema');

		if ($schema === []) {
			return null;
		}

		return $this->encode(
			$this->forSchema($schema, $references),
			$name,
			$references->resolve($schema),
		);
	}

	/**
	 * Whether the values in the example came out of the document, as opposed to
	 * being invented to satisfy a type. The page says which of the two it is
	 * showing, and "no real values" beside real values is the worse of the two
	 * mistakes it can make.
	 *
	 * A media type can declare the whole payload, and a schema can carry the
	 * values leaf by leaf — the shape is ours in that case, but every value in it
	 * is the document's, which is not a made-up example.
	 *
	 * @param  array<string, mixed>  $mediaType
	 */
	public function hasDocumentedExample(array $mediaType, ReferenceResolver $references): bool
	{
		if (array_key_exists('example', $mediaType)) {
			return true;
		}

		$first = Documents::first(Documents::map($mediaType, 'examples'));

		if (is_array($first) && array_key_exists('value', $first)) {
			return true;
		}

		return $this->schemaSuppliesValues(Documents::map($mediaType, 'schema'), $references);
	}

	/**
	 * @param  array<string, mixed>  $schema
	 */
	private function schemaSuppliesValues(array $schema, ReferenceResolver $references, int $depth = 1): bool
	{
		if ($schema === [] || $depth > $this->maxDepth) {
			return false;
		}

		$schema = $references->resolve($schema);

		if (array_key_exists('example', $schema) || Documents::listFirst($schema, 'examples') !== null) {
			return true;
		}

		foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
			foreach (Documents::list($schema, $keyword) as $branch) {
				if ($this->schemaSuppliesValues(Documents::toMap($branch), $references, $depth + 1)) {
					return true;
				}
			}
		}

		foreach (Documents::map($schema, 'properties') as $property) {
			if ($this->schemaSuppliesValues(Documents::toMap($property), $references, $depth + 1)) {
				return true;
			}
		}

		return $this->schemaSuppliesValues(Documents::map($schema, 'items'), $references, $depth + 1);
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

		$documented = Documents::listFirst($schema, 'examples');

		if ($documented !== null) {
			return $documented;
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

		if (!is_string($type)) {
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

	/**
	 * @param  array<string, mixed>  $schema  The schema behind the value, read for
	 *                                        the name its root element should take.
	 */
	private function encode(mixed $value, ?string $mediaType = null, array $schema = []): string
	{
		// A document that wrote its own example wrote it in its own format, and
		// re-encoding it would be us overruling the document.
		if (is_string($value)) {
			return $value;
		}

		if (MediaType::isXml($mediaType)) {
			return Xml::encode($value, $this->rootName($schema));
		}

		return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			?: '';
	}

	/**
	 * What the root element is called: what the document says under `xml`, else
	 * the schema's own title, else a word that says what it is.
	 *
	 * @param  array<string, mixed>  $schema
	 */
	private function rootName(array $schema): string
	{
		return Documents::string(Documents::map($schema, 'xml'), 'name')
			?? Documents::string($schema, 'title')
			?? 'response';
	}
}
