<?php

namespace App\Controller;

use App\Middleware\AuthorizationMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController
{
    public function __construct(
        protected AuthorizationMiddleware $authMiddleware
    ) {
    }

    /**
     * Проверяет авторизацию перед выполнением действия
     */
    protected function checkAuthorization(Request $request, string $methodName): ?Response
    {
        return $this->authMiddleware->checkAuthorization($request, static::class, $methodName);
    }
}