import React, { useState, useEffect } from 'react';
import { demoService } from '../services/demoService';
import { UserInfo } from '../services/authService';

interface DemoButtonsProps {
  onAuthRequired: () => void;
}

interface ButtonData {
  title: string;
  fetchFunction: () => Promise<UserInfo>;
}

export const DemoButtons: React.FC<DemoButtonsProps> = ({ onAuthRequired }) => {
  const [buttons] = useState<ButtonData[]>([
    {
      title: 'Guest info',
      fetchFunction: demoService.getPublicInfo.bind(demoService)
    },
    {
      title: 'User only',
      fetchFunction: demoService.getUserOnlyInfo.bind(demoService)
    },
    {
      title: 'No role',
      fetchFunction: demoService.getNoRoleInfo.bind(demoService)
    }
  ]);

  const [currentData, setCurrentData] = useState<UserInfo | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [lastClickedButton, setLastClickedButton] = useState<string>('');

  const fetchData = async (buttonData: ButtonData) => {
    setLoading(true);
    setError(null);
    setLastClickedButton(buttonData.title);

    try {
      const data = await buttonData.fetchFunction();
      setCurrentData(data);
      setLoading(false);
    } catch (error: any) {
      setLoading(false);
      
      if (error.response?.status === 403) {
        setError('Доступ запрещен. Требуется авторизация.');
        onAuthRequired();
      } else {
        setError(error.response?.data?.message || 'Ошибка при загрузке данных');
      }
    }
  };

  // Автоматически загружаем данные первой кнопки при монтировании
  useEffect(() => {
    // Небольшая задержка, чтобы убедиться что токен инициализирован
    const timer = setTimeout(() => {
      fetchData(buttons[0]);
    }, 100);
    
    return () => clearTimeout(timer);
  }, []);

  const renderContent = () => {
    if (loading) {
      return (
        <div style={{ 
          display: 'flex', 
          justifyContent: 'center', 
          alignItems: 'center',
          minHeight: '200px',
          color: '#666'
        }}>
          Loading...
        </div>
      );
    }

    if (error) {
      return (
        <div style={{ padding: '1rem' }}>
          <div style={{
            backgroundColor: '#fee',
            color: '#c33',
            padding: '1rem',
            borderRadius: '4px',
            marginBottom: '1rem'
          }}>
            {error}
          </div>
          <button
            onClick={() => fetchData(buttons.find(b => b.title === lastClickedButton)!)}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            Repeat request
          </button>
        </div>
      );
    }

    if (!currentData) {
      return (
        <div style={{ padding: '1rem' }}>
          <p style={{ color: '#666', textAlign: 'center' }}>
            Click one of the buttons above to load data
          </p>
        </div>
      );
    }

    return (
      <div style={{ padding: '1rem' }}>
        <div style={{
          backgroundColor: '#f8f9fa',
          border: '1px solid #e9ecef',
          borderRadius: '4px',
          padding: '1rem',
          marginBottom: '1rem'
        }}>
          <h3 style={{ margin: '0 0 1rem 0', color: '#495057' }}>API response:</h3>
          <div style={{ marginBottom: '1rem' }}>
            <strong>Message:</strong> {currentData.message}
          </div>
          
          {currentData.your_roles && currentData.your_roles.length > 0 && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>Your Roles:</strong>
              <div style={{ marginTop: '0.5rem' }}>
                {currentData.your_roles.map((role, i) => (
                  <span
                    key={i}
                    style={{
                      display: 'inline-block',
                      backgroundColor: '#007bff',
                      color: 'white',
                      padding: '0.25rem 0.5rem',
                      borderRadius: '12px',
                      fontSize: '0.875rem',
                      marginRight: '0.5rem'
                    }}
                  >
                    {role}
                  </span>
                ))}
              </div>
            </div>
          )}

          {currentData.user_id && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>User ID:</strong> {currentData.user_id}
            </div>
          )}

          {currentData.sensitive_data && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>Sensitive data:</strong>
              <div style={{
                backgroundColor: '#fff3cd',
                border: '1px solid #ffeaa7',
                borderRadius: '4px',
                padding: '0.75rem',
                marginTop: '0.5rem',
                fontFamily: 'monospace'
              }}>
                {currentData.sensitive_data}
              </div>
            </div>
          )}
        </div>
        
        <button
          onClick={() => fetchData(buttons.find(b => b.title === lastClickedButton)!)}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          Update data
        </button>
      </div>
    );
  };

  return (
    <div style={{ maxWidth: '800px', margin: '0 auto' }}>
      {/* Кнопки */}
      <div style={{ 
        display: 'flex', 
        justifyContent: 'center', 
        gap: '1rem', 
        marginBottom: '1rem',
        flexWrap: 'wrap'
      }}>
        {buttons.map((button, index) => (
          <button
            key={index}
            onClick={() => fetchData(button)}
            style={{
              padding: '0.75rem 1.5rem',
              backgroundColor: lastClickedButton === button.title ? '#28a745' : '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '6px',
              cursor: 'pointer',
              fontSize: '1rem',
              fontWeight: '500',
              transition: 'background-color 0.2s ease',
              minWidth: '200px'
            }}
            onMouseEnter={(e) => {
              if (lastClickedButton !== button.title) {
                e.currentTarget.style.backgroundColor = '#0056b3';
              }
            }}
            onMouseLeave={(e) => {
              if (lastClickedButton !== button.title) {
                e.currentTarget.style.backgroundColor = '#007bff';
              }
            }}
          >
            {button.title}
          </button>
        ))}
      </div>

      {/* Контейнер с информацией */}
      <div style={{
        backgroundColor: 'white',
        border: '1px solid #e9ecef',
        borderRadius: '8px',
        boxShadow: '0 2px 4px rgba(0,0,0,0.1)',
        minHeight: '300px'
      }}>
        {renderContent()}
      </div>
    </div>
  );
};
