<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * The request as it goes over the wire.
 *
 * The one sample that belongs to no language, and the one that travels
 * furthest: it runs as it stands in the HTTP client of PhpStorm and in the REST
 * Client of VS Code, it imports into Postman, and it is what belongs in a ticket
 * when a request has to be described to somebody else.
 */
final class HttpSnippet extends Snippet
{
	public function language(): SnippetLanguage
	{
		return SnippetLanguage::Http;
	}

	/**
	 * The form both editors read as a variable of their environment file, so a
	 * pasted sample runs as soon as the token is defined there.
	 */
	protected function secretPlaceholder(): string
	{
		return '{{token}}';
	}

	protected function build(RequestBlueprint $blueprint): string
	{
		[$authority, $target] = $this->split($blueprint->fullUrl());

		$lines = [
			strtoupper($blueprint->method->value).' '.$target.' HTTP/1.1',
			'Host: '.$authority,
		];

		foreach ($this->headerEntries($blueprint) as $header) {
			$lines[] = $header['name'].': '.$header['value'];
		}

		return $this->lines($lines);
	}

	/**
	 * The authority and the request target of a URL, cut apart at the first
	 * slash of the path. What is wanted is the two halves exactly as they were
	 * written, placeholders and all.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function split(string $url): array
	{
		$withoutScheme = (string) preg_replace('#^[A-Za-z][A-Za-z0-9+.-]*://#', '', $url);
		$slash = strpos($withoutScheme, '/');

		return $slash === false
			? [$withoutScheme, '/']
			: [substr($withoutScheme, 0, $slash), substr($withoutScheme, $slash)];
	}
}
