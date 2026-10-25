# Runway API Platform

API platform for [Runway](https://github.com/BaDClusteR/runway).

```shell
composer require bad_cluster/runway-api-platform:^2.0
```

PHP 8.4+, Runway 2 (`bad_cluster/runway:^2.0`).

## Endpoints

An endpoint is a public method of a service with the `api_endpoint_collection` tag. The method has an endpoint
attribute: `Get`, `Post`, `Put`, `Patch`, `Delete` (or `Endpoint` with any method). The path is a template relative to
the API prefix (`api_path_prefix` parameter, `/api` by default) and to the class `Prefix`:

```php
use ApiPlatform\Attribute as Api;

#[Api\Prefix('chats/{chatId}')]
class Chat {
    #[Api\Get]                              // GET /api/chats/42
    public function get(int $chatId): ChatDTO {}

    #[Api\Get('chunks/{index}')]            // GET /api/chats/42/chunks/3
    public function chunk(int $chatId, int $index): ChunkDTO {}

    #[Api\Get('messages')]                  // GET /api/chats/42/messages?number=10
    public function messages(int $chatId, #[Api\Query] ?int $number = null): MessagesDTO {}

    #[Api\Patch('messages/{messageId}/meta')]
    public function updateMeta(int $chatId, int $messageId, #[Api\Body] array $meta): MetaDTO {}
}
```

```yaml
services:
  App\Endpoint\Chat:
    tags:
      - name: api_endpoint_collection
```

The endpoints are the routes of the Runway router, so the router rules apply:

- If several endpoints match the path, the most specific one wins: a static segment beats a placeholder
  (`chats/{chatId}/messages/latest` beats `chats/{chatId}/messages/{messageId}`).
- If the path matches, but the method does not, the response is `405 Method Not Allowed` with the `Allow` header.
  An `OPTIONS` request gets the allowed methods (and the CORS headers).
- Two endpoints with the same path and method are an error.

## Arguments

- **Path:** an argument with the name of a placeholder gets its value. An `int`, `float` or `bool` argument limits the
  placeholder to such values (`/api/chats/abc` is not found). Another regexp can be set inline: `{slug<[a-z-]+>}`.
  `#[Api\Path('name')]` takes a placeholder with another name.
- **Query, body, file:** `#[Api\Query]`, `#[Api\Body]`, `#[Api\File]`, optionally with the name in the request. A query
  parameter is taken from the query string whatever the method (e.g. `DELETE files?path=...`), and from the form fields
  of a request with a form too.
- **File:** an `ApiEndpointArgumentFileDTO` argument gets the uploaded file (only an uploaded one: not any file of the
  server). An optional file (with a default value) can be left empty in the form.
- An argument without a source gets its default value.
- The values are checked by the argument types (`int`, `float`, `bool`, `string`, `array`) and the assertions
  (`#[Assert\NotEmpty]`, `#[Assert\Length]`, `#[Assert\Range]`): the errors of all the arguments are in one
  `400` response.

The mistakes (a placeholder without an argument, an argument without a source, etc.) are reported when the endpoints
are collected, not when the endpoint is called.

## Authorization

One user: its login and password hash (Argon2id) are in the `options` table, set them with `IAuth::setCredentials()`
(e.g. by a console command of the project), it ends all the sessions.

- `POST auth/login` starts a session: a browser gets it in the cookie (`HttpOnly`, `SameSite=Strict`, `Secure` over
  HTTPS), a script (`useCookie: false`) gets the token for the `API_TOKEN_HEADER` header. Only the hash of the token
  is stored (the `tokens` table). A session lasts 30 days.
- `POST auth/logout` ends it, `GET auth/status` tells whether the client is logged in.
- `GET auth/check` responds with 204 or 401: for the web server, e.g. nginx `auth_request` serves the static files only
  to the logged-in clients.
- The failed logins (the `login_attempts` table) are limited: 5 per IP address and 50 in total for 15 minutes, then
  the response is 429.
- A change (not `GET`, `HEAD` or `OPTIONS`) made by a page of another site is rejected (403), unless the site is
  allowed (`API_ALLOW_ORIGIN`) and the request has no session cookie: the browser adds the cookie, and the web server
  can let the request in by the IP address or the cached basic auth (CSRF). A script sends no `Origin` header, a page
  cannot send the token header without CORS.

The tables (with the project prefix): `options (id, name, value)`, `tokens (id, token, generated, valid_until,
last_active, ip, user_agent, server_info, expired, active)`, `login_attempts (id, ip, attempted)`.

## Configuration

The ENV variables (see `.env`, override them in the project):

- `API_AUTH_ENABLED`: `false` makes every endpoint public, e.g. when the access is limited by the web server (by IP,
  basic auth, etc.). Then `auth/login` responds with 404. Only an explicit `false` (`0`, `off`, `no`) opens the API:
  an empty or invalid value keeps the authorization.
- `API_TOKEN_HEADER`: the header of the session token for the scripts, `X-Token` by default.
- `API_ALLOW_ORIGIN`: comma-separated origins allowed to call the API from a browser on another site (CORS), `*` for
  any site. Empty by default: only the pages of the API site itself can call it.
- `api_path_prefix` parameter: `/api` by default; `api_session_cookie`: the session cookie, `api_session` by default;
  `api_title`, `api_description`: the title and the description of the OpenAPI spec (`GET schema`).

The errors of the API paths (including 404 and 405) are JSON. The responses have the `X-Request-Id`,
`X-Content-Type-Options: nosniff` and `Cache-Control: no-store` headers (an endpoint response can set its own). The
details of the internal errors (5xx: the message, the file, the trace) are shown only with `APP_DEBUG=true`; the client
errors (4xx) are logged at the info level, the internal ones at the error level, without the arguments of the calls.

## Development

```shell
composer test               # PHPUnit: the unit, integration and E2E tests
composer test:unit          # the classes one by one
composer test:integration   # the container in the process, SQLite in memory
composer test:e2e           # HTTP requests to the PHP built-in server
composer phpstan            # PHPStan, the max level
composer infection          # mutation testing of the unit tests (pcov or Xdebug)
```

The tests need the `pdo_sqlite` extension. The CI runs them on Linux, macOS and Windows with PHP 8.4 and 8.5.
