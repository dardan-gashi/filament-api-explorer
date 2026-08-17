<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Turns a parameter name into a key that is safe to bind a Livewire input to.
 *
 * Parameter names such as `filter[code]` cannot be used directly: Livewire
 * reads square brackets in a `wire:model` expression as nested property access,
 * so binding to them would write to the wrong place. The short hash keeps two
 * names that flatten to the same characters apart.
 */
final class InputKey
{
    public static function for(string $name): string
    {
        $flattened = trim((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $name), '_');

        return ($flattened === '' ? 'value' : $flattened).'_'.substr(md5($name), 0, 6);
    }
}
