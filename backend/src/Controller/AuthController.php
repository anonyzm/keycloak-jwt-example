<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\KeycloakService;

class AuthController
{
    const TEST_PHONE = '+79123456789';
    const TEST_CODE = '123456';

    public function __construct(
        private KeycloakService $keycloakService
    ) {
    }
    
    public function requestCode(Request $request): Response
    {
        $phone = $request->get('phone');
        $content = json_encode(['message' => 'Code requested', 'phone' => $phone]);        
        return new Response($content, 200, ['Content-Type' => 'application/json']);
    }

    public function login(Request $request): Response
    {
        $requestBody = json_decode($request->getContent(), true);
        $phone = $requestBody['phone'];
        $code = $requestBody['code'];

        // Валидация кода
        if ($phone !== self::TEST_PHONE || $code !== self::TEST_CODE) {
            return new Response('Invalid code', 400);
        }
        
        // Проверяем существование пользователя
        if (!$this->keycloakService->userExists($phone)) {
            // Создаем при первом входе
            $this->keycloakService->createUser($phone);
        }
        // Получаем токен
        $token = $this->keycloakService->getUserToken($phone);

        $status = 200;
        $responseJson = [
            'message' => 'Login successful', 
        ];
        if (!empty($token['error'])) {
            $status = 400;
            $responseJson['error'] = $token['error'];
            $responseJson['message'] = $token['error_description'] ?? 'Unknown error';
        }
        else {
            $responseJson['token'] = $token;
        }

        $result = json_encode($responseJson); 
        return new Response($result, $status, ['Content-Type' => 'application/json']);
    }
}