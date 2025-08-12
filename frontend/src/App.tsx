import React, { useState, useEffect } from 'react';
import { DemoButtons } from './components/DemoButtons';
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
        console.log('Starting app initialization...');
        await authService.initialize();
        updateUserInfo();
        console.log('App initialization completed');
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
      backgroundColor: '#4f4f4f',
      padding: '2rem'
    }}>
      <div style={{
        maxWidth: '1000px',
        margin: '0 auto'
      }}>
        {/* Заголовок */}
        <header style={{
          backgroundColor: '#2d2d2d',
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
              <h1 style={{ margin: '0 0 0.5rem 0', color: '#c1c1c1' }}>
                Keycloak JWT Demo
              </h1>
              <p style={{ margin: 0, color: '#c1c1c1', fontSize: '0.9rem' }}>
                {userInfo}
              </p>
            </div>
            <div style={{ display: 'flex', gap: '1rem' }}>
              <button
                onClick={() => setIsAuthModalOpen(true)}
                style={{
                  padding: '0.5rem 1rem',
                  backgroundColor: 'rgb(14 18 22)',
                  color: '#c1c1c1',
                  border: 'none',
                  borderRadius: '4px',
                  cursor: 'pointer'
                }}
              >
                Login
              </button>
              <button
                onClick={handleLogout}
                style={{
                  padding: '0.5rem 1rem',
                  backgroundColor: '#6c757d',
                  color: '#c1c1c1',
                  border: 'none',
                  borderRadius: '4px',
                  cursor: 'pointer'
                }}
              >
                Logout
              </button>
            </div>
          </div>
        </header>

        {/* Основной контент */}
        <main style={{
          backgroundColor: '#2d2d2d',
          borderRadius: '8px',
          boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
          overflow: 'hidden',
          padding: '1rem'
        }}>
          <DemoButtons onAuthRequired={handleAuthRequired} />
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