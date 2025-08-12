# Исправление проблемы с Refresh Token

## Проблема
При попытке обновить токен через refresh-token получается ошибка:
```json
{
    "error": "invalid_grant",
    "error_description": "Invalid token issuer. Expected 'http://localhost:8080/realms/myrealm'"
}
```

## Причина
Проблема в несоответствии URL между:
- Backend получает токены от `http://keycloak:8080` (внутренний Docker адрес)
- Refresh token содержит issuer `http://localhost:8080/realms/myrealm` (внешний адрес)
- Keycloak ожидает issuer `http://localhost:8080/realms/myrealm`

## Решение

### 1. Обновление переменных окружения
В `docker-compose.yml` изменена переменная для backend:
```yaml
# Было
- KEYCLOAK_URL=http://keycloak:8080

# Стало  
- KEYCLOAK_URL=http://localhost:8080
```

### 2. Обновление конфигурации Keycloak
В `docker/keycloak/keycloak.conf` изменен URL:
```conf
# Было
spi-hostname-default-frontend-url=http://keycloak:8080

# Стало
spi-hostname-default-frontend-url=http://localhost:8080
```

### 3. Обновление переменных окружения Keycloak
В `docker-compose.yml` для Keycloak:
```yaml
# Было
KC_SPI_HOSTNAME_DEFAULT_FRONTEND_URL: http://keycloak:8080

# Стало
KC_SPI_HOSTNAME_DEFAULT_FRONTEND_URL: http://localhost:8080
```

### 4. Добавление атрибутов в realm.json
Добавлены настройки для правильной работы refresh tokens:
```json
"attributes": {
    "frontendUrl": "http://localhost:8080"
},
"refreshTokenMaxReuse": 0,
"attributes": {
    "access.token.lifespan": "300",
    "refresh.token.lifespan": "1800"
}
```

### 5. Улучшение логирования
Добавлено подробное логирование в:
- `KeycloakService::refreshToken()`
- `KeycloakService::httpPostForm()`
- `AuthController::refreshToken()`

## Тестирование

### Запуск тестового скрипта
```bash
cd backend
php ../test_refresh_token.php
```

### Проверка через API
1. Получить гостевой токен: `POST /auth/guest-token`
2. Обновить токен: `POST /auth/refresh-token` с `refresh_token`

## Перезапуск сервисов
После внесения изменений необходимо перезапустить контейнеры:
```bash
docker-compose down
docker-compose up -d
```

## Проверка логов
Для диагностики проблем смотрите логи:
```bash
# Логи backend
docker-compose logs backend

# Логи Keycloak
docker-compose logs keycloak
```

## Дополнительные настройки
Если проблема сохраняется, можно также проверить:
1. Правильность настройки CORS в Keycloak
2. Соответствие realm name в конфигурации
3. Валидность client secret
4. Настройки времени жизни токенов
