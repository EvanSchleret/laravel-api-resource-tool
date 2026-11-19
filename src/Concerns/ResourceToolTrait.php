<?php

namespace EvanSchleret\LaravelApiResourceTool\Concerns;

use EvanSchleret\LaravelApiResourceTool\ResourceTool as ResourceToolClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

trait ResourceToolTrait
{
    public static function paginate(Request $request, JsonResource $resource, Builder|Relation|\Laravel\Scout\Builder $builder, ?int $paginationLimit = null): JsonResponse
    {
        return ResourceToolClass::paginate($request, $resource, $builder, $paginationLimit);
    }

    public static function cursorPaginate(Request $request, JsonResource $resource, Builder|Relation $builder, ?int $paginationLimit = null): JsonResponse
    {
        return ResourceToolClass::cursorPaginate($request, $resource, $builder, $paginationLimit);
    }

    public static function collection(Request $request, JsonResource $resource, Builder|Relation|Collection $builder): JsonResponse
    {
        return ResourceToolClass::collection($request, $resource, $builder);
    }

    public static function make(Request $request, JsonResource $resource): JsonResponse
    {
        return ResourceToolClass::make($request, $resource);
    }

    public static function created(Request $request, JsonResource $resource, ?string $message = null): JsonResponse
    {
        return ResourceToolClass::created($request, $resource, $message);
    }

    public static function content(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ResourceToolClass::content($data, $message);
    }

    public static function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ResourceToolClass::accepted($data, $message);
    }

    public static function noContent(): Response
    {
        return ResourceToolClass::noContent();
    }

    public static function successResponse(?string $message = null, ?array $data = []): JsonResponse
    {
        return ResourceToolClass::successResponse($message, $data);
    }

    public static function errorResponse(?string $message = null, int $statusCode = 500, ?string $code = null, ?array $errors = null): JsonResponse
    {
        return ResourceToolClass::errorResponse($message, $statusCode, $code, $errors);
    }
}
