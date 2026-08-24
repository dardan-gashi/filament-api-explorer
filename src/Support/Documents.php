<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Narrowing helpers for reading an untrusted OpenAPI document.
 *
 * A specification is decoded JSON or YAML, so every value is `mixed` until
 * proven otherwise. These helpers turn a value into the shape the caller needs
 * and fall back to an empty one, which keeps the parser free of type juggling.
 */
final class Documents
{
	/**
	 * @return array<string, mixed>
	 */
	public static function toMap(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$map = [];

		foreach ($value as $key => $item) {
			$map[(string) $key] = $item;
		}

		return $map;
	}

	/**
	 * @return list<mixed>
	 */
	public static function toList(mixed $value): array
	{
		return is_array($value) ? array_values($value) : [];
	}

	/**
	 * The map stored under a key, e.g. the `info` object of a document.
	 *
	 * @param  array<string, mixed>  $source
	 * @return array<string, mixed>
	 */
	public static function map(array $source, string $key): array
	{
		return self::toMap($source[$key] ?? null);
	}

	/**
	 * @param  array<string, mixed>  $source
	 * @return list<mixed>
	 */
	public static function list(array $source, string $key): array
	{
		return self::toList($source[$key] ?? null);
	}

	/**
	 * A non-empty string value, with numbers accepted because YAML happily
	 * turns an unquoted version like `2.4` into a float.
	 *
	 * @param  array<string, mixed>  $source
	 */
	public static function string(array $source, string $key): ?string
	{
		$value = $source[$key] ?? null;

		if (is_string($value)) {
			return $value === '' ? null : $value;
		}

		return is_int($value) || is_float($value) ? (string) $value : null;
	}

	/**
	 * @param  array<string, mixed>  $source
	 */
	public static function scalar(array $source, string $key): string|int|float|bool|null
	{
		$value = $source[$key] ?? null;

		return is_scalar($value) ? $value : null;
	}

	/**
	 * The first entry of a JSON *array* under this key, or null when the key holds
	 * anything else.
	 *
	 * OpenAPI 3.1 replaced a schema's single `example` with an `examples` array,
	 * and a generator that writes 3.1 — Scramble does — puts its values there. The
	 * same word means something else one level up: a media type's `examples` is a
	 * map of Example Objects, so the list check is what tells the two apart.
	 *
	 * @param  array<string, mixed>  $source
	 */
	public static function listFirst(array $source, string $key): mixed
	{
		$value = $source[$key] ?? null;

		if (!is_array($value) || $value === [] || !array_is_list($value)) {
			return null;
		}

		return $value[0];
	}

	/**
	 * @param  array<string, mixed>  $source
	 */
	public static function isTrue(array $source, string $key): bool
	{
		return ($source[$key] ?? false) === true;
	}

	/**
	 * A map's entries with every key as a string.
	 *
	 * PHP stores a numeric string key as an integer, so iterating a document
	 * hands back `200` where the specification wrote `"200"`. Iterating through
	 * this helper keeps keys the strings they are meant to be.
	 *
	 * @param  array<string, mixed>  $map
	 * @return list<array{0: string, 1: mixed}>
	 */
	public static function entries(array $map): array
	{
		$entries = [];

		foreach ($map as $key => $value) {
			$entries[] = [(string) $key, $value];
		}

		return $entries;
	}

	/**
	 * The keys of a map, as strings.
	 *
	 * @param  array<string, mixed>  $map
	 * @return list<string>
	 */
	public static function keys(array $map): array
	{
		return array_map(strval(...), array_keys($map));
	}

	/**
	 * The first entry of a map or list, or `null` when it is empty.
	 *
	 * @param  array<array-key, mixed>  $values
	 */
	public static function first(array $values): mixed
	{
		$key = array_key_first($values);

		return $key === null ? null : $values[$key];
	}
}
