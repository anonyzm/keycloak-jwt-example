import React, { useState, useEffect } from 'react';
import { authService } from '../services/authService';
import httpClient from '../services/httpClient';

interface TokenStatusProps {
  onTokenRefresh?: () => void;
}

export const TokenStatus: React.FC<TokenStatusProps> = ({ onTokenRefresh }) => {
  const [tokenInfo, setTokenInfo] = useState<string>('');
  const [isLoading, setIsLoading] = useState(false);
  const [lastRequest, setLastRequest] = useState<string>('');

  useEffect(() => {
    updateTokenInfo();
  }, []);

  const updateTokenInfo = () => {
    const token = authService.getToken();
    if (token) {
      try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        const roles = payload.realm_access?.roles || [];
        const username = payload.preferred_username || 'Неизвестно';
        const exp = new Date(payload.exp * 1000).toLocaleString();
        setTokenInfo(`Пользователь: ${username}, Роли: ${roles.join(', ')}, Истекает: ${exp}`);
      } catch (error) {
        setTokenInfo('Ошибка декодирования токена');
      }
    } else {
      setTokenInfo('Токен отсутствует');
    }
  };

  const handleForceRefresh = async () => {
    setIsLoading(true);
    try {
      await authService.forceRefreshGuestToken();
      updateTokenInfo();
      onTokenRefresh?.();
      setLastRequest('Токен успешно обновлен');
    } catch (error) {
      setLastRequest(`Ошибка обновления токена: ${error}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleTestRequest = async () => {
    setIsLoading(true);
    try {
      // Тестируем запрос, который может вернуть 401
      const response = await httpClient.get('/demo/public');
      setLastRequest(`Запрос успешен: ${JSON.stringify(response.data)}`);
    } catch (error: any) {
      setLastRequest(`Ошибка запроса: ${error.response?.status} - ${error.message}`);
    } finally {
      setIsLoading(false);
    }
  };

  const handleClearToken = () => {
    authService.clearToken();
    updateTokenInfo();
    setLastRequest('Токен очищен');
  };

  return (
    <div style={{
      backgroundColor: '#2d2d2d',
      padding: '1.5rem',
      borderRadius: '8px',
      marginBottom: '1rem',
      boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
    }}>
      <h3 style={{ margin: '0 0 1rem 0', color: '#c1c1c1' }}>
        Статус токена и тестирование
      </h3>
      
      <div style={{ marginBottom: '1rem' }}>
        <strong style={{ color: '#c1c1c1' }}>Текущий токен:</strong>
        <div style={{ 
          color: '#a0a0a0', 
          fontSize: '0.9rem', 
          marginTop: '0.5rem',
          wordBreak: 'break-all'
        }}>
          {tokenInfo}
        </div>
      </div>

      <div style={{ marginBottom: '1rem' }}>
        <strong style={{ color: '#c1c1c1' }}>Последний запрос:</strong>
        <div style={{ 
          color: '#a0a0a0', 
          fontSize: '0.9rem', 
          marginTop: '0.5rem',
          wordBreak: 'break-all'
        }}>
          {lastRequest || 'Нет запросов'}
        </div>
      </div>

      <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
        <button
          onClick={handleForceRefresh}
          disabled={isLoading}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: isLoading ? 'not-allowed' : 'pointer',
            opacity: isLoading ? 0.6 : 1
          }}
        >
          {isLoading ? 'Обновление...' : 'Принудительно обновить токен'}
        </button>

        <button
          onClick={handleTestRequest}
          disabled={isLoading}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: isLoading ? 'not-allowed' : 'pointer',
            opacity: isLoading ? 0.6 : 1
          }}
        >
          {isLoading ? 'Тестирование...' : 'Тест запроса'}
        </button>

        <button
          onClick={handleClearToken}
          disabled={isLoading}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#dc3545',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: isLoading ? 'not-allowed' : 'pointer',
            opacity: isLoading ? 0.6 : 1
          }}
        >
          Очистить токен
        </button>

        <button
          onClick={updateTokenInfo}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#6c757d',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          Обновить информацию
        </button>
      </div>

      <div style={{ 
        marginTop: '1rem', 
        padding: '1rem', 
        backgroundColor: '#1a1a1a', 
        borderRadius: '4px',
        fontSize: '0.9rem',
        color: '#a0a0a0'
      }}>
        <strong>Как это работает:</strong>
        <ul style={{ margin: '0.5rem 0', paddingLeft: '1.5rem' }}>
          <li>При получении 401 ошибки автоматически запрашивается новый гостевой токен</li>
          <li>Неудачный запрос автоматически повторяется с новым токеном</li>
          <li>Все операции логируются в консоль браузера</li>
          <li>Система предотвращает бесконечные циклы повторных попыток</li>
        </ul>
      </div>
    </div>
  );
};
