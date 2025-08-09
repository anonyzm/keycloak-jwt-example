import React, { useState } from 'react';
import { useForm } from 'react-hook-form';
import { authService } from '../services/authService';

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface FormData {
  phone: string;
  code: string;
}

export const AuthModal: React.FC<AuthModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const { register, handleSubmit, formState: { errors }, reset } = useForm<FormData>();

  const handlePhoneSubmit = async (data: FormData) => {
    setLoading(true);
    setError(null);
    
    try {
      await authService.requestCode(data.phone);
      setPhone(data.phone);
      setStep('code');
    } catch (error: any) {
      setError(error.response?.data?.message || 'Ошибка при запросе кода');
    } finally {
      setLoading(false);
    }
  };

  const handleCodeSubmit = async (data: FormData) => {
    setLoading(true);
    setError(null);
    
    try {
      await authService.login(phone, data.code);
      reset();
      setStep('phone');
      setPhone('');
      onSuccess();
      onClose();
    } catch (error: any) {
      setError(error.response?.data?.message || 'Неверный код');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    reset();
    setStep('phone');
    setPhone('');
    setError(null);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: 'rgba(0,0,0,0.5)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000
    }}>
      <div style={{
        backgroundColor: 'white',
        padding: '2rem',
        borderRadius: '8px',
        minWidth: '400px',
        maxWidth: '500px'
      }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
          <h2 style={{ margin: 0 }}>
            {step === 'phone' ? 'Авторизация' : 'Введите код'}
          </h2>
          <button 
            onClick={handleClose}
            style={{
              background: 'none',
              border: 'none',
              fontSize: '1.5rem',
              cursor: 'pointer',
              padding: '0.25rem'
            }}
          >
            ×
          </button>
        </div>

        {error && (
          <div style={{
            backgroundColor: '#fee',
            color: '#c33',
            padding: '0.75rem',
            borderRadius: '4px',
            marginBottom: '1rem'
          }}>
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit(step === 'phone' ? handlePhoneSubmit : handleCodeSubmit)}>
          {step === 'phone' ? (
            <div style={{ marginBottom: '1rem' }}>
              <label style={{ display: 'block', marginBottom: '0.5rem' }}>
                Номер телефона:
              </label>
              <input
                type="tel"
                placeholder="+79123456789"
                {...register('phone', {
                  required: 'Номер телефона обязателен',
                  pattern: {
                    value: /^\+7\d{10}$/,
                    message: 'Введите номер в формате +79123456789'
                  }
                })}
                style={{
                  width: '100%',
                  padding: '0.75rem',
                  border: '1px solid #ddd',
                  borderRadius: '4px',
                  fontSize: '1rem',
                  boxSizing: 'border-box'
                }}
              />
              {errors.phone && (
                <div style={{ color: '#c33', fontSize: '0.875rem', marginTop: '0.25rem' }}>
                  {errors.phone.message}
                </div>
              )}
            </div>
          ) : (
            <div style={{ marginBottom: '1rem' }}>
              <label style={{ display: 'block', marginBottom: '0.5rem' }}>
                Код подтверждения для {phone}:
              </label>
              <input
                type="text"
                placeholder="123456"
                {...register('code', {
                  required: 'Код обязателен',
                  minLength: {
                    value: 6,
                    message: 'Код должен содержать 6 цифр'
                  }
                })}
                style={{
                  width: '100%',
                  padding: '0.75rem',
                  border: '1px solid #ddd',
                  borderRadius: '4px',
                  fontSize: '1rem',
                  boxSizing: 'border-box'
                }}
              />
              {errors.code && (
                <div style={{ color: '#c33', fontSize: '0.875rem', marginTop: '0.25rem' }}>
                  {errors.code.message}
                </div>
              )}
            </div>
          )}

          <div style={{ display: 'flex', gap: '1rem', justifyContent: 'flex-end' }}>
            {step === 'code' && (
              <button
                type="button"
                onClick={() => setStep('phone')}
                disabled={loading}
                style={{
                  padding: '0.75rem 1.5rem',
                  border: '1px solid #ddd',
                  borderRadius: '4px',
                  backgroundColor: 'white',
                  cursor: 'pointer'
                }}
              >
                Назад
              </button>
            )}
            <button
              type="submit"
              disabled={loading}
              style={{
                padding: '0.75rem 1.5rem',
                backgroundColor: '#007bff',
                color: 'white',
                border: 'none',
                borderRadius: '4px',
                cursor: loading ? 'not-allowed' : 'pointer',
                opacity: loading ? 0.7 : 1
              }}
            >
              {loading ? 'Загрузка...' : (step === 'phone' ? 'Получить код' : 'Войти')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};