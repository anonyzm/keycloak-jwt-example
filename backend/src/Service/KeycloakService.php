<?php
namespace App\Service;

class KeycloakService 
{
    private $keycloakUrl;
    private $realm;
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->keycloakUrl = getenv('KEYCLOAK_URL');
        $this->realm = getenv('KEYCLOAK_REALM');
        $this->clientId = getenv('KEYCLOAK_CLIENT_ID');
        $this->clientSecret = getenv('KEYCLOAK_CLIENT_SECRET');
    }
    
    // Проверяем существование пользователя
    public function userExists($phone) 
    {
        $token = $this->getServiceToken();
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users?username=' . urlencode($phone);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $response = $this->httpGet($url, $headers);
        return !empty($response);
    }

    // Создаем пользователя
    public function createUser($phone)   
    {
        $token = $this->getAdminToken();
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users';
        
        $userId = 'user_' . str_replace('+', '', $phone);
        
        $userData = [
            'username' => $phone,
            'enabled' => true,
            'email' => $phone . '@temp.domain',
            'emailVerified' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => 'SMS_AUTH_ONLY',
                'temporary' => false
            ]],
            'attributes' => [
                'phone' => $phone,
                'user_id' => $userId
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        return $this->httpPost($url, $userData, $headers);
    }
    
    // Получаем токен для пользователя
    public function getUserToken($phone) 
    {
        $url = $this->keycloakUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $phone,
            'password' => 'SMS_AUTH_ONLY',
            'scope' => 'openid'
        ];
        
        // Для токенов используем form-encoded формат
        return $this->httpPostForm($url, $data);
    }

    // Получаем гостевой токен
    public function getGuestToken() 
    {
        $url = $this->keycloakUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => 'guest-user',
            'password' => 'GUEST_ACCESS',
            'scope' => 'openid'
        ];
        
        // Для токенов используем form-encoded формат
        return $this->httpPostForm($url, $data);
    }

    // Назначаем роль пользователю
    public function assignUserRole($username, $roleName)
    {
        $this->log("Attempting to assign role '{$roleName}' to user '{$username}'");
        
        $token = $this->getServiceToken();
        if (!$token) {
            $this->log("Failed to get service token");
            return ['error' => 'Failed to get service token'];
        }
        
        // Получаем ID пользователя
        $userId = $this->getUserId($username, $token);
        if (!$userId) {
            $this->log("User '{$username}' not found");
            return ['error' => 'User not found'];
        }
        
        $this->log("Found user ID: {$userId}");
        
        // Получаем роль
        $role = $this->getRole($roleName, $token);
        if (!$role || isset($role['error'])) {
            $this->log("Role '{$roleName}' not found or invalid: " . json_encode($role));
            return ['error' => 'Role not found: ' . $roleName];
        }
        
        $this->log("Found role: " . json_encode($role));
        
        // Проверяем, что роль имеет правильную структуру
        if (!isset($role['id']) || !isset($role['name'])) {
            $this->log("Invalid role structure: " . json_encode($role));
            return ['error' => 'Invalid role structure'];
        }
        
        // Назначаем роль
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users/' . $userId . '/role-mappings/realm';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $this->log("Assigning role via URL: {$url}");
        $result = $this->httpPost($url, [$role], $headers);
        
        if (isset($result['error'])) {
            $this->log("Failed to assign role: " . json_encode($result));
        } else {
            $this->log("Successfully assigned role '{$roleName}' to user '{$username}'");
        }
        
        return $result;
    }

    // Обновляем роль пользователя с guest на user (для существующего пользователя)
    public function upgradeGuestToUser($phone)
    {
        $token = $this->getServiceToken();
        
        // Получаем ID пользователя по телефону
        $userId = $this->getUserId($phone, $token);
        if (!$userId) {
            return ['error' => 'User not found'];
        }
        
        // Обновляем атрибуты пользователя
        $userIdAttr = 'user_' . str_replace('+', '', $phone);
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users/' . $userId;
        
        $userData = [
            'attributes' => [
                'phone' => $phone,
                'user_id' => $userIdAttr,
                'user_type' => 'authenticated'
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        // Обновляем атрибуты пользователя
        $this->httpPut($url, $userData, $headers);
        
        // Удаляем роль guest, если она есть
        $this->removeUserRole($phone, 'guest');
        
        // Назначаем роль user
        $this->assignUserRole($phone, 'user');
        
        // Получаем новый токен для обновленного пользователя
        return $this->getUserToken($phone);
    }


    // --------------------private-methods--------------------

    // Получаем токен для сервисного аккаунта
    private function getServiceToken() 
    {
        $url = $this->keycloakUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        
        // Для токенов используем form-encoded формат
        $response = $this->httpPostForm($url, $data);
        return $response['access_token'];
    }
    
    public function getAdminToken() 
    {
        $url = $this->keycloakUrl . '/realms/master/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => 'admin-cli', // Специальный клиент
            'username' => 'admin',      // Логин админа
            'password' => 'admin'       // Пароль админа
        ];
        
        // Для токенов используем form-encoded формат
        $response = $this->httpPostForm($url, $data);
        return $response['access_token'];
    }
        
    // Получаем ID пользователя по имени
    private function getUserId($username, $token)
    {
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users?username=' . urlencode($username);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $users = $this->httpGet($url, $headers);
        return !empty($users) ? $users[0]['id'] : null;
    }

    // Получаем роль по имени
    private function getRoles($token)
    {
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/roles';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $response = $this->httpGet($url, $headers);
        
        return $response;
    }

    private function getRole($roleName, $token) 
    {
        $role = null;
        $roles = $this->getRoles($token);
        foreach ($roles as $item) {
            if ($item['name'] === $roleName) {
                $role = $item;
            }
        }

         // Проверяем, что ответ не содержит ошибку
         if (isset($role['error'])) {
            return ['error' => 'Role not found: ' . $roleName];
        }
        
        // Проверяем, что роль имеет необходимые поля
        if (!$role || !isset($role['id']) || !isset($role['name'])) {
            return ['error' => 'Invalid role data'];
        }

        return $role;
    }

    // Удаляем роль у пользователя
    private function removeUserRole($username, $roleName)
    {
        $token = $this->getServiceToken();
        $userId = $this->getUserId($username, $token);
        $role = $this->getRole($roleName, $token);
        
        if (!$userId || !$role) {
            return ['error' => 'User or role not found'];
        }
        
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users/' . $userId . '/role-mappings/realm';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        return $this->httpDelete($url, [$role], $headers);
    }

    // HTTP-клиент для JSON данных
    private function httpPost($url, $data, $headers = []) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Всегда кодируем данные в JSON
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
    
    // HTTP-клиент для form-encoded данных (для токенов)
    private function httpPostForm($url, $data, $headers = []) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Используем form-encoded формат для токенов
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
    
    private function httpGet($url, $headers = []) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => 'cURL error: ' . $error];
        }
        
        if ($httpCode >= 400) {
            return ['error' => 'HTTP error: ' . $httpCode];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response'];
        }
        
        return $decoded;
    }

    private function httpPut($url, $data, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        
        // Всегда кодируем данные в JSON
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }

    private function httpDelete($url, $data = null, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        
        if ($data) {
            // Всегда кодируем данные в JSON
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
    
    // Метод для логирования (можно заменить на PSR Logger)
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] KeycloakService: {$message}" . PHP_EOL;
        
        // Логируем в файл или stdout для отладки
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        } else {
            error_log($logMessage);
        }
    }
}