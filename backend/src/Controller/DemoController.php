<?php

namespace App\Controller;

use App\Attribute\RequiresRole;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[RequiresRole(['guest', 'user'])] // Роль по умолчанию для всего контроллера
class DemoController
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    // Наследует роль контроллера: guest или user
    public function publicInfo(Request $request): Response
    {
        // Авторизация проверяется автоматически через AuthorizationListener
        
        $userRoles = $this->jwtService->getRoles($request);
        $userId = $this->jwtService->getUserId($request);

        return new Response(json_encode([
            'message' => 'Public information available to guests and users',
            'your_roles' => $userRoles,
            'user_id' => $userId
        ]), 200, ['Content-Type' => 'application/json']);
    }

    // Переопределяет роль контроллера: только user
    #[RequiresRole('user')]
    public function userOnlyInfo(Request $request): Response
    {
        // Авторизация проверяется автоматически через AuthorizationListener
        
        $userRoles = $this->jwtService->getRoles($request);
        $userId = $this->jwtService->getUserId($request);

        return new Response(json_encode([
            'message' => 'This information is only for authenticated users',
            'your_roles' => $userRoles,
            'user_id' => $userId,
            'sensitive_data' => 'Secret user data here'
        ]), 200, ['Content-Type' => 'application/json']);
    }

    // Публичный метод без ролей (переопределяет роли класса)
    #[RequiresRole([])] // Пустой массив = без требований к ролям
    public function noRoleRequired(Request $request): Response
    {
        // Авторизация проверяется автоматически через AuthorizationListener
        
        return new Response(json_encode([
            'message' => 'This endpoint has no role requirements (but still needs JWT from Envoy)',
            'note' => 'This demonstrates method-level override of class-level roles'
        ]), 200, ['Content-Type' => 'application/json']);
    }
}