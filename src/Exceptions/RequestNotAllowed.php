<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Exceptions;

use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use RuntimeException;

/**
 * Thrown when the explorer refuses to send a request. Sending is deliberately
 * narrow: it exists to try documented read-only endpoints of this API, not to
 * act as a general-purpose HTTP client.
 *
 * Every refusal carries its message twice: once in English for a log, and once
 * as a translation key for the reader, who is being told what to do about it and
 * should be told in the language of the panel.
 */
final class RequestNotAllowed extends RuntimeException
{
	/**
	 * @param  array<string, string>  $replacements
	 */
	private function __construct(string $message, private readonly string $key, private readonly array $replacements = [])
	{
		parent::__construct($message);
	}

	/**
	 * The refusal in the language of the panel.
	 */
	public function reason(): string
	{
		return (string) trans("filament-api-explorer::explorer.refusals.{$this->key}", $this->replacements);
	}

	public static function disabled(): self
	{
		return new self('Sending requests from the API explorer is disabled.', 'disabled');
	}

	public static function unsafeMethod(HttpMethod $method): self
	{
		return new self(
			"The API explorer only sends safe requests, so [{$method->label()}] was refused.",
			'unsafe_method',
			['method' => $method->label()],
		);
	}

	public static function hostNotAllowed(?string $host): self
	{
		return new self(
			sprintf('The host [%s] is not in the allowed hosts list.', $host ?? 'unknown'),
			'host',
			['host' => $host ?? '?'],
		);
	}

	/**
	 * @param  list<string>  $names
	 */
	public static function unresolvedPath(array $names): self
	{
		return new self(
			sprintf(
				'Fill in the path %s [%s] before sending.',
				count($names) === 1 ? 'parameter' : 'parameters',
				implode('], [', $names),
			),
			count($names) === 1 ? 'path' : 'paths',
			['names' => implode(', ', $names)],
		);
	}

	/**
	 * @param  list<string>  $names
	 */
	public static function placeholderHeader(array $names): self
	{
		return new self(
			sprintf(
				'The %s [%s] still holds the example from the documentation. Replace it with a real value.',
				count($names) === 1 ? 'header' : 'headers',
				implode('], [', $names),
			),
			count($names) === 1 ? 'header' : 'headers',
			['names' => implode(', ', $names)],
		);
	}

	public static function insecureScheme(?string $scheme): self
	{
		return new self(
			sprintf('The scheme [%s] is not allowed.', $scheme ?? 'unknown'),
			'scheme',
			['scheme' => $scheme ?? '?'],
		);
	}
}
