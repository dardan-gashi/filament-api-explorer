<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Sources;

use Closure;
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Support\Documents;

/**
 * Builds the configured specification sources.
 *
 * Every source is one entry of `filament-api-explorer.sources`, keyed by the
 * name shown in the version picker. New drivers are registered with
 * {@see extend()} from a service provider.
 */
final class SpecSourceManager
{
    /**
     * @var array<string, Closure(string, array<string, mixed>): SpecSource>
     */
    private array $drivers = [];

    /**
     * @var array<string, SpecSource>
     */
    private array $resolved = [];

    /**
     * @param  array<string, array<string, mixed>>  $sources
     */
    public function __construct(private readonly array $sources)
    {
        $this->extend('file', fn (string $name, array $config): SpecSource => new FileSpecSource(
            name: $name,
            path: (string) ($config['path'] ?? ''),
        ));

        $this->extend('array', fn (string $name, array $config): SpecSource => new ArraySpecSource(
            name: $name,
            document: Documents::map($config, 'document'),
        ));
    }

    /**
     * Register a driver, e.g. one that generates the document on the fly.
     *
     * @param  Closure(string, array<string, mixed>): SpecSource  $resolver
     */
    public function extend(string $driver, Closure $resolver): self
    {
        $this->drivers[$driver] = $resolver;

        return $this;
    }

    /**
     * The configured source names, in configuration order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->sources);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->sources);
    }

    public function defaultName(): ?string
    {
        return $this->names()[0] ?? null;
    }

    /**
     * @throws SpecUnavailable
     */
    public function source(?string $name = null): SpecSource
    {
        $name ??= $this->defaultName();

        if ($name === null || ! $this->has($name)) {
            throw SpecUnavailable::unknownSource((string) $name);
        }

        return $this->resolved[$name] ??= $this->build($name, $this->sources[$name]);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws SpecUnavailable
     */
    private function build(string $name, array $config): SpecSource
    {
        $driver = (string) ($config['driver'] ?? 'file');

        if (! isset($this->drivers[$driver])) {
            throw SpecUnavailable::unsupportedDriver($driver);
        }

        return ($this->drivers[$driver])($name, $config);
    }
}
