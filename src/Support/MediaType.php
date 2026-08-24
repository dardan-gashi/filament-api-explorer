<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

/**
 * What a media type is, as far as this page has to care.
 *
 * Only the suffix decides: `application/vnd.api+json` is JSON and
 * `application/atom+xml` is XML, so a vendor type is not treated as an unknown
 * format merely for having a name of its own.
 */
final class MediaType
{
	public static function isJson(?string $mediaType): bool
	{
		return $mediaType === null || Str::contains($mediaType, 'json', ignoreCase: true);
	}

	public static function isXml(?string $mediaType): bool
	{
		return $mediaType !== null && Str::contains($mediaType, 'xml', ignoreCase: true);
	}

	/**
	 * A media type short enough for a tab. `application/` is the prefix nearly
	 * every API type carries and the one part that tells no two of them apart;
	 * everything else stays as written, because `text/csv` and `image/png` are
	 * distinguished by exactly the half a shortening would drop.
	 */
	public static function label(?string $mediaType): string
	{
		return $mediaType === null ? '' : Str::after($mediaType, 'application/');
	}
}
