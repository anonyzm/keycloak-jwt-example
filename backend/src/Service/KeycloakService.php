<?php
namespace App\Service;

class KeycloakService 
{
    public function getKeycloakToken($username)    
    {
        $url = getenv('KEYCLOAK_URL').'/realms/'.getenv('KEYCLOAK_REALM').'/protocol/openid-connect/token';
        $data = [
            'grant_type' => 'password',
            'client_id' => getenv('KEYCLOAK_CLIENT_ID'),
            'client_secret' => getenv('KEYCLOAK_CLIENT_SECRET'),
            'username' => $username,
            'password' => 'SMS_AUTH_ONLY' // Фиксированный пароль
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        return json_decode($response, true);
    }
}