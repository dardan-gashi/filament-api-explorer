<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Marks up a JSON payload for display.
 *
 * Highlighting happens server-side so the page needs no syntax-highlighting
 * library. Every piece of the input is escaped, whether it was recognised as a
 * token or not, so the result is safe to render unescaped.
 */
final class JsonHighlighter
{
    /**
     * Strings (optionally followed by a colon, which makes them a key),
     * literals and numbers. Anything else is punctuation or whitespace and is
     * emitted unchanged.
     */
    private const TOKENS = '/("(?:\\\\.|[^"\\\\])*")(\s*:)?|\b(true|false|null)\b|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/';

    public static function highlight(string $json): string
    {
        if ($json === '') {
            return '';
        }

        $matched = preg_match_all(self::TOKENS, $json, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if ($matched === false || $matched === 0) {
            return e($json);
        }

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            [$token, $position] = $match[0];

            $html .= e(substr($json, $offset, $position - $offset));
            $html .= self::wrap($match);

            $offset = $position + strlen($token);
        }

        return $html.e(substr($json, $offset));
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $match
     */
    private static function wrap(array $match): string
    {
        $string = $match[1][0] ?? '';
        $colon = $match[2][0] ?? '';
        $literal = $match[3][0] ?? '';
        $number = $match[4][0] ?? '';

        if ($string !== '') {
            $class = $colon === '' ? 'fae-json-string' : 'fae-json-key';

            return self::span($class, $string).e($colon);
        }

        if ($literal !== '') {
            return self::span('fae-json-literal', $literal);
        }

        return self::span('fae-json-number', $number);
    }

    private static function span(string $class, string $token): string
    {
        return '<span class="'.$class.'">'.e($token).'</span>';
    }
}
