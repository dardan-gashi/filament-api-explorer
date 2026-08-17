<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Contracts;

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;

/**
 * Renders a request as copyable source code in one language.
 */
interface RequestSnippet
{
    public function language(): SnippetLanguage;

    public function render(RequestBlueprint $blueprint): string;
}
