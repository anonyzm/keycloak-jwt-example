# Используем официальный образ PHP 8.4 с FPM
FROM php:8.4-fpm

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    bc \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libxml2-utils \
    zip \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install mbstring exif pcntl bcmath gd

# Устанавливаем драйверы покрытия кода
RUN pecl install xdebug pcov \
    && docker-php-ext-enable xdebug pcov

# Настраиваем Xdebug для покрытия кода
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.discover_client_host=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Настройки PHP для подавления предупреждений
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/docker-php-custom.ini
    # && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/docker-php-custom.ini \
    # && echo "log_errors = On" >> /usr/local/etc/php/conf.d/docker-php-custom.ini

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /opt/app

# Копируем конфигурацию Nginx
COPY docker/backend/nginx.conf /etc/nginx/sites-available/default

# Копируем конфигурацию Supervisor
COPY docker/backend/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Создаем пользователя для приложения
RUN groupadd --gid 1001 application 
RUN useradd --uid 1001 --gid application --shell /bin/bash --create-home application
RUN usermod -aG www-data application
RUN usermod -aG application www-data

# Копируем composer.json и устанавливаем зависимости
COPY backend/composer.json ./
RUN composer install --no-dev --optimize-autoloader

# Копируем исходный код приложения
COPY backend/ ./

# Устанавливаем права на папки
RUN chown -R application:application /opt/app \
    && chmod -R 755 /opt/app \
    && chmod -R 775 /opt/app/cache \
    && chmod -R 775 /opt/app/logs \
    && chown -R application:application /var/log/supervisor \
    && chown -R application:application /var/run

# Открываем порт 80
EXPOSE 80

# Запускаем Supervisor для управления процессами
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]