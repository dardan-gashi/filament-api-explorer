<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

/**
 * The body an endpoint expects: the media type it is sent as and the schema
 * tree of its fields.
 *
 * The explorer documents this for every method. It still only *sends* safe
 * methods, so a body is something to read here, not something to fill in.
 */
final readonly class RequestBodyDefinition
{
	/**
	 * @param  list<SchemaField>  $fields
	 */
	public function __construct(
		public ?string $mediaType = null,
		public ?string $schemaName = null,
		public array $fields = [],
		public bool $required = false,
		public ?string $description = null,
		public ?string $example = null,
		public bool $exampleSynthesised = false,
	) {}

	public function hasFields(): bool
	{
		return $this->fields !== [];
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
