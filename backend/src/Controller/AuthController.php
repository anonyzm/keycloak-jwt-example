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
        $content = json_encode(['message' => 'Code requested']);        
        return new Response($content);
    }

    public function login(Request $request): Response
    {
        $content = json_decode($request->getContent(), true);
        $phone = $content['phone'];
        $code = $content['code'];

        if ($phone !== self::TEST_PHONE || $code !== self::TEST_CODE) {
            return new Response('Invalid code', 400);
        }

        $jwt = $this->keycloakService->getKeycloakToken($phone);
        $content = json_encode(['message' => 'Login successful', 'jwt' => $jwt]);        
        return new Response($content);
    }
}