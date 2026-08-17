<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Enums;

enum SnippetLanguage: string
{
    case Curl = 'curl';
    case Php = 'php';
    case JavaScript = 'js';

    public function label(): string
    {
        return match ($this) {
            self::Curl => 'curl',
            self::Php => 'PHP',
            self::JavaScript => 'JS',
        };
    }

    public static function default(): self
    {
        return self::Curl;
    }
}
