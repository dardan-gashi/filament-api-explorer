<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Maps HTTP status codes onto the Filament colour names used by every status
 * badge in the explorer, so a documented `422` and a live `422` look alike.
 */
final class HttpStatus
{
	public static function color(int|string $status): string
	{
		return match (self::class_($status)) {
			1, 3 => 'info',
			2 => 'success',
			4 => 'warning',
			5 => 'danger',
			default => 'gray',
		};
	}

	public static function isSuccessful(int|string $status): bool
	{
		return self::class_($status) === 2;
	}

	/**
	 * The leading digit of the status code, or `null` for non-numeric statuses
	 * such as the OpenAPI `default` response key.
	 */
	private static function class_(int|string $status): ?int
	{
		if (!is_numeric($status)) {
			return null;
		}

		return intdiv((int) $status, 100);
	}
}
