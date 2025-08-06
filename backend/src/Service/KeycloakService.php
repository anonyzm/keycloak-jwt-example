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
        $token = $this->getServiceToken();
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm . '/users';
        
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
                'phone' => $phone
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
        
        return $this->httpPost($url, $data);
    }

    // Получаем токен для пользователя
    public function getUserList($phone) 
    {
        $url = $this->keycloakUrl . '/admin/realms/' . $this->realm.'/users';
        
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
        
        $response = $this->httpPost($url, $data);
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
        
        $response = $this->httpPost($url, $data);
        return $response['access_token'];
    }
        
    // HTTP-клиент
    private function httpPost($url, $data, $headers = []) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        
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
}