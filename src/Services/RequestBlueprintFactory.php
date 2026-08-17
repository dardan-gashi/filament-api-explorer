<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

/**
 * Assembles the blueprint that both the code samples and the live sender use,
 * so what a user copies is what the explorer would send.
 */
final class RequestBlueprintFactory
{
    /**
     * @param  array<string, string>  $pathParameters
     * @param  array<string, string>  $queryParameters
     * @param  array<string, string>  $headers
     */
    public function make(
        Endpoint $endpoint,
        string $server,
        array $pathParameters = [],
        array $queryParameters = [],
        array $headers = [],
    ): RequestBlueprint {
        return new RequestBlueprint(
            method: $endpoint->method,
            url: $this->url($endpoint, $server, $pathParameters),
            query: $this->only($queryParameters, $endpoint, ParameterLocation::Query),
            headers: $this->only($headers, $endpoint, ParameterLocation::Header),
        );
    }

    /**
     * The values a request panel starts with: each documented parameter's
     * example, default or first allowed value.
     *
     * @return array<string, string>
     */
    public function suggestions(Endpoint $endpoint, ParameterLocation $in): array
    {
        $values = [];

        foreach ($endpoint->parametersIn($in) as $parameter) {
            $values[$parameter->name] = $parameter->suggestedValue() ?? '';
        }

        return $values;
    }

    /**
     * Path placeholders are replaced where a value is known and left in place
     * where it is not, so an untouched sample still reads as a template.
     *
     * @param  array<string, string>  $pathParameters
     */
    private function url(Endpoint $endpoint, string $server, array $pathParameters): string
    {
        $path = $endpoint->path;

        foreach ($endpoint->parametersIn(ParameterLocation::Path) as $parameter) {
            $value = trim($pathParameters[$parameter->name] ?? '');

            if ($value !== '') {
                $path = str_replace('{'.$parameter->name.'}', rawurlencode($value), $path);
            }
        }

        return rtrim($server, '/').'/'.ltrim($path, '/');
    }

    /**
     * Keep only the values that belong to a documented parameter of the given
     * location, so nothing a stale form left behind reaches the request.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function only(array $values, Endpoint $endpoint, ParameterLocation $in): array
    {
        $documented = array_map(
            fn (Parameter $parameter): string => $parameter->name,
            $endpoint->parametersIn($in),
        );

        $filtered = [];

        foreach ($documented as $name) {
            $value = trim($values[$name] ?? '');

            if ($value !== '') {
                $filtered[$name] = $value;
            }
        }

        return $filtered;
    }
}
