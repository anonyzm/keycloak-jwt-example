import httpClient from './httpClient';

export interface TokenResponse {
  access_token: string;
  expires_in: number;
  token_type: string;
  refresh_token?: string; // Опциональный refresh_token
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
  private readonly TOKEN_KEY = 'jwt_token';
  private readonly TOKEN_EXPIRY_KEY = 'jwt_token_expiry';
  private readonly REFRESH_TOKEN_KEY = 'jwt_refresh_token';

  constructor() {
    // Убираем дублирующие interceptor'ы - они теперь только в httpClient.ts
  }

  // Получение гостевого токена
  async getGuestToken(): Promise<TokenResponse> {
    try {
      const response: any = await httpClient.post(
        `/auth/guest-token`
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
      const response = await httpClient.post(`/auth/request-code`, {
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
      const response: any = await httpClient.post(
        `/auth/login`,
        { phone, code }
      );
      
      // Backend возвращает {message: "...", token:", {...}}
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
    
    // Сохраняем refresh_token, если он есть
    if (tokenData.refresh_token) {
      localStorage.setItem(this.REFRESH_TOKEN_KEY, tokenData.refresh_token);
    }
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

  // Получение refresh_token из localStorage
  getRefreshToken(): string | null {
    return localStorage.getItem(this.REFRESH_TOKEN_KEY);
  }

  // Очистка токена
  clearToken(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.TOKEN_EXPIRY_KEY);
    localStorage.removeItem(this.REFRESH_TOKEN_KEY);
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

  // Принудительное обновление гостевого токена
  async forceRefreshGuestToken(): Promise<TokenResponse> {
    console.log('Force refreshing guest token...');
    this.clearToken();
    return await this.getGuestToken();
  }

  // Обновление токена с использованием refresh_token
  async refreshToken(refreshToken: string): Promise<TokenResponse> {
    try {
      const response: any = await httpClient.post(
        `/auth/refresh-token`,
        { refresh_token: refreshToken }
      );
      
      // Backend возвращает {message: "...", token: {...}}
      const tokenData = response.data.token;
      this.saveToken(tokenData);
      return tokenData;
    } catch (error) {
      console.error('Failed to refresh token:', error);
      throw error;
    }
  }

  // Проверка валидности токена и обновление при необходимости
  async ensureValidToken(): Promise<string | null> {
    const token = this.getToken();
    if (!token) {
      console.log('No token found, getting new guest token...');
      try {
        const newToken = await this.getGuestToken();
        return newToken.access_token;
      } catch (error) {
        console.error('Failed to get guest token:', error);
        return null;
      }
    }
    return token;
  }

  // Выход из системы
  logout(): void {
    this.clearToken();
    // Получаем новый гостевой токен
    this.getGuestToken().catch(console.error);
  }
}

export const authService = new AuthService();