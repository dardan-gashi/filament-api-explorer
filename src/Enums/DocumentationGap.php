<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Enums;

/**
 * The ways an endpoint can be under-documented. An endpoint with no gaps
 * counts as documented towards the coverage figure in the page header, and
 * endpoints with at least one gap are what the "gaps" filter keeps.
 */
enum DocumentationGap: string
{
    /** Neither a summary nor a description explains what the endpoint does. */
    case Description = 'description';

    /** The endpoint documents no response at all. */
    case Responses = 'responses';

    /** A successful response is documented, but its body has no schema. */
    case ResponseSchema = 'response_schema';

    /** At least one parameter is undescribed. */
    case Parameters = 'parameters';

    /** The endpoint takes a body, but none is documented — or it has no schema. */
    case RequestBody = 'request_body';

    public function translationKey(): string
    {
        return "filament-api-explorer::explorer.gaps.{$this->value}";
    }
}
