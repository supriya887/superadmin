<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if (!$this->isApiRequest($request)) {
                return null;
            }

            $firstMessage = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $firstMessage ?: 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });
    }

    protected function isApiRequest(Request $request): bool
    {
        return $request->is('api/*')
            || str_contains('/' . ltrim($request->path(), '/') . '/', '/api/')
            || $request->routeIs('api.*');
    }
}
