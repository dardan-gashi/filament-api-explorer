<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Support\HttpStatus;

/**
 * One documented response of an endpoint: its status, the media type it is
 * served as, the schema tree of its body and the headers it sets.
 *
 * A response offered in several media types keeps the preferred one in its own
 * properties and the rest in `alternates`.
 */
final readonly class ResponseDefinition
{
	/**
	 * @param  list<SchemaField>  $fields
	 * @param  list<Parameter>  $headers
	 * @param  bool  $exampleSynthesised  Whether the example was built from the schema rather than declared by the document.
	 * @param  list<BodyRendering>  $alternates  The same response in the other media types it is offered in.
	 */
	public function __construct(
		public string $status,
		public ?string $description = null,
		public ?string $mediaType = null,
		public ?string $schemaName = null,
		public array $fields = [],
		public array $headers = [],
		public ?string $example = null,
		public bool $exampleSynthesised = false,
		public array $alternates = [],
	) {}

	/**
	 * The response as the document writes it in one media type. The properties
	 * of this object are the preferred one, so a page that asks for no format in
	 * particular reads exactly as it did before any of this existed.
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
