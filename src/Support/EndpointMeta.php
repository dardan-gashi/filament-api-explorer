<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Turns a vendor extension into the caption shown under an endpoint title.
 *
 * A document surfaces facts the OpenAPI schema has no field for through `x-`
 * extensions: `x-handler`, `x-rate-limit`, `x-since`. Most of those values speak
 * for themselves — `VoucherController@index` needs no label. A bare `v2.0` does
 * not, so the extensions this package recognises get a worded caption and an
 * icon, and every other one is shown exactly as the document wrote it.
 */
final class EndpointMeta
{
	/**
	 * @var array<string, string>
	 */
	private const ICONS = [
		'handler' => 'heroicon-o-document-text',
		'rate-limit' => 'heroicon-o-clock',
		'since' => 'heroicon-o-bolt',
		'abilities' => 'heroicon-o-key',
	];

	public static function caption(string $key, string $value): string
	{
		$translation = "filament-api-explorer::explorer.meta.{$key}";

		return Lang::has($translation)
			? (string) __($translation, ['value' => $value])
			: $value;
	}

	/**
	 * The icon for an extension this package knows, and nothing for one it does
	 * not: a wrong icon reads as a claim about the value beside it.
	 */
	public static function icon(string $key): ?string
	{
		return self::ICONS[$key] ?? null;
	}
}
