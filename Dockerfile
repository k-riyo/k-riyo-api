# backend-laravel/Dockerfile
FROM php:8.2-cli

# 必要な拡張インストール（例: MySQL）
RUN apt-get update \
  && apt-get install -y git unzip libzip-dev libonig-dev \
  && docker-php-ext-install pdo_mysql

# composer をコピー
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 先に composer 関連だけコピーして install キャッシュ効かせる
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts || true

# プロジェクト全体をコピー
COPY . .

# env とアプリキー（初回用）
# RUN cp .env.example .env || true && php artisan key:generate || true

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
