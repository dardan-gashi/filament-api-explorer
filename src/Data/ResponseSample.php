<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Support\Documents;

/**
 * A response the explorer recorded from a live request.
 *
 * A synthesised example describes the shape of a payload and nothing else — it
 * says `"status": "string"` where the API says `"status": "paid"`. A recorded
 * response is the real thing, which is why it outranks both the synthesised
 * example and the one the document declares.
 */
final readonly class ResponseSample
{
    public function __construct(
        public int $status,
        public string $body,
        public CarbonImmutable $capturedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'body' => $this->body,
            'captured_at' => $this->capturedAt->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): ?self
    {
        $body = Documents::string($state, 'body');
        $capturedAt = Documents::string($state, 'captured_at');

        if ($body === null || $capturedAt === null) {
            return null;
        }

        return new self(
            status: (int) (Documents::scalar($state, 'status') ?? 0),
            body: $body,
            capturedAt: CarbonImmutable::parse($capturedAt),
        );
    }
}
