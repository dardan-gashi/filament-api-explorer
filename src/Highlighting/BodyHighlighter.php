<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

use DardanGashi\FilamentApiExplorer\Support\MediaType;

/**
 * The highlighter a body gets, chosen by the media type it was declared under.
 *
 * A format nobody here can read is shown plainly rather than through the rules
 * of another language: JSON colours applied to CSV invent a structure that is
 * not there, and a reader trusts colour.
 */
final class BodyHighlighter
{
	public static function highlight(string $body, ?string $mediaType = null): string
	{
		return match (true) {
			MediaType::isXml($mediaType) => XmlHighlighter::highlight($body),
			MediaType::isJson($mediaType) => JsonHighlighter::highlight($body),
			default => e($body),
		};
	}
}
