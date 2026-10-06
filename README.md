# k-riyo-api
自己学習用APIリポジトリです

## 構成

- Laravel 13 / PHP 8.4
- MySQL 8.4（Docker Compose）
- JWT 認証（[php-open-source-saver/jwt-auth](https://github.com/PHP-Open-Source-Saver/jwt-auth)、guard `api` = `jwt`）

## セットアップ

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
docker compose up -d
php artisan migrate
php artisan serve   # http://localhost:8080
```

## 認証 API

| メソッド | パス | 認証 | 内容 |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | 不要 | ユーザー登録してトークン Cookie をセット |
| POST | `/api/auth/login` | 不要 | ログインしてトークン Cookie をセット |
| GET | `/api/auth/me` | 必要 | ログイン中のユーザー |
| POST | `/api/auth/logout` | 必要 | トークンを無効化して Cookie を削除 |
| POST | `/api/auth/refresh` | 必要 | 新しいトークンを発行して Cookie を更新 |

JWT はレスポンスボディには含めず、httpOnly Cookie（`access_token`、`Path=/api`、`SameSite=Lax`）で返します。
ブラウザからは Cookie を付けて（`credentials: include` / axios の `withCredentials`）リクエストしてください。
Cookie の属性は `.env` の `JWT_COOKIE_*` で変更できます。本番（HTTPS）では `JWT_COOKIE_SECURE=true` にしてください。
