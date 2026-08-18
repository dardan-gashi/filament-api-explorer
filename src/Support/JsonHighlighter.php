<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Marks up a JSON payload — a response body, or one of the captured samples.
 */
final class JsonHighlighter
{
    /**
     * A string a colon follows is a key rather than a value.
     */
    private const PROPERTY = '(?P<property>"(?:\\\\.|[^"\\\\])*")(?=\s*:)';

    private const STRING = '(?P<string>"(?:\\\\.|[^"\\\\])*")';

    private const LITERAL = '(?P<literal>\b(?:true|false|null)\b)';

    private const NUMBER = '(?P<number>-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)';

    /**
     * Anything the pattern does not name is punctuation or whitespace and is
     * emitted unchanged. Strings come before numbers and literals, so a digit
     * inside a string stays part of the string.
     */
    private const TOKENS = '/'.self::PROPERTY.'|'.self::STRING.'|'.self::LITERAL.'|'.self::NUMBER.'/';

    public static function highlight(string $json): string
    {
        return Highlighter::markUp($json, self::TOKENS);
    }
}
