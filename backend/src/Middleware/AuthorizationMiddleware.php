<?php

namespace App\Middleware;

use App\Attribute\RequiresRole;
use App\Service\JwtService;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    /**
     * Проверяет авторизацию для контроллера и метода
     */
    public function checkAuthorization(Request $request, string $controllerClass, string $methodName): ?Response
    {
        try {
            $requiredRoles = $this->getRequiredRoles($controllerClass, $methodName);
            
            // Если роли не требуются, доступ разрешен
            if (empty($requiredRoles)) {
                return null;
            }
            
            // Проверяем наличие требуемых ролей
            if (!$this->jwtService->hasAnyRole($request, $requiredRoles)) {
                return $this->createForbiddenResponse($request, $requiredRoles);
            }
            
            return null; // Доступ разрешен
            
        } catch (\Exception $e) {
            return new Response(
                json_encode(['error' => 'Authorization check failed', 'message' => $e->getMessage()]),
                500,
                ['Content-Type' => 'application/json']
            );
        }
    }

    /**
     * Получает требуемые роли из атрибутов класса и метода
     */
    private function getRequiredRoles(string $controllerClass, string $methodName): array
    {
        $requiredRoles = [];
        
        try {
            $reflectionClass = new ReflectionClass($controllerClass);
            $reflectionMethod = new ReflectionMethod($controllerClass, $methodName);
            
            // Проверяем атрибуты на уровне класса
            $classAttributes = $reflectionClass->getAttributes(RequiresRole::class);
            foreach ($classAttributes as $attribute) {
                $roleAttribute = $attribute->newInstance();
                $requiredRoles = array_merge($requiredRoles, $roleAttribute->getRoles());
            }
            
            // Проверяем атрибуты на уровне метода (приоритет выше)
            $methodAttributes = $reflectionMethod->getAttributes(RequiresRole::class);
            if (!empty($methodAttributes)) {
                $requiredRoles = []; // Сбрасываем роли класса, если есть роли метода
                foreach ($methodAttributes as $attribute) {
                    $roleAttribute = $attribute->newInstance();
                    $requiredRoles = array_merge($requiredRoles, $roleAttribute->getRoles());
                }
            }
            
        } catch (\ReflectionException $e) {
            // Если не удалось получить рефлексию, считаем что роли не требуются
        }
        
        return array_unique($requiredRoles);
    }

    /**
     * Создает ответ об отказе в доступе
     */
    private function createForbiddenResponse(Request $request, array $requiredRoles): Response
    {
        $userRoles = $this->jwtService->getRoles($request);
        
        return new Response(
            json_encode([
                'error' => 'Access denied',
                'message' => 'Insufficient privileges',
                'required_roles' => $requiredRoles,
                'user_roles' => $userRoles
            ]),
            403,
            ['Content-Type' => 'application/json']
        );
    }
}