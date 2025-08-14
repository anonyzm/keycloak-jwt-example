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
        $phone = $this->trimPhone($phone);
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
        
        $phone = $this->trimPhone($phone);
        $userId = 'user_' . $phone;
        
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
        $phone = $this->trimPhone($phone);

        $data = [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $phone,
            'password' => 'SMS_AUTH_ONLY',
            'scope' => 'openid'
        ];
        
        return $this->httpPost($url, $data);
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
        
        return $this->httpPost($url, $data);
    }

    // Назначаем роль пользователю
    public function assignUserRole($phone, $roleName)
    {
        $token = $this->getServiceToken();
        $phone = $this->trimPhone($phone);

        // Получаем ID пользователя
        $userId = $this->getUserId($phone, $token);
        if (!$userId) {
            return ['error' => 'User not found'];
        }
        
        // Получаем роль
        $role = $this->getRole($roleName, $token);
        if (!$role || isset($role['error'])) {
            return ['error' => 'Role not found: ' . $roleName];
        }
        
        // Проверяем, что роль имеет правильную структуру
        if (!isset($role['id']) || !isset($role['name'])) {
            return ['error' => 'Invalid role structure'];
        }
        
        // Назначаем роль
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users/' . $userId . '/role-mappings/realm';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        return $this->httpPost($url, [$role], $headers);
    }

    // Обновляем роль пользователя с guest на user (для существующего пользователя)
    public function upgradeGuestToUser($phone)
    {
        $token = $this->getServiceToken();
        $phone = $this->trimPhone($phone);
        
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
    
    // Получаем токен для сервисного аккаунта
    public function getServiceToken() 
    {
        $url = $this->keycloakUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        
        $response = $this->httpPost($url, $data);
        return $response['access_token'];
    }
    
    // Получаем токен для админа
    public function getAdminToken() 
    {
        $url = $this->keycloakUrl . '/realms/master/protocol/openid-connect/token';
        
        $data = [
            'grant_type' => 'password',
            'client_id' => 'admin-cli', // Специальный клиент
            'username' => 'admin',      // Логин админа
            'password' => 'admin'       // Пароль админа
        ];
        
        $response = $this->httpPost($url, $data);
        return $response['access_token'];
    }

    // --------------------private-methods--------------------

    private function trimPhone($phone)
    {
        return trim(str_replace('+', '', $phone));
    }
    
    // Получаем ID пользователя по имени
    private function getUserId($phone, $token)
    {
        $phone = $this->trimPhone($phone);
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users?username=' . urlencode($phone);
        
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
    private function removeUserRole($phone, $roleName)
    {
        $token = $this->getServiceToken();
        $phone = $this->trimPhone($phone);
        $userId = $this->getUserId($phone, $token);
        $role = $this->getRole($roleName, $token);
        
        if (!$userId || !$role) {
            return ['error' => 'User or role not found'];
        }
        
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users/' . $userId . '/role-mappings/realm';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        return $this->httpDelete($url, json_encode([$role]), $headers);
    }

    // ---------------------http-methods--------------------
    // TODO: ЗАМЕНИТЬ НА GUZZLE

    // HTTP-клиент
    private function httpPost($url, $data, $headers = []) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // Проверяем, нужно ли отправлять как JSON
        $isJsonRequest = false;
        foreach ($headers as $header) {
            if (strpos($header, 'Content-Type: application/json') !== false) {
                $isJsonRequest = true;
                break;
            }
        }
        
        if ($isJsonRequest && is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
        
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
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        return json_decode($response, true);
    }

    private function httpPut($url, $data, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        
        // Проверяем, нужно ли отправлять как JSON
        $isJsonRequest = false;
        foreach ($headers as $header) {
            if (strpos($header, 'Content-Type: application/json') !== false) {
                $isJsonRequest = true;
                break;
            }
        }
        
        if ($isJsonRequest && is_array($data)) {
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
            // Проверяем, нужно ли отправлять как JSON
            $isJsonRequest = false;
            foreach ($headers as $header) {
                if (strpos($header, 'Content-Type: application/json') !== false) {
                    $isJsonRequest = true;
                    break;
                }
            }
            
            if ($isJsonRequest && is_array($data)) {
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
}