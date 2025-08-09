import React, { useState, useEffect } from 'react';
import { Tab, Tabs, TabList, TabPanel } from 'react-tabs';
import { demoService } from '../services/demoService';
import { UserInfo } from '../services/authService';
import 'react-tabs/style/react-tabs.css';

interface DemoTabsProps {
  onAuthRequired: () => void;
}

interface TabData {
  title: string;
  data: UserInfo | null;
  loading: boolean;
  error: string | null;
  fetchFunction: () => Promise<UserInfo>;
}

export const DemoTabs: React.FC<DemoTabsProps> = ({ onAuthRequired }) => {
  const [tabs, setTabs] = useState<TabData[]>([
    {
      title: 'Публичная информация',
      data: null,
      loading: false,
      error: null,
      fetchFunction: demoService.getPublicInfo.bind(demoService)
    },
    {
      title: 'Только для пользователей',
      data: null,
      loading: false,
      error: null,
      fetchFunction: demoService.getUserOnlyInfo.bind(demoService)
    },
    {
      title: 'Без ролей',
      data: null,
      loading: false,
      error: null,
      fetchFunction: demoService.getNoRoleInfo.bind(demoService)
    }
  ]);

  const fetchData = async (index: number) => {
    const newTabs = [...tabs];
    newTabs[index].loading = true;
    newTabs[index].error = null;
    setTabs(newTabs);

    try {
      const data = await newTabs[index].fetchFunction();
      newTabs[index].data = data;
      newTabs[index].loading = false;
    } catch (error: any) {
      newTabs[index].loading = false;
      
      if (error.response?.status === 403) {
        newTabs[index].error = 'Доступ запрещен. Требуется авторизация.';
        onAuthRequired();
      } else {
        newTabs[index].error = error.response?.data?.message || 'Ошибка при загрузке данных';
      }
    }
    
    setTabs(newTabs);
  };

  // Автоматически загружаем данные первого таба при монтировании
  useEffect(() => {
    // Небольшая задержка, чтобы убедиться что токен инициализирован
    const timer = setTimeout(() => {
      fetchData(0);
    }, 100);
    
    return () => clearTimeout(timer);
  }, []);

  const renderTabContent = (tabData: TabData, index: number) => {
    if (tabData.loading) {
      return (
        <div style={{ 
          display: 'flex', 
          justifyContent: 'center', 
          alignItems: 'center',
          minHeight: '200px',
          color: '#666'
        }}>
          Загрузка...
        </div>
      );
    }

    if (tabData.error) {
      return (
        <div style={{ padding: '1rem' }}>
          <div style={{
            backgroundColor: '#fee',
            color: '#c33',
            padding: '1rem',
            borderRadius: '4px',
            marginBottom: '1rem'
          }}>
            {tabData.error}
          </div>
          <button
            onClick={() => fetchData(index)}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            Повторить запрос
          </button>
        </div>
      );
    }

    if (!tabData.data) {
      return (
        <div style={{ padding: '1rem' }}>
          <button
            onClick={() => fetchData(index)}
            style={{
              padding: '0.5rem 1rem',
              backgroundColor: '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            Загрузить данные
          </button>
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
          <h3 style={{ margin: '0 0 1rem 0', color: '#495057' }}>Ответ сервера:</h3>
          <div style={{ marginBottom: '1rem' }}>
            <strong>Сообщение:</strong> {tabData.data.message}
          </div>
          
          {tabData.data.your_roles && tabData.data.your_roles.length > 0 && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>Ваши роли:</strong>
              <div style={{ marginTop: '0.5rem' }}>
                {tabData.data.your_roles.map((role, i) => (
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

          {tabData.data.user_id && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>ID пользователя:</strong> {tabData.data.user_id}
            </div>
          )}

          {tabData.data.sensitive_data && (
            <div style={{ marginBottom: '1rem' }}>
              <strong>Секретные данные:</strong>
              <div style={{
                backgroundColor: '#fff3cd',
                border: '1px solid #ffeaa7',
                borderRadius: '4px',
                padding: '0.75rem',
                marginTop: '0.5rem',
                fontFamily: 'monospace'
              }}>
                {tabData.data.sensitive_data}
              </div>
            </div>
          )}
        </div>
        
        <button
          onClick={() => fetchData(index)}
          style={{
            padding: '0.5rem 1rem',
            backgroundColor: '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          Обновить данные
        </button>
      </div>
    );
  };

  return (
    <div style={{ maxWidth: '800px', margin: '0 auto' }}>
      <Tabs onSelect={(index) => {
        // Загружаем данные для выбранного таба, если они еще не загружены
        if (!tabs[index].data && !tabs[index].loading) {
          fetchData(index);
        }
      }}>
        <TabList>
          {tabs.map((tab, index) => (
            <Tab key={index}>{tab.title}</Tab>
          ))}
        </TabList>

        {tabs.map((tab, index) => (
          <TabPanel key={index}>
            {renderTabContent(tab, index)}
          </TabPanel>
        ))}
      </Tabs>
    </div>
  );
};