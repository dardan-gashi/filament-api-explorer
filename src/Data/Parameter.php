<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

/**
 * A single request parameter — a query string entry, a header, a path segment
 * or a cookie. All four locations share this shape so the documentation
 * sections and the request builder can treat them alike.
 */
final readonly class Parameter
{
	/**
	 * @param  list<string>  $enum
	 * @param  bool  $inferred  Whether the explorer added this parameter itself — an
	 *                          authentication header read off a security scheme, say —
	 *                          rather than finding it in the document.
	 */
	public function __construct(
		public string $name,
		public ParameterLocation $in,
		public string $type = 'string',
		public bool $required = false,
		public ?string $description = null,
		public array $enum = [],
		public string|int|float|bool|null $default = null,
		public bool $deprecated = false,
		public string|int|float|bool|null $example = null,
		public bool $inferred = false,
	) {}

	/**
	 * The authentication scheme a documented example prescribes — `Bearer` for
	 * `Bearer <token>`.
	 *
	 * This is what a header field means to a person: they hold a token, not an
	 * HTTP header value. Pasting the token alone produced a header the API could
	 * not read and a 401 nobody could explain, so the explorer states the scheme
	 * and adds it back.
	 */
	public function headerScheme(): ?string
	{
		if ($this->in !== ParameterLocation::Header || !is_string($this->example)) {
			return null;
		}

		return preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+\S/', $this->example, $matches) === 1
			? $matches[1]
			: null;
	}

	/**
	 * The credential part of the documented example, which is what the input
	 * asks for: `<token>` out of `Bearer <token>`.
	 */
	public function credentialPlaceholder(): ?string
	{
		$scheme = $this->headerScheme();

		if ($scheme === null) {
			return $this->suggestedValue();
		}

		return trim(substr((string) $this->example, strlen($scheme)));
	}

	/**
	 * The value with its documented scheme in front, unless it is already there.
	 */
	public function withScheme(string $value): string
	{
		$scheme = $this->headerScheme();

		if ($scheme === null || $value === '') {
			return $value;
		}

		return str_starts_with(strtolower($value), strtolower($scheme).' ')
			? $value
			: $scheme.' '.$value;
	}

	public function hasDefault(): bool
	{
		return $this->default !== null;
	}

	/**
	 * The default rendered for humans, e.g. `true` rather than `1`.
	 */
	public function defaultLabel(): ?string
	{
		return $this->default === null ? null : self::scalarToString($this->default);
	}

	/**
	 * The value the request builder and the code samples start from: an
	 * explicit example, else the default, else the first allowed value.
	 */
	public function suggestedValue(): ?string
	{
		foreach ([$this->example, $this->default, $this->enum[0] ?? null] as $candidate) {
			if ($candidate !== null && $candidate !== '') {
				return self::scalarToString($candidate);
			}
		}

		return null;
	}

	private static function scalarToString(string|int|float|bool $value): string
	{
		return match (true) {
			is_bool($value) => $value ? 'true' : 'false',
			default => (string) $value,
		};
	}
}
