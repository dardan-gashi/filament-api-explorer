<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Enums;

enum SnippetLanguage: string
{
    case Curl = 'curl';
    case Http = 'http';
    case Php = 'php';
    case JavaScript = 'js';
    case Python = 'python';

    public function label(): string
    {
        return match ($this) {
            self::Curl => 'curl',
            self::Http => 'HTTP',
            self::Php => 'PHP',
            self::JavaScript => 'JS',
            self::Python => 'Python',
        };
    }

    public static function default(): self
    {
        return self::Curl;
    }
}
