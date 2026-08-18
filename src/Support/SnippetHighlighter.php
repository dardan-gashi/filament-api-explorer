<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * Marks up a request sample in the language it is written in.
 */
final class SnippetHighlighter
{
    public static function highlight(string $code, SnippetLanguage $language): string
    {
        return match ($language) {
            SnippetLanguage::Curl => ShellHighlighter::highlight($code),
            SnippetLanguage::Php => PhpHighlighter::highlight($code),
            SnippetLanguage::JavaScript => JavaScriptHighlighter::highlight($code),
        };
    }
}
