<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Auth\AuthenticationException;
use App\Http\Traits\HttpResponses;

class Handler extends ExceptionHandler
{
        use HttpResponses;
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
    }

     protected function unauthenticated($request, AuthenticationException $exception)
    {
        $this->error(null, __('messages.unauthorized'));
    }

    public function render($request, Throwable $e)
{
    if ($e instanceof TokenExpiredException) {
        return response()->json([
            'success' => false,
            'message' => 'Token expired'
        ], 401);
    }

    if ($e instanceof TokenInvalidException) {
        return response()->json([
            'success' => false,
            'message' => 'Token invalid'
        ], 401);
    }

    return parent::render($request, $e);
}
}
