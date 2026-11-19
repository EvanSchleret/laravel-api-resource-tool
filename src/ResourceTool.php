<?php

namespace EvanSchleret\LaravelApiResourceTool;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ResourceTool
{
    private static function paginationLimit(?int $paginationLimit): int
    {
        if ($paginationLimit !== null) {
            return $paginationLimit;
        }

        return max(1, (int) config('laravel-api-resource-tool.pagination.default_per_page', 21));
    }

    private static function makeJson(JsonResponse $response): JsonResponse
    {
        $payload = (array) $response->getData(true);
        $wrapper = JsonResource::$wrap ?? 'data';
        $dataKey = array_key_exists($wrapper, $payload)
            ? $wrapper
            : (array_key_exists('data', $payload) ? 'data' : null);
        $additional = $dataKey !== null
            ? array_diff_key($payload, [$dataKey => true])
            : array_intersect_key($payload, ['links' => true, 'meta' => true]);

        return response()->json(array_merge($additional, [
            'status' => 'success',
            'message' => '',
            'data' => $dataKey !== null ? $payload[$dataKey] : $payload,
        ]), $response->getStatusCode());
    }

    public static function paginate(Request $request, JsonResource $resource, Builder|Relation|\Laravel\Scout\Builder $builder, ?int $paginationLimit = null): JsonResponse
    {
        return ResourceTool::makeJson(
            $resource::collection(
                $builder
                    ->paginate(ResourceTool::paginationLimit($paginationLimit))
                    ->withQueryString()
            )
                ->toResponse($request)
        );
    }

    public static function cursorPaginate(Request $request, JsonResource $resource, Builder|Relation $builder, ?int $paginationLimit = null): JsonResponse
    {
        return ResourceTool::makeJson(
            $resource::collection(
                $builder
                    ->cursorPaginate(ResourceTool::paginationLimit($paginationLimit))
                    ->withQueryString()
            )
                ->toResponse($request)
        );
    }

    public static function collection(Request $request, JsonResource $resource, Builder|Relation|Collection $builder): JsonResponse
    {
        if ($builder instanceof Builder || $builder instanceof Relation) {
            $builder = $builder->get();
        }

        return ResourceTool::makeJson(
            $resource::collection($builder)->toResponse($request)
        );
    }

    public static function make(Request $request, JsonResource $resource): JsonResponse
    {
        return ResourceTool::makeSuccessResponse($resource->resolve($request));
    }

    public static function created(Request $request, JsonResource $resource, ?string $message = null): JsonResponse
    {
        return ResourceTool::makeSuccessResponse($resource->resolve($request), $message, 201);
    }

    public static function content(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ResourceTool::makeSuccessResponse($data, $message);
    }

    public static function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ResourceTool::makeSuccessResponse($data, $message, 202);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }

    private static function makeSuccessResponse(mixed $data, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message ?? '',
            'data' => $data,
        ], $statusCode);
    }

    public static function successResponse(?string $message = null, ?array $data = []): JsonResponse
    {
        return ResourceTool::content($data, $message);
    }

    public static function errorResponse(?string $message = null, int $statusCode = 500, ?string $code = null, ?array $errors = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message ?? '',
            'data' => null,
            'code' => $code,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
