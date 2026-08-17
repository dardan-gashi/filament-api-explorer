<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Sources;

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;

/**
 * An in-memory document. Handy for tests, and as the return value of a custom
 * driver that has already built the specification itself.
 */
final class ArraySpecSource implements SpecSource
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        private readonly string $name,
        private readonly array $document,
        private readonly ?CarbonImmutable $generatedAt = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->document;
    }

    public function generatedAt(): ?CarbonImmutable
    {
        return $this->generatedAt;
    }
}
