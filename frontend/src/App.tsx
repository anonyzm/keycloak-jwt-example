import React, { useState, useEffect } from 'react';
import { DemoTabs } from './components/DemoTabs';
import { AuthModal } from './components/AuthModal';
import { authService } from './services/authService';

const App: React.FC = () => {
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);
  const [userInfo, setUserInfo] = useState<string>('');

  useEffect(() => {
    // Инициализация сервиса авторизации при загрузке приложения
    const initialize = async () => {
      try {
        await authService.initialize();
        updateUserInfo();
      } catch (error) {
        console.error('Failed to initialize app:', error);
      } finally {
        setIsInitialized(true);
      }
    };

    initialize();
  }, []);

  const updateUserInfo = () => {
    const token = authService.getToken();
    if (token) {
      try {
        // Декодируем JWT токен для отображения информации о пользователе
        const payload = JSON.parse(atob(token.split('.')[1]));
        const roles = payload.realm_access?.roles || [];
        const username = payload.preferred_username || 'Неизвестно';
        setUserInfo(`Пользователь: ${username}, Роли: ${roles.join(', ')}`);
      } catch (error) {
        setUserInfo('Информация о токене недоступна');
      }
    } else {
      setUserInfo('Токен отсутствует');
    }
  };

  const handleAuthRequired = () => {
    setIsAuthModalOpen(true);
  };

  const handleAuthSuccess = () => {
    updateUserInfo();
    // Можно добавить обновление данных в табах
  };

  const handleLogout = () => {
    authService.logout();
    updateUserInfo();
  };

  if (!isInitialized) {
    return (
      <div style={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        minHeight: '100vh',
        fontSize: '1.2rem',
        color: '#666'
      }}>
        Инициализация приложения...
      </div>
    );
  }

  return (
    <div style={{
      minHeight: '100vh',
      backgroundColor: '#f5f5f5',
      padding: '2rem'
    }}>
      <div style={{
        maxWidth: '1000px',
        margin: '0 auto'
      }}>
        {/* Заголовок */}
        <header style={{
          backgroundColor: 'white',
          padding: '1.5rem',
          borderRadius: '8px',
          marginBottom: '2rem',
          boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
        }}>
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center'
          }}>
            <div>
              <h1 style={{ margin: '0 0 0.5rem 0', color: '#333' }}>
                Keycloak JWT Demo
              </h1>
              <p style={{ margin: 0, color: '#666', fontSize: '0.9rem' }}>
                {userInfo}
              </p>
            </div>
            <div style={{ display: 'flex', gap: '1rem' }}>
              <button
                onClick={() => setIsAuthModalOpen(true)}
                style={{
                  padding: '0.5rem 1rem',
                  backgroundColor: '#007bff',
                  color: 'white',
                  border: 'none',
                  borderRadius: '4px',
                  cursor: 'pointer'
                }}
              >
                Авторизация
              </button>
              <button
                onClick={handleLogout}
                style={{
                  padding: '0.5rem 1rem',
                  backgroundColor: '#6c757d',
                  color: 'white',
                  border: 'none',
                  borderRadius: '4px',
                  cursor: 'pointer'
                }}
              >
                Выйти
              </button>
            </div>
          </div>
        </header>

        {/* Основной контент */}
        <main style={{
          backgroundColor: 'white',
          borderRadius: '8px',
          boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
          overflow: 'hidden'
        }}>
          <DemoTabs onAuthRequired={handleAuthRequired} />
        </main>

        {/* Модальное окно авторизации */}
        <AuthModal
          isOpen={isAuthModalOpen}
          onClose={() => setIsAuthModalOpen(false)}
          onSuccess={handleAuthSuccess}
        />
      </div>
    </div>
  );
};

export default App;