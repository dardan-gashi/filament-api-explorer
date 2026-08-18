<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use Illuminate\Support\Str;

/**
 * One row of a response or request schema tree.
 *
 * The field owns the search behaviour for the tree it heads: {@see matches()}
 * answers whether the term occurs anywhere below it, and {@see filter()}
 * returns the same tree pruned down to the matching branches.
 */
final readonly class SchemaField
{
    /**
     * @param  list<string>  $enum
     * @param  list<SchemaField>  $children
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?string $format = null,
        public ?string $description = null,
        public bool $required = false,
        public bool $nullable = false,
        public bool $deprecated = false,
        public array $enum = [],
        public ?string $reference = null,
        public array $children = [],
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * Whether the term occurs in this field or in any of its descendants.
     */
    public function matches(string $term): bool
    {
        if ($this->matchesSelf($term)) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->matches($term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The field with only the branches that match the term, or `null` when
     * nothing below it matches. A field that matches on its own keeps all of
     * its children, so a matched object still shows its shape.
     */
    public function filter(string $term): ?self
    {
        if ($this->matchesSelf($term)) {
            return $this;
        }

        $children = array_values(array_filter(array_map(
            fn (self $child): ?self => $child->filter($term),
            $this->children,
        )));

        if ($children === []) {
            return null;
        }

        return new self(
            name: $this->name,
            type: $this->type,
            format: $this->format,
            description: $this->description,
            required: $this->required,
            nullable: $this->nullable,
            deprecated: $this->deprecated,
            enum: $this->enum,
            reference: $this->reference,
            children: $children,
        );
    }

    /**
     * Prune a list of fields, dropping the ones with no match at all.
     *
     * @param  list<SchemaField>  $fields
     * @return list<SchemaField>
     */
    public static function filterAll(array $fields, ?string $term): array
    {
        if (blank($term)) {
            return $fields;
        }

        return array_values(array_filter(array_map(
            fn (self $field): ?self => $field->filter($term),
            $fields,
        )));
    }

    /**
     * Whether any field in the tree, at any depth, satisfies the predicate. What a
     * badge means is worth explaining where the badge occurs, and nowhere else.
     *
     * @param  list<self>  $fields
     * @param  callable(self): bool  $predicate
     */
    public static function anyIn(array $fields, callable $predicate): bool
    {
        foreach ($fields as $field) {
            if ($predicate($field) || self::anyIn($field->children, $predicate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many rows the tree renders in total, used for the "n fields" caption.
     */
    public function countRows(): int
    {
        return array_reduce(
            $this->children,
            fn (int $carry, self $child): int => $carry + $child->countRows(),
            1,
        );
    }

    private function matchesSelf(string $term): bool
    {
        $term = trim($term);

        if ($term === '') {
            return true;
        }

        foreach ([$this->name, $this->type, $this->format, $this->description, $this->reference] as $haystack) {
            if (filled($haystack) && Str::contains($haystack, $term, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }
}
