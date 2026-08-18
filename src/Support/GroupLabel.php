<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

/**
 * Turns an OpenAPI tag into a heading a person would write.
 *
 * Generators tend to tag operations with the class that handles them, so a
 * document arrives full of tags like `BookApi`. That is a fact
 * about the code, not a section title.
 */
final class GroupLabel
{
    /**
     * Suffixes that name the implementation rather than the subject.
     */
    private const NOISE = ['Api', 'API', 'Controller', 'Resource'];

    public static function for(string $tag): string
    {
        $tag = trim($tag);
        $subject = self::withoutNoise($tag);

        // A tag that is *only* a suffix — a plain `API` — is already the best
        // caption it has, and running an acronym through a headline would spell
        // it out letter by letter.
        return $subject === null ? $tag : Str::headline($subject);
    }

    private static function withoutNoise(string $tag): ?string
    {
        foreach (self::NOISE as $suffix) {
            if (! str_ends_with($tag, $suffix)) {
                continue;
            }

            $trimmed = rtrim(substr($tag, 0, -strlen($suffix)), '-_ ');

            return $trimmed === '' ? null : $trimmed;
        }

        return $tag;
    }
}
