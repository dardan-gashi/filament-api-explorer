<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Sources;

use Carbon\CarbonImmutable;
use JsonException;
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Support\Documents;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads an OpenAPI document from the local filesystem, in either JSON or YAML.
 */
final class FileSpecSource implements SpecSource
{
    public function __construct(
        private readonly string $name,
        private readonly string $path,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        if (! $this->exists()) {
            throw SpecUnavailable::missingFile($this->path);
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw SpecUnavailable::unreadableFile($this->path);
        }

        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'json' => $this->decodeJson($contents),
            'yaml', 'yml' => $this->decodeYaml($contents),
            default => throw SpecUnavailable::unsupportedExtension($this->path),
        };
    }

    public function generatedAt(): ?CarbonImmutable
    {
        if (! $this->exists()) {
            return null;
        }

        $timestamp = @filemtime($this->path);

        return $timestamp === false ? null : CarbonImmutable::createFromTimestamp($timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $contents): array
    {
        try {
            $decoded = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw SpecUnavailable::invalidFile($this->path, $exception->getMessage());
        }

        return $this->assertDocument($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeYaml(string $contents): array
    {
        try {
            $decoded = Yaml::parse($contents);
        } catch (ParseException $exception) {
            throw SpecUnavailable::invalidFile($this->path, $exception->getMessage());
        }

        return $this->assertDocument($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertDocument(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw SpecUnavailable::invalidFile($this->path, 'the document is not an object.');
        }

        return Documents::toMap($decoded);
    }
}
