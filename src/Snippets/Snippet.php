<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Contracts\RequestSnippet;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Support\SecretHeaders;

/**
 * Shared behaviour of the code samples: blank entries are dropped and
 * credentials are replaced by a language-appropriate variable reference.
 */
abstract class Snippet implements RequestSnippet
{
	public function render(RequestBlueprint $blueprint): string
	{
		return $this->build($blueprint->withoutBlanks());
	}

	abstract protected function build(RequestBlueprint $blueprint): string;

	/**
	 * The variable reference that stands in for a credential, e.g. `$TOKEN`.
	 */
	abstract protected function secretPlaceholder(): string;

	/**
	 * The headers to render, with credentials already replaced. Each entry
	 * reports whether it carries a placeholder, because a language has to
	 * quote an interpolated value differently from a literal one.
	 *
	 * @return list<array{name: string, value: string, secret: bool}>
	 */
	protected function headerEntries(RequestBlueprint $blueprint): array
	{
		$entries = [];

		foreach ($blueprint->headers as $name => $value) {
			$secret = SecretHeaders::isSecret($name);

			$entries[] = [
				'name' => $name,
				'value' => $secret ? SecretHeaders::redact($value, $this->secretPlaceholder()) : $value,
				'secret' => $secret,
			];
		}

		return $entries;
	}

	/**
	 * A value as a single-quoted literal. PHP, JavaScript and Python spell one
	 * the same way, and every sample that carries a value carries it quoted.
	 */
	protected function quote(string $value): string
	{
		return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
	}

	/**
	 * Join the lines of a sample. Blank entries are kept, because they are the
	 * paragraph breaks that make a sample readable, and the newline is always
	 * `\n` so a copied sample looks the same wherever it is pasted.
	 *
	 * @param  list<string>  $lines
	 */
	protected function lines(array $lines): string
	{
		return implode("\n", $lines);
	}
}
