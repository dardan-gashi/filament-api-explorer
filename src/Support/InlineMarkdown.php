<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Renders the inline markdown a document puts into a description.
 *
 * Generators write names as code spans — Scramble describes a response as
 * `` `OrderDetailResource` `` — and a reference littered with stray backticks
 * reads worse than one with none at all.
 *
 * Only code spans are understood. Everything else is escaped and shown as it was
 * written, because a viewer that renders arbitrary markup out of a generated
 * document renders whatever somebody happens to type into a docblock.
 */
final class InlineMarkdown
{
    public static function toHtml(?string $text): string
    {
        return (string) preg_replace(
            '/`([^`]+)`/',
            '<code class="fae-inline-code">$1</code>',
            e((string) $text),
        );
    }
}
