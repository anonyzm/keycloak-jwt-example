import { AxiosResponse } from 'axios';
import { UserInfo } from './authService';
import httpClient from './httpClient';

class DemoService {
  private readonly API_BASE = 'http://localhost/api/demo';

  // Публичная информация (доступна guest и user)
  async getPublicInfo(): Promise<UserInfo> {
    try {
      const response: AxiosResponse<UserInfo> = await httpClient.get('/demo/public');
      return response.data;
    } catch (error) {
      console.error('Failed to get public info:', error);
      throw error;
    }
  }

  // Информация только для пользователей (требует роль user)
  async getUserOnlyInfo(): Promise<UserInfo> {
    try {
      const response: AxiosResponse<UserInfo> = await httpClient.get('/demo/user-only');
      return response.data;
    } catch (error) {
      console.error('Failed to get user-only info:', error);
      throw error;
    }
  }

  // Эндпоинт без требований к ролям
  async getNoRoleInfo(): Promise<UserInfo> {
    try {
      const response: AxiosResponse<UserInfo> = await httpClient.get('/demo/no-role');
      return response.data;
    } catch (error) {
      console.error('Failed to get no-role info:', error);
      throw error;
    }
  }
}

export const demoService = new DemoService();