# Changelog

## 2.0.0

The changes since 1.0.3. The upgrade steps are in [Breaking changes](#breaking-changes).

### Breaking changes

- **Endpoints are the routes of the Runway router.** The path of an endpoint is a template relative to the API prefix
  (`api_path_prefix`, `/api` by default) and the class `#[Api\Prefix]`: `chats/{chatId}/chunks/{index}`. The fixed
  `/api/{section}/{action}/{identifier}` routes, `ApiRouter` / `IApiRouter` and the section / action / identifier of
  `ApiRequestDTO` are removed: `ApiController` handles the routes of `ApiRouteProvider`.
- **Every argument has a source:** a path placeholder with its name, `#[Api\Path]`, `#[Api\Query]`, `#[Api\Body]`,
  `#[Api\File]` (the shortcuts of `#[Api\Parameter]`), or its default value. A mistake in a declaration is an
  `EndpointDefinitionException` when the endpoints are collected.
- **The authorization is rewritten.** One user: the login and the Argon2id password hash are in the `options` table
  (`IAuth::setCredentials()` sets them and ends all the sessions). The endpoints are `POST auth/login` (was
  `POST auth`), `POST auth/logout` (was `GET logoff`), `GET auth/status` and `GET auth/check`. `Crypto`, the `Option` /
  `Token` models, `API_CRYPTO_KEY`, `API_CRYPTO_KEY_LENGTH` and `API_AUTH_HASH` are removed; the failed logins are in
  the new `login_attempts` table.
- **CORS is off by default** (`API_ALLOW_ORIGIN` was `*`). A change made by a page of another site is rejected (403)
  unless the site is in `API_ALLOW_ORIGIN` and the request has no session cookie.
- Requires PHP 8.4 and `bad_cluster/runway` ^2.0.1 (was 1.*).
- The response controllers are `ApiResponseController` / `ApiExceptionResponseController` (`API*` were not found by the
  autoloader on case-sensitive file systems). Their constructors and the one of `ApiController` have new dependencies
  (e.g. `IAllowedOriginsProvider`).
- An internal error (5xx) is `Internal server error` without `APP_DEBUG=true`; an `ApiException` without an error code
  is a 500.
- OpenAPI: the title and the description of the spec are the `api_title` / `api_description` parameters (they were
  hardcoded); the platform DTOs are named without the namespace in the components (e.g. `Auth.LoginDTO`).

### Security

- The session tokens are random (`random_bytes()`, they were `mt_rand()`), only their SHA-256 hashes are stored; the
  password is a hash checked by `password_verify()` (the credentials were encrypted with a reversible key).
- The failed logins are limited: 5 per IP address and 50 in total for 15 minutes, then 429. A failed login does not end
  the current session.
- The session cookie is `HttpOnly`, `SameSite=Strict` and `Secure` over HTTPS.
- CSRF: a change made by a page of another site is rejected, with the session cookie or without it (e.g. the access is
  given by the web server).
- The request bodies (passwords) and the arguments of the calls are not logged; the messages of the internal errors and
  the traces are shown only with `APP_DEBUG=true`.
- `API_AUTH_ENABLED`: an empty or invalid value keeps the authorization, only an explicit `false` opens the API.
- A file argument must be an uploaded file (`is_uploaded_file()`), not any file of the server.
- The responses have `X-Content-Type-Options: nosniff` and `Cache-Control: no-store`.

### Added

- `Get`, `Post`, `Put`, `Patch`, `Delete` shortcuts; several endpoints per method; inline requirements
  (`{year<\d{4}>}`); `int` / `float` / `bool` placeholders accept only such values; an inherited endpoint belongs to
  the subclass (one abstract class can serve several prefixes).
- JSON 404 and 405 (with `Allow`) for the API paths; `OPTIONS` gets the allowed methods and the CORS headers.
- `API_AUTH_ENABLED=false` (every endpoint is public), `API_TOKEN_HEADER`, `api_path_prefix`, `api_session_cookie`.
- File arguments (`ApiEndpointArgumentFileDTO`), optional ones too.
- `NotFoundException`, `TooManyRequestsException`, `MethodNotAllowedException`, `EndpointDefinitionException`,
  `ApiRuntimeException`, `RuntimeInternalErrorException`; `BadRequestException` takes a single message.
- Responses: arrays and objects in the DTOs, 204 for no data, `X-Request-Id`.
- OpenAPI: the response schemas are components with `$ref`, nested and free-form objects, arrays of primitives and DTOs,
  repeatable `Docs\Throws`, multipart forms for files, the required body properties, the token security of the private
  operations, sorted paths and schemas.
- Unit, integration and E2E tests, PHPStan (max level), Infection (MSI 97%), CI on Linux, macOS and Windows with PHP 8.4
  and 8.5.

### Fixed

- A query parameter of any method is taken from the query string (then from the form fields).
- `Length` counts characters and checks the maximum; `Range` compares floats; a `"false"` query value is false; the
  assertions are not applied to a value of another type (it was a 500).
- Nullable arguments; a union type of an argument is a clear error, not a fatal one.
- Invalid UTF-8 in the response data is replaced; data that cannot be encoded is a 500, not an empty 200; the slashes
  are not escaped.
- An empty file field is a 400 (or the default value of an optional file), not a 500.
- OpenAPI: the endpoint paths, `Range` of floats, the file arguments are strings, no content for 204.
