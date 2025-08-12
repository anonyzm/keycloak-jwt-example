import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { authService } from './authService';

// Создаем экземпляр axios с базовой конфигурацией
const httpClient: AxiosInstance = axios.create({
  baseURL: 'http://localhost/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor для автоматического добавления токена к запросам
httpClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Добавляем токен, если он есть
    const token = authService.getToken();
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
      console.log('🔑 Added token to request:', config.url);
    } else {
      console.log('⚠️ No token available for request:', config.url);
    }
    return config;
  },
  (error) => {
    console.error('❌ Request interceptor error:', error);
    return Promise.reject(error);
  }
);

// Interceptor для автоматической обработки 401 ошибок
httpClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error) => {
    console.log('httpClient interceptor response code: ', error);
    const originalRequest = error.config;

    // Если получили 401 и это не повторная попытка
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      console.log('🔐 Received 401 error, attempting to refresh token...');
      console.log('📝 Original request:', {
        url: originalRequest.url,
        method: originalRequest.method,
        headers: originalRequest.headers
      });
      
      try {
        // Пытаемся обновить токен с помощью refresh_token
        const refreshToken = authService.getRefreshToken();
        if (refreshToken) {
          console.log('🔄 Attempting to refresh token with refresh_token...');
          await authService.refreshToken(refreshToken);
          console.log('✅ Successfully refreshed token, retrying request...');
        } else {
          // Если нет refresh_token, принудительно обновляем гостевой токен
          console.log('⚠️ No refresh_token found, falling back to guest token...');
          await authService.forceRefreshGuestToken();
          console.log('✅ Successfully refreshed guest token, retrying request...');
        }
        
        // Повторяем оригинальный запрос с новым токеном
        const token = authService.getToken();
        if (token && originalRequest.headers) {
          originalRequest.headers.Authorization = `Bearer ${token}`;
          console.log('🔄 Retrying request with new token...');
          return httpClient.request(originalRequest);
        } else {
          console.error('❌ Failed to get token after refresh');
          return Promise.reject(error);
        }
      } catch (refreshError) {
        console.error('❌ Failed to refresh token:', refreshError);
        // Если не удалось обновить токен, очищаем его и возвращаем ошибку
        authService.clearToken();
        console.log('🧹 Cleared invalid token');
      }
    } else if (error.response?.status === 401 && originalRequest._retry) {
      console.log('⚠️ Request already retried once, not retrying again');
    }
    
    return Promise.reject(error);
  }
);

export default httpClient;
