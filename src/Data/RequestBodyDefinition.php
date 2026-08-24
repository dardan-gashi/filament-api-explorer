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
	 * @param  list<BodyRendering>  $alternates  The same body in the other media types it may be sent as.
	 */
	public function __construct(
		public ?string $mediaType = null,
		public ?string $schemaName = null,
		public array $fields = [],
		public bool $required = false,
		public ?string $description = null,
		public ?string $example = null,
		public bool $exampleSynthesised = false,
		public array $alternates = [],
	) {}

	/**
	 * The body as the document writes it in one media type, falling back to the
	 * preferred one where that type is not among them.
	 */
	public function renderedAs(?string $mediaType): BodyRendering
	{
		return BodyRendering::pick($mediaType, $this->preferredRendering(), $this->alternates);
	}

	public function preferredRendering(): BodyRendering
	{
		return new BodyRendering(
			mediaType: $this->mediaType,
			schemaName: $this->schemaName,
			fields: $this->fields,
			example: $this->example,
			exampleSynthesised: $this->exampleSynthesised,
		);
	}

	/**
	 * @return list<string>
	 */
	public function mediaTypes(): array
	{
		return BodyRendering::mediaTypesOf($this->preferredRendering(), $this->alternates);
	}

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
