<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    protected function apiKey(Request $request): ?ApiKey
    {
        return $request->attributes->get('api_key');
    }

    protected function ok($data = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    protected function created($data = []): JsonResponse
    {
        return response()->json(['data' => $data], 201);
    }

    protected function deleted(): JsonResponse
    {
        return response()->json(['message' => 'Deleted successfully.']);
    }

    protected function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
