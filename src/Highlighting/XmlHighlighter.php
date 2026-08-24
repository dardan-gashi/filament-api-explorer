<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

/**
 * XML, in the token classes every other language here already uses: the tag
 * carries the keyword colour, an attribute name the one a JSON property gets,
 * its value the string colour. A tag is matched whole and marked up from the
 * inside, which is how the attribute inside it gets a colour of its own without
 * a second pass over the document.
 */
final class XmlHighlighter
{
	private const PATTERN = '/(?P<comment><!--.*?-->)|(?P<keyword><[^>]*>)/s';

	private const INSIDE_A_TAG = '/(?P<string>"[^"]*")|(?P<property>[A-Za-z_][\w.:-]*)(?=\s*=)/';

	public static function highlight(string $xml): string
	{
		return Highlighter::markUp($xml, self::PATTERN, ['keyword' => self::INSIDE_A_TAG]);
	}
}
