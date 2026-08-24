<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

/**
 * A body in one of the media types it is offered in.
 *
 * OpenAPI writes a body as a map of media type to schema, so an endpoint that
 * answers both JSON and XML documents each of them: usually the same shape
 * twice, sometimes two shapes, because a format can carry less than the other.
 * Every one of them is parsed, and the reader decides which the page shows.
 */
final readonly class BodyRendering
{
	/**
	 * @param  list<SchemaField>  $fields
	 * @param  bool  $exampleSynthesised  Whether the example was built from the schema rather than declared by the document.
	 */
	public function __construct(
		public ?string $mediaType = null,
		public ?string $schemaName = null,
		public array $fields = [],
		public ?string $example = null,
		public bool $exampleSynthesised = false,
	) {}

	/**
	 * The rendering in the asked-for media type, or the preferred one where the
	 * body is not offered in it: a reader who put the endpoint into XML still
	 * gets to read a body that exists only as JSON — as JSON, and labelled as
	 * such, rather than as XML it never is.
	 *
	 * @param  list<self>  $alternates
	 */
	public static function pick(?string $mediaType, self $preferred, array $alternates): self
	{
		if ($mediaType === null) {
			return $preferred;
		}

		foreach ([$preferred, ...$alternates] as $rendering) {
			if ($rendering->mediaType !== null && strcasecmp($rendering->mediaType, $mediaType) === 0) {
				return $rendering;
			}
		}

		return $preferred;
	}

	/**
	 * @param  list<self>  $alternates
	 * @return list<string>
	 */
	public static function mediaTypesOf(self $preferred, array $alternates): array
	{
		$types = [];

		foreach ([$preferred, ...$alternates] as $rendering) {
			if ($rendering->mediaType !== null) {
				$types[] = $rendering->mediaType;
			}
		}

		return $types;
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
