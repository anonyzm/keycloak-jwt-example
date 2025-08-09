<?php

namespace App\EventListener;

use App\Attribute\RequiresRole;
use App\Service\JwtService;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class AuthorizationListener
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        
        // Проверяем, что это массив [объект, метод]
        if (!is_array($controller) || count($controller) !== 2) {
            return;
        }
        
        [$controllerObject, $methodName] = $controller;
        $controllerClass = get_class($controllerObject);
        
        // Получаем требуемые роли
        $requiredRoles = $this->getRequiredRoles($controllerClass, $methodName);
        
        // Если роли не требуются, пропускаем
        if (empty($requiredRoles)) {
            return;
        }
        
        $request = $event->getRequest();
        
        // Проверяем роли
        if (!$this->jwtService->hasAnyRole($request, $requiredRoles)) {
            $userRoles = $this->jwtService->getRoles($request);
            
            $response = new JsonResponse([
                'error' => 'Access denied',
                'message' => 'Insufficient privileges',
                'required_roles' => $requiredRoles,
                'user_roles' => $userRoles
            ], 403);
            
            $event->setController(function() use ($response) {
                return $response;
            });
        }
    }

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
                $requiredRoles = []; // Сбрасываем роли класса
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
}