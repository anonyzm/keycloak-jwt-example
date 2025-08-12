<?php

namespace App\Controller;

use App\Attribute\RequiresRole;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\KeycloakService;

class AuthController
{
    private array $log = [];

    const TEST_PHONE = '+79123456789';
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
            $this->log('User does not exist');
            // Создаем при первом входе
            $result = $this->keycloakService->createUser($phone);
            $this->log('User created: ' . json_encode($result));
            // Назначаем роль пользователю
            $result =   $this->keycloakService->assignUserRole($phone, 'user');
            $this->log('User role assigned: ' . json_encode($result));
        } 
        
        $token = $this->keycloakService->upgradeGuestToUser($phone);
        $this->log('Token upgraded: ' . json_encode($token));

        $status = 200;
        $responseJson = [
            'message' => 'Login successful', 
            'log' => $this->getLog(),
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

    // Публичный эндпоинт - не требует роли
    public function refreshToken(Request $request): Response
    {
        $requestBody = json_decode($request->getContent(), true);
        $refreshToken = $requestBody['refresh_token'] ?? null;

        if (!$refreshToken) {
            $responseJson = [
                'error' => 'refresh_token_required',
                'message' => 'Refresh token is required'
            ];
            return new Response(json_encode($responseJson), 400, ['Content-Type' => 'application/json']);
        }

        $this->log('Attempting to refresh token with: ' . substr($refreshToken, 0, 20) . '...');
        
        $token = $this->keycloakService->refreshToken($refreshToken);
        
        $status = 200;
        $responseJson = [
            'message' => 'Token refreshed successfully',
            'log' => $this->getLog()
        ];
        
        if (!empty($token['error'])) {
            $status = 400;
            $responseJson['error'] = $token['error'];
            $responseJson['message'] = $token['error_description'] ?? 'Failed to refresh token';
            $this->log('Token refresh failed: ' . json_encode($token));
        } else {
            $responseJson['token'] = $token;
            $this->log('Token refresh successful');
        }

        $result = json_encode($responseJson); 
        return new Response($result, $status, ['Content-Type' => 'application/json']);
    }

    // -------------private-methods-------------
    
    private function log($message)
    {
        $this->log[] = $message;
    }

    private function getLog()
    {
        return $this->log;
    }
}