# Cricket Amigo — Backend

Laravel 13 API for the Cricket Amigo app (Expo client in the sibling `cricket-amigo` repo).

## Local dev (Kettle)

- Vhost: `https://cricketamigo.test` (PHP 8.5 FPM, SSL)
- DB: MySQL `cricket_amigo` (root, no password — Kettle local only)
- Base URL for the app: `https://cricketamigo.test` (see mobile `app.json → extra.apiUrl`)

```bash
composer install
php artisan migrate
./vendor/bin/pest      # tests
```

## API surface (v1)

`POST /api/auth/{device,register,login,social/{p},logout}` · `GET|PUT /api/me` · `PUT /api/me/locale` · `POST /api/me/avatar` · `GET /api/flags` · `GET /api/users/search|/{id}` · `/friends/*`

Flags (free_mode, ad policy, feature toggles) live in `config/cricketamigo.php`.

Product docs live in the mobile repo: `cricket-amigo/docs/`.
