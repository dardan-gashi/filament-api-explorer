<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

/**
 * Follows `$ref` pointers inside one OpenAPI document.
 */
final class ReferenceResolver
{
	/**
	 * @param  array<string, mixed>  $document
	 */
	public function __construct(private readonly array $document) {}

	/**
	 * Replace a `$ref` with the schema it points at, following chains and
	 * stopping on a cycle. Keys written beside the `$ref` win over the target,
	 * which is how a specification overrides a shared schema locally.
	 *
	 * @param  array<string, mixed>  $schema
	 * @return array<string, mixed>
	 */
	public function resolve(array $schema): array
	{
		$seen = [];

		while (is_string($reference = $schema['$ref'] ?? null)) {
			if (isset($seen[$reference])) {
				break;
			}

			$seen[$reference] = true;
			$target = $this->pointer($reference);

			if ($target === null) {
				break;
			}

			unset($schema['$ref']);
			$schema = [...$target, ...$schema];
		}

		return $schema;
	}

	/**
	 * The schema a pointer addresses, or `null` when it does not resolve.
	 *
	 * @return array<string, mixed>|null
	 */
	public function pointer(string $reference): ?array
	{
		if (!str_starts_with($reference, '#/')) {
			return null;
		}

		$current = $this->document;

		foreach (explode('/', substr($reference, 2)) as $segment) {
			$segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

			if (!is_array($current) || !array_key_exists($segment, $current)) {
				return null;
			}

			$current = $current[$segment];
		}

		if (!is_array($current)) {
			return null;
		}

		return Documents::toMap($current);
	}

	/**
	 * The display name of a referenced schema, e.g. `BookResource`, taken
	 * from the schema before it is resolved.
	 *
	 * @param  array<string, mixed>  $schema
	 */
	public static function nameOf(array $schema): ?string
	{
		$reference = $schema['$ref'] ?? null;

		return is_string($reference) ? self::shortName($reference) : null;
	}

	public static function shortName(string $reference): string
	{
		return Str::afterLast($reference, '/');
	}
}
