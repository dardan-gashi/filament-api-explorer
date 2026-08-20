<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Contracts\RequestSnippet;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * Holds the registered code samples and renders one, or all, of them.
 */
final class SnippetRenderer
{
	/**
	 * @var array<string, RequestSnippet>
	 */
	private array $snippets = [];

	/**
	 * @param  iterable<RequestSnippet>  $snippets
	 */
	public function __construct(iterable $snippets = [])
	{
		foreach ($snippets as $snippet) {
			$this->register($snippet);
		}
	}

	public function register(RequestSnippet $snippet): self
	{
		$this->snippets[$snippet->language()->value] = $snippet;

		return $this;
	}

	/**
	 * The languages available, in registration order.
	 *
	 * @return list<SnippetLanguage>
	 */
	public function languages(): array
	{
		return array_values(array_map(
			fn (RequestSnippet $snippet): SnippetLanguage => $snippet->language(),
			$this->snippets,
		));
	}

	public function supports(SnippetLanguage $language): bool
	{
		return isset($this->snippets[$language->value]);
	}

	public function render(SnippetLanguage $language, RequestBlueprint $blueprint): string
	{
		return ($this->snippets[$language->value] ?? null)?->render($blueprint) ?? '';
	}

	/**
	 * @return array<string, string>
	 */
	public function renderAll(RequestBlueprint $blueprint): array
	{
		$rendered = [];

		foreach ($this->snippets as $key => $snippet) {
			$rendered[$key] = $snippet->render($blueprint);
		}

		return $rendered;
	}
}
