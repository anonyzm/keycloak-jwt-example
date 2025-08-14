<?php

namespace App\Controller;

use App\Attribute\RequiresRole;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\KeycloakService;

class AuthController
{
    const TEST_CODE = '123456';

    public function __construct(
        private KeycloakService $keycloakService
    ) {
    }
    
    // Публичный эндпоинт - не требует роли
    public function getGuestToken(Request $request): Response
    {
        $token = $this->keycloakService->getGuestToken();
        
        $status = 200;
        $responseJson = [
            'message' => 'Guest token issued', 
        ];
        
        if (!empty($token['error'])) {
            $status = 400;
            $responseJson['error'] = $token['error'];
            $responseJson['message'] = $token['error_description'] ?? 'Unknown error';
        } else {
            $responseJson['token'] = $token;
        }

        $result = json_encode($responseJson); 
        return new Response($result, $status, ['Content-Type' => 'application/json']);
    }
    
    #[RequiresRole(['guest', 'user'])]
    public function requestCode(Request $request): Response
    {
        // Авторизация проверяется автоматически через AuthorizationListener
        
        $phone = $request->get('phone');
        $content = json_encode(['message' => 'Code requested', 'phone' => $phone]);        
        return new Response($content, 200, ['Content-Type' => 'application/json']);
    }

    #[RequiresRole(['guest', 'user'])]
    public function login(Request $request): Response
    {
        // Авторизация проверяется автоматически через AuthorizationListener
        
        $requestBody = json_decode($request->getContent(), true);
        $phone = $requestBody['phone'];
        $code = $requestBody['code'];

        // Валидация кода
        if ($code !== self::TEST_CODE) {
            return new Response('Invalid code', 400);
        }
        
        // Проверяем существование пользователя
        if (!$this->keycloakService->userExists($phone)) {
            // Создаем при первом входе
            $this->keycloakService->createUser($phone);
            // Назначаем роль user новому пользователю
            $this->keycloakService->assignUserRole($phone, roleName: 'user');
        }
        
        // Пользователь уже существует, обновляем гостевой токен на пользовательский
        $token = $this->keycloakService->upgradeGuestToUser($phone);
        
        $status = 200;
        $responseJson = [
            'message' => 'Login successful', 
        ];
        if (!empty($token['error'])) {
            $status = 400;
            $responseJson['error'] = $token['error'];
            $responseJson['message'] = $token['error_description'] ?? 'Unknown error';
        } else {
            $responseJson['token'] = $token;
        }

        $result = json_encode($responseJson); 
        return new Response($result, $status, ['Content-Type' => 'application/json']);
    }
}