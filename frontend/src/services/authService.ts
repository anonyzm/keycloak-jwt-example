import axios, { AxiosResponse } from 'axios';

export interface TokenResponse {
  access_token: string;
  expires_in: number;
  token_type: string;
}

export interface AuthResponse {
  message: string;
  token: TokenResponse;
}

export interface ErrorResponse {
  error: string;
  message: string;
}

export interface UserInfo {
  message: string;
  your_roles: string[];
  user_id?: string;
  sensitive_data?: string;
}

export interface AuthRequest {
  phone: string;
  code: string;
}

class AuthService {
  private readonly API_BASE = 'http://localhost/api';
  private readonly TOKEN_KEY = 'jwt_token';
  private readonly TOKEN_EXPIRY_KEY = 'jwt_token_expiry';

  constructor() {
    // Настройка axios interceptor для автоматического добавления токена
    axios.interceptors.request.use((config) => {
      const token = this.getToken();
      if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Interceptor для обработки 401 ошибок (токен истек)
    axios.interceptors.response.use(
      (response) => response,
      async (error) => {
        if (error.response?.status === 401 && error.config && !error.config._retry) {
          error.config._retry = true;
          this.clearToken();
          
          // Попытка получить новый гостевой токен
          try {
            await this.getGuestToken();
            // Повторяем оригинальный запрос с новым токеном
            const originalRequest = error.config;
            const token = this.getToken();
            if (token) {
              originalRequest.headers.Authorization = `Bearer ${token}`;
              return axios.request(originalRequest);
            }
          } catch (refreshError) {
            console.error('Failed to refresh token:', refreshError);
          }
        }
        return Promise.reject(error);
      }
    );
  }

  // Получение гостевого токена
  async getGuestToken(): Promise<TokenResponse> {
    try {
      const response: AxiosResponse<AuthResponse> = await axios.post(
        `${this.API_BASE}/auth/guest-token`
      );
      
      // Backend возвращает {message: "...", token: {...}}
      const tokenData = response.data.token;
      this.saveToken(tokenData);
      return tokenData;
    } catch (error) {
      console.error('Failed to get guest token:', error);
      throw error;
    }
  }

  // Запрос кода авторизации
  async requestCode(phone: string): Promise<any> {
    try {
      const response = await axios.post(`${this.API_BASE}/auth/request-code`, {
        phone
      });
      return response.data;
    } catch (error) {
      console.error('Failed to request code:', error);
      throw error;
    }
  }

  // Авторизация с кодом
  async login(phone: string, code: string): Promise<TokenResponse> {
    try {
      const response: AxiosResponse<AuthResponse> = await axios.post(
        `${this.API_BASE}/auth/login`,
        { phone, code }
      );
      
      // Backend возвращает {message: "...", token: {...}}
      const tokenData = response.data.token;
      this.saveToken(tokenData);
      return tokenData;
    } catch (error) {
      console.error('Failed to login:', error);
      throw error;
    }
  }

  // Сохранение токена в localStorage
  private saveToken(tokenData: TokenResponse): void {
    const expiryTime = Date.now() + (tokenData.expires_in * 1000);
    localStorage.setItem(this.TOKEN_KEY, tokenData.access_token);
    localStorage.setItem(this.TOKEN_EXPIRY_KEY, expiryTime.toString());
  }

  // Получение токена из localStorage
  getToken(): string | null {
    const token = localStorage.getItem(this.TOKEN_KEY);
    const expiry = localStorage.getItem(this.TOKEN_EXPIRY_KEY);
    
    if (!token || !expiry) {
      return null;
    }
    
    // Проверяем, не истек ли токен
    if (Date.now() > parseInt(expiry)) {
      this.clearToken();
      return null;
    }
    
    return token;
  }

  // Очистка токена
  clearToken(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.TOKEN_EXPIRY_KEY);
  }

  // Проверка, авторизован ли пользователь
  isAuthenticated(): boolean {
    const token = this.getToken();
    return token !== null && token !== 'undefined' && token.length > 0;
  }

  // Инициализация - получение токена при старте приложения
  async initialize(): Promise<void> {
    try {
      if (!this.isAuthenticated()) {
        console.log('No valid token found, getting guest token...');
        await this.getGuestToken();
        console.log('Guest token obtained successfully');
      } else {
        console.log('Valid token found in localStorage');
      }
    } catch (error) {
      console.error('Failed to initialize auth service:', error);
      // Если не удалось получить токен, попробуем еще раз через секунду
      setTimeout(() => {
        this.initialize().catch(console.error);
      }, 1000);
    }
  }

  // Выход из системы
  logout(): void {
    this.clearToken();
    // Получаем новый гостевой токен
    this.getGuestToken().catch(console.error);
  }
}

export const authService = new AuthService();