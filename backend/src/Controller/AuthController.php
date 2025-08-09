<?php

namespace App\Controller;

use App\Attribute\RequiresRole;
use App\Middleware\AuthorizationMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\KeycloakService;

class AuthController extends BaseController
{
    const TEST_PHONE = '+79123456789';
    const TEST_CODE = '123456';

    public function __construct(
        AuthorizationMiddleware $authMiddleware,
        private KeycloakService $keycloakService
    ) {
        parent::__construct($authMiddleware);
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
        // Проверяем авторизацию
        $authResponse = $this->checkAuthorization($request, 'requestCode');
        if ($authResponse) {
            return $authResponse;
        }
        
        $phone = $request->get('phone');
        $content = json_encode(['message' => 'Code requested', 'phone' => $phone]);        
        return new Response($content, 200, ['Content-Type' => 'application/json']);
    }

    #[RequiresRole(['guest', 'user'])]
    public function login(Request $request): Response
    {
        // Проверяем авторизацию
        $authResponse = $this->checkAuthorization($request, 'login');
        if ($authResponse) {
            return $authResponse;
        }
        
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
            // Получаем токен для нового пользователя
            $token = $this->keycloakService->getUserToken($phone);
        } else {
            // Пользователь уже существует, обновляем гостевой токен на пользовательский
            $token = $this->keycloakService->upgradeGuestToUser($phone);
        }

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