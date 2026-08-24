<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\HttpStatus;
use DardanGashi\FilamentApiExplorer\Support\MediaType;
use DardanGashi\FilamentApiExplorer\Support\Xml;
use Illuminate\Support\Str;
use Livewire\Wireable;

/**
 * The outcome of a live request sent from the explorer.
 *
 * The result is held in the page's Livewire state so it survives the requests
 * that follow it — switching a snippet tab does not discard the response the
 * user just fetched.
 */
final readonly class ExecutedRequest implements Wireable
{
	/**
	 * @param  array<string, string>  $headers
	 * @param  string|null  $error  Set when the request never produced a response.
	 */
	public function __construct(
		public int $status,
		public string $body = '',
		public array $headers = [],
		public int $durationMs = 0,
		public ?string $error = null,
	) {}

	public static function failed(string $error, int $durationMs = 0): self
	{
		return new self(status: 0, durationMs: $durationMs, error: $error);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toLivewire(): array
	{
		return [
			'status' => $this->status,
			'body' => $this->body,
			'headers' => $this->headers,
			'durationMs' => $this->durationMs,
			'error' => $this->error,
		];
	}

	public static function fromLivewire(mixed $value): self
	{
		$state = Documents::toMap($value);

		return new self(
			status: (int) (Documents::scalar($state, 'status') ?? 0),
			body: (string) (Documents::scalar($state, 'body') ?? ''),
			headers: array_map(strval(...), array_filter(Documents::map($state, 'headers'), 'is_scalar')),
			durationMs: (int) (Documents::scalar($state, 'durationMs') ?? 0),
			error: Documents::string($state, 'error'),
		);
	}

	public function hasFailed(): bool
	{
		return $this->error !== null;
	}

	public function isSuccessful(): bool
	{
		return !$this->hasFailed() && HttpStatus::isSuccessful($this->status);
	}

	public function color(): string
	{
		return $this->hasFailed() ? 'danger' : HttpStatus::color($this->status);
	}

	/**
	 * The body indented when it is JSON, and untouched otherwise.
	 */
	/**
	 * What the server said it sent, without the charset it may have appended.
	 * The response decides how it is read, not what the document promised.
	 */
	public function contentType(): ?string
	{
		foreach ($this->headers as $name => $value) {
			if (strcasecmp($name, 'Content-Type') === 0) {
				return trim(Str::before($value, ';'));
			}
		}

		return null;
	}

	public function prettyBody(): string
	{
		if ($this->body === '') {
			return '';
		}

		if (MediaType::isXml($this->contentType())) {
			return Xml::format($this->body) ?? $this->body;
		}

		try {
			$decoded = json_decode($this->body, associative: true, flags: JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return $this->body;
		}

		return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			?: $this->body;
	}
}
