<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class JwtService
{
    /**
     * Извлекает JWT токен из заголовка Authorization
     */
    public function extractToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        
        return substr($authHeader, 7); // Убираем "Bearer "
    }

    /**
     * Декодирует JWT токен (без проверки подписи, так как Envoy уже проверил)
     */
    public function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        // Декодируем payload (вторая часть токена)
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        
        return json_decode($payload, true);
    }

    /**
     * Получает роли из токена
     */
    public function getRoles(Request $request): array
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return [];
        }
        
        $payload = $this->decodeToken($token);
        
        if (!$payload) {
            return [];
        }
        
        // Роли могут быть в разных местах в зависимости от настроек Keycloak
        return $payload['realm_access']['roles'] ?? 
               $payload['roles'] ?? 
               [];
    }

    /**
     * Получает user_id из токена
     */
    public function getUserId(Request $request): ?string
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return null;
        }
        
        $payload = $this->decodeToken($token);
        
        if (!$payload) {
            return null;
        }
        
        return $payload['user_id'] ?? null;
    }

    /**
     * Получает полную информацию из токена
     */
    public function getTokenPayload(Request $request): ?array
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return null;
        }
        
        return $this->decodeToken($token);
    }

    /**
     * Проверяет, есть ли у пользователя требуемая роль
     */
    public function hasRole(Request $request, string $role): bool
    {
        $roles = $this->getRoles($request);
        return in_array($role, $roles);
    }

    /**
     * Проверяет, есть ли у пользователя любая из требуемых ролей
     */
    public function hasAnyRole(Request $request, array $requiredRoles): bool
    {
        $userRoles = $this->getRoles($request);
        return !empty(array_intersect($userRoles, $requiredRoles));
    }
}