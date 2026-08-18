<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Snippets;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * A sample using Laravel's HTTP client, since the readers of this page are
 * most likely calling the API from another Laravel application.
 */
final class PhpSnippet extends Snippet
{
    public function language(): SnippetLanguage
    {
        return SnippetLanguage::Php;
    }

    protected function secretPlaceholder(): string
    {
        return '$token';
    }

    protected function build(RequestBlueprint $blueprint): string
    {
        $lines = ['use Illuminate\Support\Facades\Http;', ''];
        $headers = $this->headerEntries($blueprint);

        if ($headers === []) {
            $lines[] = '$response = Http::'.$blueprint->method->value.'(';
        } else {
            $lines[] = '$response = Http::withHeaders([';

            foreach ($headers as $header) {
                $value = $header['secret']
                    ? '"'.$header['value'].'"'
                    : $this->quote($header['value']);

                $lines[] = '    '.$this->quote($header['name']).' => '.$value.',';
            }

            $lines[] = '])->'.$blueprint->method->value.'(';
        }

        $lines[] = '    '.$this->quote($blueprint->url).($blueprint->query === [] ? ',' : ', [');

        foreach ($blueprint->query as $name => $value) {
            $lines[] = '        '.$this->quote($name).' => '.$this->quote($value).',';
        }

        if ($blueprint->query !== []) {
            $lines[] = '    ],';
        }

        $lines[] = ');';

        return $this->lines([...$lines, '', '$data = $response->json();']);
    }
}
