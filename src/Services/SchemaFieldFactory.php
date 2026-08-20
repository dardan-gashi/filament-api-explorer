<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Data\SchemaField;
use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;

/**
 * Turns an OpenAPI schema into the field tree the explorer renders.
 *
 * Composition keywords are reduced to something displayable: `allOf` branches
 * are merged, and `oneOf`/`anyOf` fall back to their first branch, which is
 * what a reader wants to see first.
 */
final class SchemaFieldFactory
{
	/**
	 * The synthetic name given to a body that is not an object, so the row
	 * still has a label to render.
	 */
	public const ROOT_FIELD_NAME = 'body';

	public function __construct(private readonly int $maxDepth = 6) {}

	/**
	 * The top-level rows of a body schema. An object contributes its
	 * properties, anything else contributes a single row.
	 *
	 * @param  array<string, mixed>  $schema
	 * @return list<SchemaField>
	 */
	public function rootFields(array $schema, ReferenceResolver $references): array
	{
		if ($schema === []) {
			return [];
		}

		$resolved = $this->flatten($schema, $references);

		if (Documents::map($resolved, 'properties') !== []) {
			return $this->propertyFields($resolved, $references, depth: 1, seenReferences: $this->seenWith([], $schema));
		}

		return [
			$this->field(
				name: ReferenceResolver::nameOf($schema) ?? self::ROOT_FIELD_NAME,
				schema: $schema,
				references: $references,
			),
		];
	}

	/**
	 * @param  array<string, mixed>  $schema
	 * @param  list<string>  $seenReferences
	 */
	public function field(
		string $name,
		array $schema,
		ReferenceResolver $references,
		bool $required = false,
		bool $optional = false,
		int $depth = 1,
		array $seenReferences = [],
	): SchemaField {
		$reference = ReferenceResolver::nameOf($schema);
		$resolved = $this->flatten($schema, $references);

		[$type, $nullable] = $this->typeAndNullability($resolved);

		$items = $type === 'array'
			? $this->flatten(Documents::map($resolved, 'items'), $references)
			: [];

		if ($type === 'array') {
			[$itemType] = $this->typeAndNullability($items);
			$type = "array<{$itemType}>";
		}

		$isRecursive = $reference !== null && in_array($reference, $seenReferences, true);

		$children = $isRecursive || $depth >= $this->maxDepth
			? []
			: $this->childrenFor($resolved, $items, $references, $depth + 1, $this->seenWith($seenReferences, $schema));

		return new SchemaField(
			name: $name,
			type: $type,
			format: Documents::string($resolved, 'format'),
			description: Documents::string($resolved, 'description'),
			required: $required,
			optional: $optional,
			nullable: $nullable,
			deprecated: Documents::isTrue($resolved, 'deprecated'),
			enum: $this->enumValues($resolved),
			reference: $reference,
			children: $children,
		);
	}

	/**
	 * Children come from an object's properties, or from the properties of an
	 * array's item schema so a collection shows the shape of one entry.
	 *
	 * @param  array<string, mixed>  $resolved
	 * @param  array<string, mixed>  $items
	 * @param  list<string>  $seenReferences
	 * @return list<SchemaField>
	 */
	private function childrenFor(
		array $resolved,
		array $items,
		ReferenceResolver $references,
		int $depth,
		array $seenReferences,
	): array {
		if (Documents::map($resolved, 'properties') !== []) {
			return $this->propertyFields($resolved, $references, $depth, $seenReferences);
		}

		if (Documents::map($items, 'properties') !== []) {
			return $this->propertyFields($items, $references, $depth, $seenReferences);
		}

		return [];
	}

	/**
	 * @param  array<string, mixed>  $schema
	 * @param  list<string>  $seenReferences
	 * @return list<SchemaField>
	 */
	private function propertyFields(array $schema, ReferenceResolver $references, int $depth, array $seenReferences): array
	{
		$required = $this->requiredNames($schema);
		$fields = [];

		foreach (Documents::entries(Documents::map($schema, 'properties')) as [$name, $property]) {
			$names = in_array($name, $required, true);

			$fields[] = $this->field(
				name: $name,
				schema: Documents::toMap($property),
				references: $references,
				required: $names,
				// A schema that names its required fields says, by leaving one out,
				// that it can be absent. One that names none says nothing at all,
				// and guessing would put a badge on every row.
				optional: $required !== [] && !$names,
				depth: $depth,
				seenReferences: $seenReferences,
			);
		}

		return $fields;
	}

	/**
	 * Reduce composition keywords to a single displayable schema.
	 *
	 * @param  array<string, mixed>  $schema
	 * @return array<string, mixed>
	 */
	private function flatten(array $schema, ReferenceResolver $references): array
	{
		$schema = $references->resolve($schema);

		foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
			$branches = Documents::list($schema, $keyword);

			if ($branches === []) {
				continue;
			}

			unset($schema[$keyword]);

			$merged = $keyword === 'allOf'
				? $this->mergeBranches($branches, $references)
				: $this->flatten(Documents::toMap($branches[0]), $references);

			$schema = $this->mergeSchemas($merged, $schema);
		}

		return $schema;
	}

	/**
	 * @param  list<mixed>  $branches
	 * @return array<string, mixed>
	 */
	private function mergeBranches(array $branches, ReferenceResolver $references): array
	{
		$merged = [];

		foreach ($branches as $branch) {
			$merged = $this->mergeSchemas($merged, $this->flatten(Documents::toMap($branch), $references));
		}

		return $merged;
	}

	/**
	 * Merge two schemas, combining `properties` and `required` rather than
	 * letting one side replace the other.
	 *
	 * @param  array<string, mixed>  $base
	 * @param  array<string, mixed>  $overrides
	 * @return array<string, mixed>
	 */
	private function mergeSchemas(array $base, array $overrides): array
	{
		$properties = [
			...Documents::map($base, 'properties'),
			...Documents::map($overrides, 'properties'),
		];

		$required = array_values(array_unique([
			...$this->requiredNames($base),
			...$this->requiredNames($overrides),
		]));

		$merged = [...$base, ...$overrides];

		if ($properties !== []) {
			$merged['properties'] = $properties;
		}

		if ($required !== []) {
			$merged['required'] = $required;
		}

		return $merged;
	}

	/**
	 * @param  array<string, mixed>  $schema
	 * @return array{0: string, 1: bool}
	 */
	private function typeAndNullability(array $schema): array
	{
		$nullable = Documents::isTrue($schema, 'nullable');
		$type = $schema['type'] ?? null;

		if (is_array($type)) {
			$declared = array_map(strval(...), Documents::toList($type));
			$named = array_values(array_filter($declared, fn (string $candidate): bool => $candidate !== 'null'));
			$nullable = $nullable || count($named) !== count($declared);
			$type = $named[0] ?? null;
		}

		if (!is_string($type) || $type === '') {
			$type = match (true) {
				Documents::map($schema, 'properties') !== [] => 'object',
				array_key_exists('items', $schema) => 'array',
				default => 'mixed',
			};
		}

		return [$type, $nullable];
	}

	/**
	 * @param  array<string, mixed>  $schema
	 * @return list<string>
	 */
	private function requiredNames(array $schema): array
	{
		return array_values(array_map(
			strval(...),
			array_filter(Documents::list($schema, 'required'), 'is_scalar'),
		));
	}

	/**
	 * @param  array<string, mixed>  $schema
	 * @return list<string>
	 */
	private function enumValues(array $schema): array
	{
		return array_map(
			fn (mixed $value): string => match (true) {
				is_bool($value) => $value ? 'true' : 'false',
				is_scalar($value) => (string) $value,
				$value === null => 'null',
				default => (string) json_encode($value),
			},
			Documents::list($schema, 'enum'),
		);
	}

	/**
	 * Track which referenced schemas are already on the current branch, so a
	 * self-referencing schema stops instead of recursing forever.
	 *
	 * @param  list<string>  $seenReferences
	 * @param  array<string, mixed>  $schema
	 * @return list<string>
	 */
	private function seenWith(array $seenReferences, array $schema): array
	{
		$reference = ReferenceResolver::nameOf($schema);

		return $reference === null ? $seenReferences : [...$seenReferences, $reference];
	}
}
