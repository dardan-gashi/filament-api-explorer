<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Support\HttpStatus;

/**
 * One documented response of an endpoint: its status, the media type it is
 * served as, the schema tree of its body and the headers it sets.
 */
final readonly class ResponseDefinition
{
    /**
     * @param  list<SchemaField>  $fields
     * @param  list<Parameter>  $headers
     */
    public function __construct(
        public string $status,
        public ?string $description = null,
        public ?string $mediaType = null,
        public ?string $schemaName = null,
        public array $fields = [],
        public array $headers = [],
        public ?string $example = null,
    ) {}

    public function isSuccessful(): bool
    {
        return HttpStatus::isSuccessful($this->status);
    }

    public function color(): string
    {
        return HttpStatus::color($this->status);
    }

    public function hasFields(): bool
    {
        return $this->fields !== [];
    }

    public function hasHeaders(): bool
    {
        return $this->headers !== [];
    }

    /**
     * The body fields narrowed to a search term.
     *
     * @return list<SchemaField>
     */
    public function filteredFields(?string $term): array
    {
        return SchemaField::filterAll($this->fields, $term);
    }
}
