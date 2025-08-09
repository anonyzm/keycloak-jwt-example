# Многоэтапная сборка
FROM node:18-alpine as build

WORKDIR /app

# Копируем package.json и package-lock.json (если есть)
COPY frontend/package*.json ./

# Устанавливаем зависимости
RUN npm install

# Копируем исходный код
COPY frontend/ .

# Собираем приложение
RUN npm run build

# Продакшн образ с nginx
FROM nginx:alpine

# Копируем собранное приложение
COPY --from=build /app/build /usr/share/nginx/html

# Копируем конфигурацию nginx
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf

# Открываем порт
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]