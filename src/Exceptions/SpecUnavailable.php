<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Exceptions;

use RuntimeException;

/**
 * Thrown when a configured specification cannot be loaded. The explorer page
 * catches this and renders an empty state instead of failing the panel.
 */
final class SpecUnavailable extends RuntimeException
{
    public static function missingFile(string $path): self
    {
        return new self("No OpenAPI document at [{$path}].");
    }

    public static function unreadableFile(string $path): self
    {
        return new self("The OpenAPI document at [{$path}] could not be read.");
    }

    public static function invalidFile(string $path, string $reason): self
    {
        return new self("The OpenAPI document at [{$path}] could not be parsed: {$reason}");
    }

    public static function unsupportedExtension(string $path): self
    {
        return new self("The OpenAPI document at [{$path}] must be a .json, .yaml or .yml file.");
    }

    public static function unknownSource(string $name): self
    {
        return new self("No OpenAPI source named [{$name}] is configured.");
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new self("No OpenAPI source driver named [{$driver}] is registered.");
    }
}
