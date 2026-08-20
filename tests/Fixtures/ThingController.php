<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Tests\Fixtures;

use Illuminate\Http\JsonResponse;

class ThingController
{
	/**
	 * List every thing.
	 *
	 * Supports filtering and sorting through query parameters.
	 */
	public function index(): JsonResponse
	{
		return response()->json(['data' => []]);
	}

	/**
	 * Read one thing.
	 *
	 * @deprecated Use the collection endpoint and filter it.
	 */
	public function show(string $thing): JsonResponse
	{
		return response()->json(['id' => $thing]);
	}

	/**
	 * Delete one thing.
	 *
	 * Deleting is permanent and cannot be undone.
	 *
	 * @deprecated Archive it instead.
	 */
	public function destroy(string $thing): JsonResponse
	{
		return response()->json(['id' => $thing]);
	}
}
