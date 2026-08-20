<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

/**
 * Keeps credentials out of the code samples.
 *
 * A user may paste a real token into the request panel to try an endpoint, but
 * the copyable snippet beside it must stay safe to share, so credential-bearing
 * headers are rendered as a variable reference instead of their value.
 */
final class SecretHeaders
{
	/**
	 * @var list<string>
	 */
	private const NAME_HINTS = [
		'authorization',
		'cookie',
		'key',
		'password',
		'secret',
		'signature',
		'token',
	];

	/**
	 * @var list<string>
	 */
	private const AUTH_SCHEMES = ['Bearer', 'Basic', 'Digest'];

	public static function isSecret(string $name): bool
	{
		$name = strtolower($name);

		foreach (self::NAME_HINTS as $hint) {
			if (str_contains($name, $hint)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Swap the credential for a placeholder, keeping the auth scheme so the
	 * sample still shows the expected header shape.
	 */
	public static function redact(string $value, string $placeholder): string
	{
		foreach (self::AUTH_SCHEMES as $scheme) {
			if (Str::startsWith($value, $scheme.' ')) {
				return $scheme.' '.$placeholder;
			}
		}

		return $placeholder;
	}

	/**
	 * @param  array<string, string>  $headers
	 * @return array<string, string>
	 */
	public static function redactAll(array $headers, string $placeholder): array
	{
		$redacted = [];

		foreach ($headers as $name => $value) {
			$redacted[$name] = self::isSecret($name)
				? self::redact($value, $placeholder)
				: $value;
		}

		return $redacted;
	}
}
