<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

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
	 * Marked-up code cut into one string of HTML per line, so a gutter can put a
	 * number beside each of them.
	 *
	 * A token can span a line break — a heredoc, a template literal, a quoted
	 * string a sample breaks over two lines — so the split cannot simply explode
	 * on "\n": that would leave a `<span>` open at the end of one line and its
	 * `</span>` orphaned at the start of the next. Every span still open at a
	 * break is closed and opened again, which keeps each line valid markup on its
	 * own and the colour unbroken across the break.
	 *
	 * @return list<string>
	 */
	public static function lines(string $html): array
	{
		$parts = preg_split(
			'/(<span class="[a-z-]+">|<\/span>)/',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE,
		);

		if ($parts === false) {
			return [$html];
		}

		/** @var list<string> $open */
		$open = [];
		$lines = [''];

		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}

			if ($part === '</span>') {
				array_pop($open);
				$lines[array_key_last($lines)] .= $part;

				continue;
			}

			if (str_starts_with($part, '<span')) {
				$open[] = $part;
				$lines[array_key_last($lines)] .= $part;

				continue;
			}

			foreach (explode("\n", $part) as $index => $chunk) {
				if ($index > 0) {
					$lines[array_key_last($lines)] .= str_repeat('</span>', count($open));
					$lines[] = implode('', $open);
				}

				$lines[array_key_last($lines)] .= $chunk;
			}
		}

		return $lines;
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
