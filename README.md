# MemoryLane

A tiny, self-hosted, prod-safe profiler for Laravel: Lighthouse, but for your backend. One dashboard shows what's slow right now, with per-request duration, peak memory, queries and N+1s.

- **Prod-safe:** samples 10% of requests, always records slow ones, writes one row after the response is sent, and never breaks your app if it fails.
- **Private by default:** stores query shapes (`where id = ?`), never values. No request input.
- **Clean in, clean out:** zero edits to your `app/`, `routes/` or `bootstrap/`.

## Requirements

PHP 8.3+, Laravel 12, MySQL or SQL Server.

## Install

```bash
composer require thirdestonks/memorylane
php artisan migrate
```

Set a login (below), then open `/memorylane`.

## Dashboard access

The dashboard is locked in every environment, local included, until someone signs in.

| Situation | What happens |
|---|---|
| Login set in `.env` | Login screen, then the dashboard. |
| No login set | Locked. The page shows the two `.env` lines to add. |
| A user your app's `viewMemoryLane` gate allows | Straight in. |

### Turn on the login

Add to your `.env`:

```env
MEMORYLANE_USERNAME=ops
MEMORYLANE_PASSWORD=a-long-random-key
```

Generate a strong key with `php -r "echo bin2hex(random_bytes(24));"`. If you cache config, run `php artisan config:cache` after editing `.env`.

- **Change the password** in `.env` and everyone who's logged in is signed out on their next click.
- **The login is kept in your app's normal session**, so no extra table and no user accounts.
- **Guessing is limited** to 5 attempts per minute per IP.
- **Keep it safe:** use a long random key and serve over HTTPS.

### Or let your own users in

Already have logged-in users? Grant them access with the `viewMemoryLane` gate in your `AppServiceProvider` instead (or as well):

```php
Gate::define('viewMemoryLane', fn ($user) => in_array($user->email, ['you@example.com']));
```

## Configuration

Optional: `php artisan vendor:publish --tag=memorylane-config`

| Key | Default | |
|---|---|---|
| `enabled` | `true` | `MEMORYLANE_ENABLED=false` turns everything off |
| `sample_rate` | `0.1` | share of requests recorded |
| `always_record_slow` | `1000` | ms; slower requests are always recorded |
| `n_plus_one_threshold` | `10` | same query this many times = N+1 |
| `max_queries` | `500` | per-request query buffer cap |
| `keep_hours` | `72` | pruned hourly by the scheduler |
| `connection` | `null` | separate DB connection for the table |
| `capture_bindings` | `false` | store query values (avoid on real data) |
| `ignore` | `memorylane*`, `horizon*`, `up` | paths never recorded |
| `username` / `password` | `null` | dashboard login (`MEMORYLANE_USERNAME` / `MEMORYLANE_PASSWORD`) |

These can be set straight from `.env`:

```env
MEMORYLANE_ENABLED=true
MEMORYLANE_SAMPLE_RATE=0.1
MEMORYLANE_DB_CONNECTION=
MEMORYLANE_USERNAME=
MEMORYLANE_PASSWORD=
```

The other keys (`always_record_slow`, `n_plus_one_threshold`, `keep_hours`, and so on) are changed in the published config file.

## Uninstall

```bash
php artisan memorylane:uninstall
composer remove thirdestonks/memorylane
```

## License

MIT
