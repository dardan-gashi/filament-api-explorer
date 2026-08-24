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
}
