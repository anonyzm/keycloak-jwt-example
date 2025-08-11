# Отладка Interceptor для 401 ошибок

## Проблема
Interceptor для автоматической обработки 401 ошибок не работает - при получении 401 кода ничего не происходит.

## Что было исправлено

### 1. Убраны дублирующие interceptor'ы
- В `authService.ts` были собственные axios interceptor'ы
- В `httpClient.ts` были другие interceptor'ы
- Это создавало конфликт

### 2. Все запросы теперь проходят через httpClient
- `authService` теперь использует `httpClient` вместо прямого axios
- Все запросы проходят через единый interceptor

### 3. Улучшено логирование
- Добавлены эмодзи для лучшей читаемости логов
- Логируются детали запросов и ответов
- Отслеживается процесс обновления токенов

## Как тестировать

### 1. Откройте консоль браузера (F12)

### 2. Проверьте логи при загрузке страницы
Должны появиться сообщения:
- `🔑 Added token to request: /auth/guest-token`
- `✅ Successfully refreshed guest token, retrying request...`

### 3. Сделайте запрос, который вернет 401
Например, попробуйте получить доступ к защищенному эндпоинту без авторизации.

### 4. Проверьте логи в консоли
Должны появиться сообщения:
- `🔐 Received 401 error, attempting to refresh guest token...`
- `✅ Successfully refreshed guest token, retrying request...`
- `🔄 Retrying request with new token...`

## Возможные проблемы

### 1. CORS ошибки
- Проверьте, что backend разрешает CORS
- Убедитесь, что `baseURL` в `httpClient` правильный

### 2. Проблемы с токенами
- Проверьте, что `authService.getToken()` возвращает валидный токен
- Убедитесь, что `forceRefreshGuestToken()` работает корректно

### 3. Зацикливание запросов
- Interceptor настроен на одну попытку повтора (`_retry` флаг)
- Если проблема повторяется, проверьте backend

## Структура файлов

```
frontend/src/services/
├── httpClient.ts      # Основной HTTP клиент с interceptor'ами
├── authService.ts     # Сервис авторизации (использует httpClient)
└── demoService.ts     # Демо сервис (использует httpClient)
```

## Логи для отладки

При успешной работе interceptor'а вы должны видеть:
1. `🔑 Added token to request: [URL]` - токен добавлен к запросу
2. `🔐 Received 401 error, attempting to refresh guest token...` - получена 401 ошибка
3. `✅ Successfully refreshed guest token, retrying request...` - токен обновлен
4. `🔄 Retrying request with new token...` - запрос повторяется
5. `🔑 Added token to request: [URL]` - новый токен добавлен к повторному запросу
