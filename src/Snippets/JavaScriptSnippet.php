<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * A sample using the browser's `fetch`, so it can be pasted straight into a
 * console or a front-end client.
 */
final class JavaScriptSnippet extends Snippet
{
    public function language(): SnippetLanguage
    {
        return SnippetLanguage::JavaScript;
    }

    protected function secretPlaceholder(): string
    {
        return '${token}';
    }

    protected function build(RequestBlueprint $blueprint): string
    {
        $lines = ['const url = new URL('.$this->quote($blueprint->url).')'];

        if ($blueprint->query !== []) {
            $lines[] = '';
            $lines[] = 'url.search = new URLSearchParams({';

            foreach ($blueprint->query as $name => $value) {
                $lines[] = '    '.$this->key($name).': '.$this->quote($value).',';
            }

            $lines[] = '}).toString()';
        }

        $headers = $this->headerEntries($blueprint);
        $lines[] = '';

        if ($headers === []) {
            $lines[] = 'const response = await fetch(url)';
        } else {
            $lines[] = 'const response = await fetch(url, {';
            $lines[] = '    headers: {';

            foreach ($headers as $header) {
                $value = $header['secret']
                    ? '`'.$header['value'].'`'
                    : $this->quote($header['value']);

                $lines[] = '        '.$this->key($header['name']).': '.$value.',';
            }

            $lines[] = '    },';
            $lines[] = '})';
        }

        return $this->lines([...$lines, '', 'const data = await response.json()']);
    }

    /**
     * Object keys stay unquoted while they are valid identifiers, which is how
     * the sample reads in idiomatic JavaScript.
     */
    private function key(string $name): string
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name) === 1
            ? $name
            : $this->quote($name);
    }
}
