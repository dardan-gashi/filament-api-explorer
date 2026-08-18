<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * The machinery every code highlighter on this page shares.
 *
 * A highlighter is one regular expression whose named groups are its token
 * classes: a group called `string` becomes `<span class="fae-code-string">`. So
 * adding a language means writing a pattern, and the colours of a token are
 * decided once, in the stylesheet, for every language at once.
 *
 * Highlighting happens on the server, so the page needs no syntax-highlighting
 * library and nothing has to be re-highlighted when Livewire patches the DOM.
 * Every piece of the input is escaped, whether it was recognised as a token or
 * not, so the result is safe to render unescaped.
 */
final class Highlighter
{
    /**
     * @param  string  $pattern  A pattern whose named groups are token classes.
     * @param  array<string, string>  $inner  A pattern to mark up *inside* a token
     *                                        of the named class. A shell expands a
     *                                        variable inside double quotes, and
     *                                        that is exactly where a sample carries
     *                                        the credential the reader must replace.
     */
    public static function markUp(string $code, string $pattern, array $inner = []): string
    {
        if ($code === '') {
            return '';
        }

        $matched = preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if ($matched === false || $matched === 0) {
            return e($code);
        }

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            [$token, $position] = $match[0];
            $class = self::classOf($match);

            $html .= e(substr($code, $offset, $position - $offset));
            $html .= $class === null
                ? e($token)
                : self::span($class, $token, $inner[$class] ?? null);

            $offset = $position + strlen($token);
        }

        return $html.e(substr($code, $offset));
    }

    /**
     * The named group that matched, which is the name of the token class. A
     * group that did not take part reports an offset of -1.
     *
     * @param  array<array-key, array{0: string|null, 1: int}>  $match
     */
    private static function classOf(array $match): ?string
    {
        foreach ($match as $name => $capture) {
            if (is_string($name) && $capture[1] !== -1) {
                return $name;
            }
        }

        return null;
    }

    private static function span(string $class, string $token, ?string $inner): string
    {
        $content = $inner === null ? e($token) : self::markUp($token, $inner);

        return '<span class="fae-code-'.$class.'">'.$content.'</span>';
    }
}
