# Обновление токенов (Refresh Token)

## Описание

Добавлен новый функционал для обновления JWT токенов с использованием refresh_token. Это позволяет пользователям получать новые access_token без повторной авторизации.

## Новые методы

### KeycloakService::refreshToken()

```php
public function refreshToken($refreshToken): array
```

**Параметры:**
- `$refreshToken` (string) - refresh_token для обновления

**Возвращает:**
- Массив с новым токеном или ошибкой

**Пример использования:**
```php
$newToken = $keycloakService->refreshToken($refreshToken);
if (!empty($newToken['error'])) {
    // Обработка ошибки
} else {
    $accessToken = $newToken['access_token'];
    $refreshToken = $newToken['refresh_token'];
}
```

## Новый эндпоинт

### POST /api/auth/refresh-token

**Описание:** Обновляет JWT токен с использованием refresh_token

**Заголовки:**
```
Content-Type: application/json
```

**Тело запроса:**
```json
{
    "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Ответы:**

**Успех (200):**
```json
{
    "message": "Token refreshed successfully",
    "token": {
        "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
        "expires_in": 300,
        "token_type": "Bearer",
        "scope": "openid"
    }
}
```

**Ошибка (400):**
```json
{
    "error": "refresh_token_required",
    "message": "Refresh token is required"
}
```

**Ошибка Keycloak (400):**
```json
{
    "error": "invalid_grant",
    "message": "Invalid refresh token"
}
```

## Логика работы

1. **Получение refresh_token**: При авторизации пользователь получает access_token и refresh_token
2. **Истечение access_token**: Когда access_token истекает, пользователь может использовать refresh_token
3. **Обновление токена**: Отправляется запрос на `/api/auth/refresh-token` с refresh_token
4. **Новый токен**: Keycloak возвращает новый access_token и refresh_token
5. **Продолжение работы**: Пользователь может продолжать работу с новым токеном

## Безопасность

- **Публичный эндпоинт**: Не требует авторизации для доступа
- **Валидация**: Проверяется наличие refresh_token в запросе
- **Keycloak валидация**: Refresh_token проверяется на стороне Keycloak
- **Автоматическое обновление**: Новый refresh_token выдается автоматически

## Интеграция с фронтендом

Фронтенд может использовать этот эндпоинт для:
- Автоматического обновления токенов при 401 ошибках
- Продления сессии пользователя
- Улучшения UX (пользователь не должен повторно авторизовываться)

## Пример интеграции

```typescript
// В httpClient.ts
const refreshToken = async (refreshToken: string) => {
  try {
    const response = await axios.post('/api/auth/refresh-token', {
      refresh_token: refreshToken
    });
    return response.data.token;
  } catch (error) {
    // Обработка ошибки
    throw error;
  }
};
```

## Тестирование

1. **Получите токен**: Авторизуйтесь через `/api/auth/login`
2. **Сохраните refresh_token**: Извлеките refresh_token из ответа
3. **Обновите токен**: Отправьте POST запрос на `/api/auth/refresh-token`
4. **Проверьте ответ**: Убедитесь, что получен новый access_token

## Обработка ошибок

- **refresh_token_required**: Не передан refresh_token
- **invalid_grant**: Недействительный или истекший refresh_token
- **unauthorized_client**: Неверный client_id или client_secret
- **invalid_scope**: Неверный scope в запросе
