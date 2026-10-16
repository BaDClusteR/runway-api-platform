# Runway API Platform

API platform for [Runway](https://github.com/BaDClusteR/runway).

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
- **Query, body, file:** `#[Api\Query]`, `#[Api\Body]`, `#[Api\File]`, optionally with the name in the request.
- An argument without a source gets its default value.

The mistakes (a placeholder without an argument, an argument without a source, etc.) are reported when the endpoints
are collected, not when the endpoint is called.

## Configuration

The ENV variables (see `.env`, override them in the project):

- `API_AUTH_ENABLED`: `false` makes every endpoint public, e.g. when the access is limited by the web server (by IP,
  basic auth, etc.). Then `API_CRYPTO_KEY`, `API_CRYPTO_KEY_LENGTH` and `API_AUTH_HASH` are not needed, and the
  `auth` / `logoff` endpoints respond with 404.
- `API_ALLOW_ORIGIN`: comma-separated origins allowed to call the API from a browser on another site (CORS), `*` for
  any site. Empty by default: only the pages of the API site itself can call it.
- `api_path_prefix` parameter: `/api` by default.

The errors of the API paths (including 404 and 405) are JSON. The details of the internal errors (the message, the
file, the trace) are shown only with `APP_DEBUG=true`; the client errors (4xx) are logged at the info level, the
internal ones at the error level, without the arguments of the calls.
