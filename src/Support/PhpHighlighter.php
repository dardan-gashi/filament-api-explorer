<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use PhpToken;

/**
 * Marks up the PHP sample.
 *
 * PHP is the one language on this page that does not need a pattern: it can be
 * asked to tokenise itself. So the sample is lexed by the same lexer that would
 * run it, which is exact where a regular expression would only be close — and it
 * keeps working when somebody registers a sample of their own.
 */
final class PhpHighlighter
{
    public static function highlight(string $code): string
    {
        if ($code === '') {
            return '';
        }

        // The lexer needs an open tag; the sample is written without one.
        $tokens = PhpToken::tokenize('<?php '.$code);
        $html = '';

        foreach ($tokens as $index => $token) {
            if ($index === 0 && $token->is(T_OPEN_TAG)) {
                continue;
            }

            $class = self::classOf($token, self::codeAfter($tokens, $index));

            $html .= $class === null
                ? e($token->text)
                : '<span class="fae-code-'.$class.'">'.e($token->text).'</span>';
        }

        return $html;
    }

    /**
     * `true`, `false` and `null` are written like any other name and read like
     * keywords, which is how every other language on this page draws them.
     */
    private static function classOf(PhpToken $token, ?PhpToken $next): ?string
    {
        return match (true) {
            $token->is([T_COMMENT, T_DOC_COMMENT]) => 'comment',
            $token->is(T_VARIABLE) => 'variable',
            $token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, '"']) => 'string',
            $token->is([T_LNUMBER, T_DNUMBER]) => 'number',
            in_array(strtolower($token->text), ['true', 'false', 'null'], true) => 'literal',
            self::isKeyword($token) => 'keyword',
            $token->is([T_STRING, T_NAME_QUALIFIED]) && $next?->text === '(' => 'call',
            default => null,
        };
    }

    /**
     * Whether a token is one of the language's own words.
     *
     * `PhpToken` cannot say so, but the tokenizer already has: every reserved
     * word is given an id of its own, and only a name is left as `T_STRING`. So
     * a token that still reads as a single word once strings, variables,
     * comments and numbers are accounted for is a keyword — `use`, `fn`, `match`
     * and whatever a hand-written sample brings with it.
     */
    private static function isKeyword(PhpToken $token): bool
    {
        return $token->id !== T_STRING
            && preg_match('/^[A-Za-z_]\\w*$/', $token->text) === 1;
    }

    /**
     * The next token that is not whitespace, which is what decides whether a
     * name is being called or only mentioned.
     *
     * @param  array<int, PhpToken>  $tokens
     */
    private static function codeAfter(array $tokens, int $index): ?PhpToken
    {
        for ($next = $index + 1; $next < count($tokens); $next++) {
            if (! $tokens[$next]->is(T_WHITESPACE)) {
                return $tokens[$next];
            }
        }

        return null;
    }
}
