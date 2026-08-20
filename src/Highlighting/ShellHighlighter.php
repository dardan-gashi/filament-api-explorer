<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

/**
 * Marks up a shell command, which on this page means a curl sample.
 */
final class ShellHighlighter
{
	private const COMMENT = '(?P<comment>#[^\n]*)';

	/**
	 * Only double quotes, which is all the samples emit. A single-quoted
	 * argument stays uncoloured rather than being coloured on a guess about
	 * whether the shell would expand what is inside it.
	 */
	private const STRING = '(?P<string>"(?:\\\\.|[^"\\\\])*")';

	private const VARIABLE = '(?P<variable>\$\{\w+\}|\$\w+)';

	/**
	 * A flag, which is what tells one line of a curl invocation from the next.
	 */
	private const FLAG = '(?P<keyword>(?<=\s)--?[A-Za-z][\w-]*)';

	/**
	 * The command itself: the first word of a line. Every other line of a
	 * sample is a continuation and starts indented.
	 */
	private const COMMAND = '(?P<call>^[a-z][\w.-]*)';

	private const TOKENS = '/'.self::COMMENT.'|'.self::STRING.'|'.self::VARIABLE.'|'.self::FLAG.'|'.self::COMMAND.'/m';

	/**
	 * A shell expands a variable inside double quotes, so the credential in a
	 * sample is marked up as the thing the reader has to replace rather than as
	 * more of the surrounding string.
	 */
	private const IN_STRING = '/'.self::VARIABLE.'/';

	public static function highlight(string $code): string
	{
		return Highlighter::markUp($code, self::TOKENS, ['string' => self::IN_STRING]);
	}
}
