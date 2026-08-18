<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Exceptions;

use RuntimeException;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;

/**
 * Thrown when the explorer refuses to send a request. Sending is deliberately
 * narrow: it exists to try documented read-only endpoints of this API, not to
 * act as a general-purpose HTTP client.
 */
final class RequestNotAllowed extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('Sending requests from the API explorer is disabled.');
    }

    public static function unsafeMethod(HttpMethod $method): self
    {
        return new self("The API explorer only sends safe requests, so [{$method->label()}] was refused.");
    }

    public static function hostNotAllowed(?string $host): self
    {
        return new self(sprintf('The host [%s] is not in the allowed hosts list.', $host ?? 'unknown'));
    }

    /**
     * @param  list<string>  $names
     */
    public static function unresolvedPath(array $names): self
    {
        return new self(sprintf(
            'Fill in the path %s [%s] before sending.',
            count($names) === 1 ? 'parameter' : 'parameters',
            implode('], [', $names),
        ));
    }

    public static function insecureScheme(?string $scheme): self
    {
        return new self(sprintf('The scheme [%s] is not allowed.', $scheme ?? 'unknown'));
    }
}
