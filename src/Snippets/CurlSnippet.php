<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

final class CurlSnippet extends Snippet
{
	public function language(): SnippetLanguage
	{
		return SnippetLanguage::Curl;
	}

	protected function secretPlaceholder(): string
	{
		return '$TOKEN';
	}

	protected function build(RequestBlueprint $blueprint): string
	{
		$lines = [$this->command($blueprint), '  "'.$this->escape($blueprint->url).'"'];

		foreach ($blueprint->query as $name => $value) {
			$lines[] = '  -d "'.$this->escape($name.'='.$value).'"';
		}

		foreach ($this->headerEntries($blueprint) as $header) {
			$value = $header['secret'] ? $header['value'] : $this->escape($header['value']);
			$lines[] = '  -H "'.$this->escape($header['name']).': '.$value.'"';
		}

		return $this->withContinuations($lines);
	}

	/**
	 * `-G` sends the parameters as a query string rather than as a body, which
	 * is what makes `-d` usable on a GET request.
	 */
	private function command(RequestBlueprint $blueprint): string
	{
		return match (true) {
			$blueprint->method === HttpMethod::Get && $blueprint->query !== [] => 'curl -G',
			$blueprint->method === HttpMethod::Get => 'curl',
			default => 'curl -X '.strtoupper($blueprint->method->value),
		};
	}

	/**
	 * @param  list<string>  $lines
	 */
	private function withContinuations(array $lines): string
	{
		$last = array_key_last($lines);

		return $this->lines(array_map(
			fn (string $line, int $index): string => $index === $last ? $line : $line.' \\',
			$lines,
			array_keys($lines),
		));
	}

	/**
	 * Neutralise the characters that would end the quoted argument or start a
	 * command substitution, so a pasted value cannot become a pasted command.
	 */
	private function escape(string $value): string
	{
		return str_replace(['\\', '"', '`', '$('], ['\\\\', '\\"', '\\`', '\\$('], $value);
	}
}
