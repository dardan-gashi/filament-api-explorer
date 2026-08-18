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
    /**
     * The language this snippet renders in.
     */
    public function language(): SnippetLanguage;

    /**
     * Render the request as source code.
     */
    public function render(RequestBlueprint $blueprint): string;
}
