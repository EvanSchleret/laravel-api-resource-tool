<?php

namespace EvanSchleret\LaravelApiResourceTool;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Throwable;

class LaravelApiResourceToolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-api-resource-tool.php',
            'laravel-api-resource-tool',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-api-resource-tool.php' => config_path('laravel-api-resource-tool.php'),
        ], 'laravel-api-resource-tool-config');

        $this->app->afterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! method_exists($handler, 'renderable')) {
                return;
            }

            $handler->renderable(function (Throwable $exception, Request $request): ?JsonResponse {
                $validationException = ValidationException::class;

                if (
                    ! config('laravel-api-resource-tool.responses.validation_errors', false) ||
                    ! $request->expectsJson() ||
                    ! class_exists($validationException) ||
                    ! ($exception instanceof $validationException)
                ) {
                    return null;
                }

                return ResourceTool::errorResponse(
                    message: $exception->getMessage(),
                    statusCode: $exception->status,
                    code: 'VALIDATION_ERROR',
                    errors: $exception->errors(),
                );
            });
        });
    }
}
