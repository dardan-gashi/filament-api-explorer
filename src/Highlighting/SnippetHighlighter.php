<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

use DardanGashi\FilamentApiExplorer\Contracts\RequestSnippet;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;
use DardanGashi\FilamentApiExplorer\Services\SnippetRenderer;

/**
 * Marks up a request sample in the language it is written in.
 *
 * A language of its own takes three things, and this is where the last of them
 * is wired up:
 *
 * 1. a case on {@see SnippetLanguage} — the tab, the query string and the stored
 *    preference are all keyed on its value;
 * 2. a {@see RequestSnippet} that writes the sample, registered on the
 *    {@see SnippetRenderer} — the sample that ships for a language can be
 *    replaced the same way, to reach for a different library;
 * 3. a highlighter, added to the match below.
 *
 * The third is one regular expression: name the capture groups after the token
 * classes and {@see Highlighter} does the rest. The match stays exhaustive on
 * purpose — a new case without an arm here does not get past the analyser, which
 * is the reminder that a sample nobody highlights reads like a wall of
 * monospace.
 */
final class SnippetHighlighter
{
	public static function highlight(string $code, SnippetLanguage $language): string
	{
		return match ($language) {
			SnippetLanguage::Curl => ShellHighlighter::highlight($code),
			SnippetLanguage::Http => HttpHighlighter::highlight($code),
			SnippetLanguage::Php => PhpHighlighter::highlight($code),
			SnippetLanguage::JavaScript => JavaScriptHighlighter::highlight($code),
			SnippetLanguage::Python => PythonHighlighter::highlight($code),
		};
	}
}
