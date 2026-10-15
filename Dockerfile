# Образ для Yandex Serverless Containers: PHP 8.3 + Node.js в одном контейнере.
# Маршруты берутся из vercel.json, запуск — через yc/server.js.
FROM php:8.3-cli-alpine

RUN apk add --no-cache nodejs ca-certificates tzdata

ENV TZ=Europe/Moscow \
    NODE_ENV=production \
    PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
COPY . .

# Справочники с GitHub (если приложение их использует) кладём внутрь образа.
RUN node yc/fetch-mirror.js

EXPOSE 8080
CMD ["node", "yc/server.js"]
