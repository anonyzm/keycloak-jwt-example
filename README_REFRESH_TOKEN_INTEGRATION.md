# Интеграция Refresh Token функционала

## Обзор изменений

Добавлен полный функционал для обновления JWT токенов с использованием refresh_token как на backend, так и на frontend.

## Backend изменения

### 1. KeycloakService
- **Новый метод**: `refreshToken($refreshToken)` - обновляет токен через Keycloak API
- **Логика**: Использует grant_type=refresh_token для получения нового access_token

### 2. AuthController
- **Новый эндпоинт**: `POST /api/auth/refresh-token`
- **Функциональность**: Принимает refresh_token и возвращает новый токен
- **Безопасность**: Публичный эндпоинт, не требует авторизации

### 3. Маршруты
- **Новый маршрут**: `/api/auth/refresh-token` → `AuthController::refreshToken`

## Frontend изменения

### 1. AuthService
- **Новый метод**: `refreshToken(refreshToken: string)` - обновляет токен через backend API
- **Хранение**: Автоматически сохраняет refresh_token в localStorage
- **Интерфейс**: Обновлен `TokenResponse` для поддержки refresh_token

### 2. HttpClient
- **Улучшенный interceptor**: Теперь пытается использовать refresh_token перед fallback на гостевой токен
- **Логика**: 
  1. При 401 ошибке проверяет наличие refresh_token
  2. Если есть - пытается обновить токен
  3. Если нет или не удалось - использует гостевой токен

## Архитектура решения

```
Frontend (401 ошибка) 
    ↓
Проверка refresh_token
    ↓
Если есть refresh_token → Backend /api/auth/refresh-token → Keycloak
    ↓
Новый access_token + refresh_token
    ↓
Повтор запроса с новым токеном
```

## Преимущества

1. **Лучший UX**: Пользователи не должны повторно авторизовываться
2. **Безопасность**: Refresh_token имеет ограниченный срок жизни
3. **Автоматизация**: Система сама обновляет токены при необходимости
4. **Fallback**: При отсутствии refresh_token используется гостевой токен

## Тестирование

### Backend
```bash
# Обновление токена
curl -X POST http://localhost/api/auth/refresh-token \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "your_refresh_token"}'
```

### Frontend
1. Авторизуйтесь и получите токен с refresh_token
2. Дождитесь истечения access_token
3. Сделайте запрос к защищенному эндпоинту
4. Проверьте, что токен автоматически обновился

## Безопасность

- Refresh_token хранится в localStorage (можно улучшить до httpOnly cookies)
- Автоматическая очистка при ошибках обновления
- Fallback на гостевой токен для публичных эндпоинтов
- Валидация на стороне Keycloak

## Дальнейшие улучшения

1. **HttpOnly cookies**: Более безопасное хранение refresh_token
2. **Rotating refresh tokens**: Автоматическая ротация refresh_token
3. **Token blacklisting**: Возможность отзыва токенов
4. **Rate limiting**: Ограничение частоты обновления токенов
