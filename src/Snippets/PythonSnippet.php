<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * A sample using `requests`, which is what a Python reader already has
 * installed.
 */
final class PythonSnippet extends Snippet
{
    public function language(): SnippetLanguage
    {
        return SnippetLanguage::Python;
    }

    protected function secretPlaceholder(): string
    {
        return '{token}';
    }

    protected function build(RequestBlueprint $blueprint): string
    {
        $lines = [
            'import requests',
            '',
            'response = requests.'.$blueprint->method->value.'(',
            '    '.$this->quote($blueprint->url).',',
        ];

        if ($blueprint->query !== []) {
            $lines[] = '    params={';

            foreach ($blueprint->query as $name => $value) {
                $lines[] = '        '.$this->quote($name).': '.$this->quote($value).',';
            }

            $lines[] = '    },';
        }

        $headers = $this->headerEntries($blueprint);

        if ($headers !== []) {
            $lines[] = '    headers={';

            foreach ($headers as $header) {
                // An interpolated value needs the `f` prefix to be interpolated
                // at all; a literal one must not have it.
                $value = $header['secret']
                    ? 'f'.$this->quote($header['value'])
                    : $this->quote($header['value']);

                $lines[] = '        '.$this->quote($header['name']).': '.$value.',';
            }

            $lines[] = '    },';
        }

        return $this->lines([...$lines, ')', '', 'data = response.json()']);
    }
}
